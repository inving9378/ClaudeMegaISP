<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decisión de Irving (2026-09-22): ahora que el capítulo Talento vive en
 * /manual (ver TalentoUserManualSeeder), esta pantalla pasa a llamarse
 * "Manual Operativo de Meganet" — mismo módulo (addon-manual), misma ruta;
 * solo cambia el nombre visible. Actualiza el nombre ya sembrado por
 * 2026_05_21_120100_add_permissions_manual (que no toca module_registry) /
 * el alta original del addon en `module_registry` — editar una migración ya
 * corrida no re-siembra la fila existente, por eso una migración nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_registry')
            ->where('slug', 'addon-manual')
            ->update(['name' => 'Manual Operativo de Meganet']);
    }

    public function down(): void
    {
        DB::table('module_registry')
            ->where('slug', 'addon-manual')
            ->update(['name' => 'Manual de Usuario']);
    }
};
