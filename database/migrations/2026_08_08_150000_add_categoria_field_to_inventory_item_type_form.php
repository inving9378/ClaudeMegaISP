<?php

use App\Models\InventoryItemType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hace la CATEGORÍA seleccionable/visible en el CRUD de Tipos de Artículo (item #572).
 *
 * Hasta hoy `inventory_item_types.categoria` solo se podía poblar por migración: no estaba en el
 * `$fillable`, ni en el formulario, ni en el listado. El formulario y el datatable de este módulo
 * son DB-driven (`field_modules` / `column_datatable_modules` sobre la fila `modules.name =
 * InventoryItemType`), así que exponer el campo = agregar esas dos filas de catálogo.
 *
 * Va como MIGRACIÓN versionada e idempotente a propósito: las filas de catálogo que vivían solo en
 * `migrations_old/` son la causa raíz conocida de que un entorno nuevo nazca con huecos y el
 * síntoma aparezca disfrazado de bug de frontend (ver CLAUDE.md, "Catálogo esencial atrapado").
 *
 * `type = 22` es el select con `options` JSON — mismo tipo que ya usa el campo `type` del módulo.
 */
return new class extends Migration
{
    private const MODULO = 'InventoryItemType';

    public function up(): void
    {
        $moduleId = DB::table('modules')->where('name', self::MODULO)->value('id');
        if (! $moduleId) {
            return; // catálogo ausente en este entorno: no inventamos el módulo aquí
        }

        // 1. Campo del formulario (select con las 3 categorías). Sin duplicar si ya existe.
        $existeCampo = DB::table('field_modules')
            ->where('module_id', $moduleId)->where('name', 'categoria')->exists();

        if (! $existeCampo) {
            DB::table('field_modules')->insert([
                'module_id'   => $moduleId,
                'include'     => 1,
                'name'        => 'categoria',
                'type'        => '22',                       // select
                'label'       => 'Categoría',
                'hint'        => 'Herramienta se devuelve · Material se gasta · Equipo de cliente se presta/instala',
                'placeholder' => 'Sin clasificar',
                'options'     => json_encode(InventoryItemType::CATEGORIAS, JSON_UNESCAPED_UNICODE),
                'position'    => 4,
                'class_col'   => 'full',
                'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                'class_field' => 'col-sm-12 col-md-9',
                'additional_field' => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } else {
            // Ya existía (re-corrida o entorno adelantado): re-sincroniza SOLO las opciones, para
            // que agregar una 4ª categoría al modelo se refleje sin escribir otra migración.
            DB::table('field_modules')
                ->where('module_id', $moduleId)->where('name', 'categoria')
                ->update([
                    'options'    => json_encode(InventoryItemType::CATEGORIAS, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }

        // 2. Columna en el listado, para que la clasificación se vea sin abrir cada fila.
        $existeColumna = DB::table('column_datatable_modules')
            ->where('module_id', $moduleId)->where('name', 'categoria')->exists();

        if (! $existeColumna) {
            DB::table('column_datatable_modules')->insert([
                'module_id'  => $moduleId,
                'name'       => 'categoria',
                'label'      => 'Categoría',
                'active'     => '1',
                'order'      => '5',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('name', self::MODULO)->value('id');
        if (! $moduleId) {
            return;
        }

        DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'categoria')->delete();
        DB::table('column_datatable_modules')->where('module_id', $moduleId)->where('name', 'categoria')->delete();
    }
};
