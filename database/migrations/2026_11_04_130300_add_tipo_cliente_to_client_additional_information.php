<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * #988 — «Tipo de cliente», el único campo que dice qué clase de cliente es. Regla de Irving: todos
 * quedan Residencial y solo los que hoy tienen is_dedicated = 1 quedan Dedicado; a los clientes sin
 * fila se les crea, para que no quede ese caso especial. category e is_dedicated se conservan.
 * Ocupa el lugar de «Categoría», «Cliente dedicado» y «Segmento» en formularios y listado.
 */
return new class extends Migration
{
    private const OPCIONES = ['residencial' => 'Residencial', 'negocio' => 'Negocio', 'pyme' => 'Pyme', 'dedicado' => 'Dedicado'];

    private const OCULTOS = ['category', 'is_dedicated', 'segmento_id'];

    public function up(): void
    {
        $t = 'client_additional_information';
        if (! Schema::hasColumn($t, 'tipo_cliente')) {
            Schema::table($t, function (Blueprint $table) {
                $table->enum('tipo_cliente', array_keys(self::OPCIONES))->default('residencial')->after('category')
                    ->comment('residencial | negocio | pyme | dedicado. Única fuente de verdad; is_dedicated se deriva');
                $table->index('tipo_cliente');
            });
        }

        DB::table($t)->where('is_dedicated', true)->update(['tipo_cliente' => 'dedicado']);

        $ahora = now();
        $sinFila = DB::table('clients as c')
            ->leftJoin("{$t} as a", 'a.client_id', '=', 'c.id')
            ->whereNull('c.deleted_at')->whereNull('a.id')->pluck('c.id');
        foreach ($sinFila->chunk(500) as $ids) {
            DB::table($t)->insert($ids->map(fn ($id) => ['client_id' => $id, 'tipo_cliente' => 'residencial', 'created_at' => $ahora, 'updated_at' => $ahora])->all());
        }

        $this->formularios();
        $this->listado();

        $conteos = DB::table($t)->whereNull('deleted_at')->selectRaw('tipo_cliente, COUNT(*) n')->groupBy('tipo_cliente')->pluck('n', 'tipo_cliente')->all();
        $linea = 'Tipo de cliente (#988): ' . json_encode($conteos) . ' · filas creadas para clientes sin fila: ' . $sinFila->count();
        Log::info($linea);
        echo $linea . PHP_EOL;
    }

    /** Select en Crear cliente y en la ficha, ya en Residencial; lo que reemplaza deja de mostrarse. */
    private function formularios(): void
    {
        foreach (['Client', 'ClientAdditionalInformation'] as $nombre) {
            $m = DB::table('modules')->where('name', $nombre)->value('id');
            if (! $m) {
                continue;
            }
            if (! DB::table('field_modules')->where('module_id', $m)->where('name', 'tipo_cliente')->exists()) {
                DB::table('field_modules')->insert([
                    'module_id' => $m, 'include' => 1, 'name' => 'tipo_cliente', 'type' => '22', 'label' => 'Tipo de cliente',
                    'placeholder' => 'Seleccionar tipo de cliente', 'options' => json_encode(self::OPCIONES, JSON_UNESCAPED_UNICODE),
                    'position' => (int) (DB::table('field_modules')->where('module_id', $m)->where('name', 'category')->value('position') ?? 0),
                    'disabled' => 0, 'default_value' => json_encode('residencial'), 'partition' => '', 'additional_field' => 0, 'class_col' => 'full',
                    'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                    'class_field' => 'col-sm-12 col-md-9', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('field_modules')->where('module_id', $m)->whereIn('name', self::OCULTOS)->update(['include' => 0]);
        }
    }

    /** En el listado la columna toma el lugar de «Categoría», que queda desactivada. */
    private function listado(): void
    {
        $m = DB::table('modules')->where('name', 'Client')->value('id');
        if (! $m || DB::table('column_datatable_modules')->where('module_id', $m)->where('name', 'tipo_cliente')->exists()) {
            return;
        }
        $categoria = DB::table('column_datatable_modules')->where('module_id', $m)->where('name', 'category')->first();
        $ultimo = (int) DB::table('column_datatable_modules')->where('module_id', $m)->max(DB::raw('CAST(`order` AS UNSIGNED)'));
        DB::table('column_datatable_modules')->insert([
            'module_id' => $m, 'name' => 'tipo_cliente', 'label' => 'Tipo de cliente', 'active' => '1',
            'filter_name' => 'client_additional_information.tipo_cliente', 'order' => $categoria->order ?? $ultimo + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($categoria) {
            DB::table('column_datatable_modules')->where('id', $categoria->id)->update(['active' => '0', 'order' => $ultimo + 2]);
        }
    }

    public function down(): void
    {
        foreach (['Client', 'ClientAdditionalInformation'] as $nombre) {
            $m = DB::table('modules')->where('name', $nombre)->value('id');
            if ($m) {
                DB::table('field_modules')->where('module_id', $m)->where('name', 'tipo_cliente')->delete();
                DB::table('field_modules')->where('module_id', $m)->whereIn('name', self::OCULTOS)->update(['include' => 1]);
            }
        }
        $m = DB::table('modules')->where('name', 'Client')->value('id');
        if ($m) {
            $nueva = DB::table('column_datatable_modules')->where('module_id', $m)->where('name', 'tipo_cliente')->first();
            if ($nueva) {
                DB::table('column_datatable_modules')->where('module_id', $m)->where('name', 'category')->update(['active' => '1', 'order' => $nueva->order]);
                DB::table('column_datatable_modules')->where('id', $nueva->id)->delete();
            }
        }
        if (Schema::hasColumn('client_additional_information', 'tipo_cliente')) {
            Schema::table('client_additional_information', function (Blueprint $table) {
                $table->dropIndex(['tipo_cliente']);
                $table->dropColumn('tipo_cliente');
            });
        }
    }
};
