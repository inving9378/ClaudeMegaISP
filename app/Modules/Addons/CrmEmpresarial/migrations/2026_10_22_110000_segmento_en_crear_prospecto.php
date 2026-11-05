<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Empresarial — segmento en Crear/Editar Prospecto (item #557, épica #554). Aditiva e idempotente:
 *  - crm_main_information += requiere_expediente_dedicado (boolean, default false);
 *  - campos de formulario en «Crm» (alta) y «CrmMainInformation» (edición): segmento, tipo de persona,
 *    nombre comercial (solo persona moral) y la casilla de Dedicado (solo con segmento Dedicado);
 *  - columna «Segmento» en la lista de CRM (solo lectura).
 * Los ids del catálogo de segmentos se resuelven por código en cada entorno. Un prospecto sin
 * segmento sigue siendo el residencial de siempre.
 */
return new class extends Migration
{
    private const MODULOS = ['Crm', 'CrmMainInformation'];

    public function up(): void
    {
        if (! Schema::hasTable('crm_segmentos') || ! Schema::hasColumn('crm_main_information', 'segmento_id')) {
            return; // la migración del modelo de datos (#555) corre antes
        }
        if (! Schema::hasColumn('crm_main_information', 'requiere_expediente_dedicado')) {
            Schema::table('crm_main_information', function (Blueprint $t) {
                $t->boolean('requiere_expediente_dedicado')->default(false);
            });
        }

        $segmentos = DB::table('crm_segmentos')->where('activo', true)->orderBy('orden')->pluck('nombre', 'id')->all();
        $res = DB::table('crm_segmentos')->where('codigo', 'RES')->value('id');
        $ded = DB::table('crm_segmentos')->where('codigo', 'DED')->value('id');

        foreach (self::MODULOS as $modulo) {
            $m = DB::table('modules')->where('name', $modulo)->value('id');
            if (! $m) {
                continue;
            }
            $pos = (int) (DB::table('field_modules')->where('module_id', $m)->max('position') ?? 0);
            $this->campo($m, 'segmento_id', [
                'type' => '22', 'label' => 'Segmento', 'placeholder' => 'Seleccionar segmento',
                'options' => json_encode($segmentos, JSON_UNESCAPED_UNICODE), 'position' => $pos + 1,
                'default_value' => $res ? json_encode((string) $res) : null,
            ]);
            $this->campo($m, 'requiere_expediente_dedicado', [
                'type' => '16', 'label' => 'Requiere expediente y contratación de Internet Dedicado',
                'options' => '[]', 'position' => $pos + 2, 'depend' => $ded ? 'segmento_id=' . $ded : null,
            ]);
            $this->campo($m, 'tipo_persona', [
                'type' => '22', 'label' => 'Tipo de persona', 'placeholder' => 'Seleccionar tipo de persona',
                'options' => json_encode(['fisica' => 'Persona física', 'moral' => 'Persona moral'], JSON_UNESCAPED_UNICODE),
                'position' => $pos + 3, 'default_value' => json_encode('fisica'),
            ]);
            $this->campo($m, 'nombre_comercial', [
                'type' => '1', 'label' => 'Nombre comercial', 'placeholder' => 'nombre comercial',
                'options' => '[]', 'position' => $pos + 4, 'depend' => 'tipo_persona=moral',
            ]);
        }

        $crm = DB::table('modules')->where('name', 'Crm')->value('id');
        if ($crm && ! DB::table('column_datatable_modules')->where('module_id', $crm)->where('name', 'segmento')->exists()) {
            DB::table('column_datatable_modules')->insert([
                'module_id' => $crm, 'name' => 'segmento', 'filter_name' => null, 'label' => 'Segmento',
                'active' => '1', 'class' => null, 'order' => '19', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Cache::forget('datatable_columns_allCrm');
    }

    private function campo(int $moduleId, string $nombre, array $datos): void
    {
        if (DB::table('field_modules')->where('module_id', $moduleId)->where('name', $nombre)->exists()) {
            return;
        }
        DB::table('field_modules')->insert($datos + [
            'module_id' => $moduleId, 'name' => $nombre, 'include' => 1, 'partition' => '',
            'additional_field' => 0, 'class_col' => 'full',
            'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            'class_field' => 'col-sm-12 col-md-9', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach (self::MODULOS as $modulo) {
            $m = DB::table('modules')->where('name', $modulo)->value('id');
            if ($m) {
                DB::table('field_modules')->where('module_id', $m)
                    ->whereIn('name', ['segmento_id', 'requiere_expediente_dedicado', 'tipo_persona', 'nombre_comercial'])->delete();
            }
        }
        $crm = DB::table('modules')->where('name', 'Crm')->value('id');
        if ($crm) {
            DB::table('column_datatable_modules')->where('module_id', $crm)->where('name', 'segmento')->delete();
        }
        Cache::forget('datatable_columns_allCrm');
    }
};
