<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-01 (#535, decisión de Irving 2026-10-17): una sola tarjeta «Datos de facturación» y una sola
 * fuente en BD = client_fiscal_data. Aditiva e idempotente:
 *  - client_fiscal_data += calle, ciudad (opcionales);
 *  - lo capturado en «Dirección de facturación» (billing_addresses, que se conserva sin borrar) se
 *    copia SOLO a campos vacíos de client_fiscal_data; nunca pisa un valor distinto. Los choques y
 *    los C.P. que no son de 5 dígitos los lista `php artisan facturacion:datos-fiscales-diagnostico`
 *    para que Irving decida;
 *  - las columnas de la lista de clientes (mismos nombres) cambian solo de etiqueta.
 */
return new class extends Migration
{
    private const ETIQUETAS = [
        'billing_name'     => ['Nombre de Facturación', 'Razón social (facturación)'],
        'billing_street'   => ['Calle de Facturación', 'Calle (facturación)'],
        'billing_zip_code' => ['Código Postal de la facturación', 'C.P. fiscal'],
        'billing_city'     => ['Ciudad de Facturación', 'Ciudad (facturación)'],
    ];

    public function up(): void
    {
        Schema::table('client_fiscal_data', function (Blueprint $table) {
            if (! Schema::hasColumn('client_fiscal_data', 'calle')) {
                $table->string('calle', 255)->nullable()->after('correo_fiscal');
            }
            if (! Schema::hasColumn('client_fiscal_data', 'ciudad')) {
                $table->string('ciudad', 150)->nullable()->after('calle');
            }
        });

        $this->copiarDireccionesDeFacturacion();

        $moduleId = DB::table('modules')->where('name', 'Client')->value('id');
        if ($moduleId) {
            foreach (self::ETIQUETAS as $columna => [$antes, $despues]) {
                DB::table('column_datatable_modules')->where('module_id', $moduleId)
                    ->where('name', $columna)->where('label', $antes)
                    ->update(['label' => $despues, 'updated_at' => now()]);
            }
            Cache::forget('datatable_columns_allClient');
        }
    }

    /** billing_addresses → client_fiscal_data, solo a campos vacíos (idempotente). */
    private function copiarDireccionesDeFacturacion(): void
    {
        if (! Schema::hasTable('billing_addresses')) {
            return;
        }

        // Una dirección por cliente: la más reciente (gana la de id mayor). Solo clientes que existen
        // (FK de la tabla destino).
        $direcciones = DB::table('billing_addresses as b')
            ->join('clients as c', 'c.id', '=', 'b.client_id')
            ->select('b.id', 'b.client_id', 'b.billing_name', 'b.billing_street', 'b.billing_zip_code', 'b.billing_city')
            ->orderBy('b.id')
            ->get()
            ->keyBy('client_id');

        foreach ($direcciones as $d) {
            $origen = [
                'razon_social'         => $this->texto($d->billing_name, 250),
                'calle'                => $this->texto($d->billing_street, 255),
                'codigo_postal_fiscal' => preg_match('/^\d{5}$/', trim((string) $d->billing_zip_code)) ? trim($d->billing_zip_code) : null,
                'ciudad'               => $this->texto($d->billing_city, 150),
            ];
            $origen = array_filter($origen, fn ($v) => $v !== null);
            if ($origen === []) {
                continue;
            }

            $actual = DB::table('client_fiscal_data')->where('client_id', $d->client_id)->first();
            if (! $actual) {
                DB::table('client_fiscal_data')->insert($origen + [
                    'client_id' => $d->client_id, 'facturacion_fiscal' => false,
                    'created_by' => 0, 'updated_by' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                continue;
            }

            $faltantes = [];
            foreach ($origen as $campo => $valor) {
                if (trim((string) ($actual->{$campo} ?? '')) === '') {
                    $faltantes[$campo] = $valor;
                }
            }
            if ($faltantes !== []) {
                DB::table('client_fiscal_data')->where('id', $actual->id)->update($faltantes + ['updated_at' => now()]);
            }
        }
    }

    private function texto($valor, int $max): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, $max);
    }

    public function down(): void
    {
        // Las filas copiadas no se quitan: no se distinguen de las capturadas después.
        $moduleId = DB::table('modules')->where('name', 'Client')->value('id');
        if ($moduleId) {
            foreach (self::ETIQUETAS as $columna => [$antes, $despues]) {
                DB::table('column_datatable_modules')->where('module_id', $moduleId)
                    ->where('name', $columna)->where('label', $despues)
                    ->update(['label' => $antes, 'updated_at' => now()]);
            }
            Cache::forget('datatable_columns_allClient');
        }
        Schema::table('client_fiscal_data', function (Blueprint $table) {
            foreach (['ciudad', 'calle'] as $col) {
                if (Schema::hasColumn('client_fiscal_data', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
