<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * FR-03 (#537), corrección tras la revisión de Irving (2026-10-17): el plazo no se veía al CREAR un
 * prospecto, porque esa pantalla usa el catálogo de campos del módulo «Crm» (editar usa
 * «CrmMainInformation», que sí lo tenía). Aditiva e idempotente:
 *  - módulo «Crm»: «Duración del Contrato» (18 meses por defecto) y «Meses (solo plazo Especial)»;
 *  - los campos «Meses (solo plazo Especial)» (Crm, CrmMainInformation, ClientMainInformation) llevan
 *    depend = «duration_contract_id=<id de Especial>»: los formularios solo los muestran con
 *    «Especial» (helpers/camposCondicionales.js). El id se resuelve aquí, por nombre, en cada entorno;
 *  - columna «Plazo de contrato» en la lista de CRM (solo lectura).
 */
return new class extends Migration
{
    private const MODULOS_MESES = ['Crm', 'CrmMainInformation', 'ClientMainInformation'];

    public function up(): void
    {
        $especial = DB::table('duration_contracts')->where('name', 'Especial')->value('id');
        $id18 = DB::table('duration_contracts')->where('name', '18 Meses')->value('id');
        $condicion = $especial ? 'duration_contract_id=' . $especial : null;

        $crm = DB::table('modules')->where('name', 'Crm')->value('id');
        if ($crm) {
            $despues = (int) (DB::table('field_modules')->where('module_id', $crm)->max('position') ?? 0);
            $this->campo($crm, 'duration_contract_id', [
                'type' => '22', 'label' => 'Duración del Contrato', 'position' => $despues + 1,
                'search' => json_encode(['model' => 'App\\Models\\DurationContract', 'text' => 'name', 'id' => 'id']),
                'default_value' => $id18 ? json_encode((string) $id18) : null,
            ]);
            $this->campo($crm, 'plazo_especial_meses', [
                'type' => '15', 'label' => 'Meses (solo plazo Especial)', 'placeholder' => 'Ej. 9', 'options' => '[]',
                'hint' => 'Solo si la duración del contrato es «Especial».', 'position' => $despues + 2,
            ]);
        }

        if ($condicion) {
            foreach (self::MODULOS_MESES as $modulo) {
                $moduleId = DB::table('modules')->where('name', $modulo)->value('id');
                if ($moduleId) {
                    DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'plazo_especial_meses')
                        ->where(fn ($q) => $q->whereNull('depend')->orWhere('depend', ''))
                        ->update(['depend' => $condicion, 'updated_at' => now()]);
                }
            }
        }

        if ($crm && ! DB::table('column_datatable_modules')->where('module_id', $crm)->where('name', 'plazo_contrato')->exists()) {
            DB::table('column_datatable_modules')->insert([
                'module_id' => $crm, 'name' => 'plazo_contrato', 'filter_name' => null, 'label' => 'Plazo de contrato',
                'active' => '1', 'class' => null, 'order' => '18', 'created_at' => now(), 'updated_at' => now(),
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
        $crm = DB::table('modules')->where('name', 'Crm')->value('id');
        if ($crm) {
            DB::table('field_modules')->where('module_id', $crm)->whereIn('name', ['duration_contract_id', 'plazo_especial_meses'])->delete();
            DB::table('column_datatable_modules')->where('module_id', $crm)->where('name', 'plazo_contrato')->delete();
            Cache::forget('datatable_columns_allCrm');
        }
        foreach (self::MODULOS_MESES as $modulo) {
            $moduleId = DB::table('modules')->where('name', $modulo)->value('id');
            if ($moduleId) {
                DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'plazo_especial_meses')
                    ->where('depend', 'like', 'duration_contract_id=%')->update(['depend' => null]);
            }
        }
    }
};
