<?php

use App\Models\InventoryItemType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suma "Equipo de red" (4ª categoría) al SELECT del formulario de Tipos de Artículo (item #1007,
 * seguimiento de #572). El campo `categoria` es DB-driven (`field_modules`, fila creada por la
 * migración 2026_08_08_150000) — esa migración ya dejó previsto un `else` que re-sincroniza
 * `options` si vuelve a correr, pero como YA corrió, agregar una clave nueva al modelo no le llega
 * sin esta migración explícita.
 *
 * Solo actualiza `options` y el `hint`; no toca la columna del listado (ya existe, no cambia).
 */
return new class extends Migration
{
    private const MODULO = 'InventoryItemType';

    public function up(): void
    {
        $moduleId = DB::table('modules')->where('name', self::MODULO)->value('id');
        if (! $moduleId) {
            return; // catálogo ausente en este entorno: nada que sincronizar
        }

        DB::table('field_modules')
            ->where('module_id', $moduleId)->where('name', 'categoria')
            ->update([
                'options'    => json_encode(InventoryItemType::CATEGORIAS, JSON_UNESCAPED_UNICODE),
                'hint'       => 'Herramienta se devuelve · Material se gasta · Equipo de cliente se presta/instala · Equipo de red es infraestructura (router, switch, ONU, OLT, antena, radio)',
                'updated_at' => now(),
            ]);
    }

    /**
     * down() no revierte a las 3 opciones viejas: si algún tipo ya quedó clasificado como
     * `equipo_red` desde el formulario, ocultar la opción del select lo dejaría inconsistente
     * (un valor guardado que el select ya no ofrece). No es destructivo dejarlo así.
     */
    public function down(): void
    {
    }
};
