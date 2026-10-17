<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-01 (#535): solo lectura. Compara «Dirección de facturación» (billing_addresses, histórico) con
 * «Datos de facturación» (client_fiscal_data, la fuente única) y lista lo que la migración NO copió
 * porque se contradice o no es válido, para que Irving decida caso por caso. No escribe nada.
 *
 *   php artisan facturacion:datos-fiscales-diagnostico           (resumen + primeros 50 casos)
 *   php artisan facturacion:datos-fiscales-diagnostico --todos   (todos los casos)
 */
class DatosFiscalesDiagnosticoCommand extends Command
{
    protected $signature = 'facturacion:datos-fiscales-diagnostico {--todos : Lista todos los casos, no solo los primeros 50}';

    protected $description = 'Lista choques entre la Dirección de facturación y los Datos de facturación (solo lectura)';

    private const PARES = [
        'billing_name'     => ['razon_social', 'Razón social'],
        'billing_street'   => ['calle', 'Calle'],
        'billing_zip_code' => ['codigo_postal_fiscal', 'C.P. fiscal'],
        'billing_city'     => ['ciudad', 'Ciudad'],
    ];

    public function handle(): int
    {
        $this->line('Datos de facturación: ' . DB::table('client_fiscal_data')->whereNull('deleted_at')->count()
            . ' clientes (' . DB::table('client_fiscal_data')->whereNull('deleted_at')->where('facturacion_fiscal', true)->count()
            . ' con factura fiscal; el resto se factura como público en general).');

        if (! Schema::hasTable('billing_addresses')) {
            $this->info('No existe la tabla billing_addresses: nada que comparar.');

            return self::SUCCESS;
        }

        $filas = DB::table('billing_addresses as b')
            ->join('clients as c', 'c.id', '=', 'b.client_id')
            ->leftJoin('client_fiscal_data as f', function ($j) {
                $j->on('f.client_id', '=', 'b.client_id')->whereNull('f.deleted_at');
            })
            ->select('b.id', 'b.client_id', 'b.billing_name', 'b.billing_street', 'b.billing_zip_code', 'b.billing_city',
                'f.id as fiscal_id', 'f.razon_social', 'f.calle', 'f.codigo_postal_fiscal', 'f.ciudad')
            ->orderBy('b.id')
            ->get()
            ->keyBy('client_id') // una por cliente: la más reciente, igual que la migración
            ->sortKeys();

        $this->line('Direcciones de facturación (histórico): ' . $filas->count() . ' clientes.');

        $casos = [];
        foreach ($filas as $r) {
            foreach (self::PARES as $origen => [$destino, $etiqueta]) {
                $a = trim((string) $r->{$origen});
                if ($a === '') {
                    continue;
                }
                if ($origen === 'billing_zip_code' && ! preg_match('/^\d{5}$/', $a)) {
                    $casos[] = [$r->client_id, $etiqueta, $a, (string) $r->{$destino}, 'C.P. que no es de 5 dígitos (no se copió)'];
                    continue;
                }
                $b = trim((string) $r->{$destino});
                if ($b !== '' && mb_strtolower($a) !== mb_strtolower($b)) {
                    $casos[] = [$r->client_id, $etiqueta, $a, $b, 'Distinto (se conservó el de Datos de facturación)'];
                }
            }
        }

        if ($casos === []) {
            $this->info('Sin choques: todo lo de la Dirección de facturación ya está en Datos de facturación o no aplica.');

            return self::SUCCESS;
        }

        $this->warn(count($casos) . ' casos para revisar:');
        $this->table(['Cliente', 'Campo', 'Dirección de facturación', 'Datos de facturación', 'Motivo'],
            $this->option('todos') ? $casos : array_slice($casos, 0, 50));
        if (! $this->option('todos') && count($casos) > 50) {
            $this->line('… usa --todos para ver los ' . count($casos) . '.');
        }

        return self::SUCCESS;
    }
}
