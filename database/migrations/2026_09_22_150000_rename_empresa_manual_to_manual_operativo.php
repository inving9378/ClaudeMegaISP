<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decisión de Irving (2026-09-22): el "Manual General de la Empresa"
 * (addon-empresa, /empresa/manual) se renombra a "Manual Operativo de
 * Meganet" — mismo módulo, mismas tablas, misma ruta; solo cambia el
 * nombre visible. Actualiza el nombre ya sembrado por la migración
 * `2026_08_18_190000_seed_empresa_manual_permission_and_registry` en
 * `module_registry` (el código fuente de ese registro ya quedó
 * actualizado, pero editar una migración ya corrida no re-siembra la fila
 * existente).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_registry')
            ->where('slug', 'addon-empresa')
            ->update(['name' => 'Manual Operativo de Meganet']);
    }

    public function down(): void
    {
        DB::table('module_registry')
            ->where('slug', 'addon-empresa')
            ->update(['name' => 'Manual General de la Empresa']);
    }
};
