<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1125 — captura de «Segmento» en la ficha de clientes existentes. Aditiva e idempotente:
 * solo declara el campo `segmento_id` (select del catálogo crm_segmentos) en el módulo
 * ClientAdditionalInformation. Sin valor por defecto y SIN backfill: se llena a mano, cliente por cliente.
 * «Categoría» no se toca (decisión de Irving: conviven).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_segmentos') || ! Schema::hasColumn('client_additional_information', 'segmento_id')) {
            return;
        }
        $m = DB::table('modules')->where('name', 'ClientAdditionalInformation')->value('id');
        if (! $m || DB::table('field_modules')->where('module_id', $m)->where('name', 'segmento_id')->exists()) {
            return;
        }
        $segmentos = DB::table('crm_segmentos')->where('activo', true)->orderBy('orden')->pluck('nombre', 'id')->all();
        DB::table('field_modules')->insert([
            'module_id' => $m, 'name' => 'segmento_id', 'type' => '22', 'label' => 'Segmento',
            'placeholder' => 'Seleccionar segmento', 'options' => json_encode($segmentos, JSON_UNESCAPED_UNICODE),
            'position' => (int) (DB::table('field_modules')->where('module_id', $m)->max('position') ?? 0) + 1,
            'include' => 1, 'partition' => '', 'additional_field' => 0, 'class_col' => 'full',
            'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            'class_field' => 'col-sm-12 col-md-9', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $m = DB::table('modules')->where('name', 'ClientAdditionalInformation')->value('id');
        if ($m) {
            DB::table('field_modules')->where('module_id', $m)->where('name', 'segmento_id')->delete();
        }
    }
};
