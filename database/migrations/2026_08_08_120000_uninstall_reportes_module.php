<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desinstala addon-reportes (cascarón vacío que duplica /releases) con
 * semántica keep_data: solo desactiva en module_registry, NO toca permisos
 * Spatie (release_* pertenecen a Core/Release, no a Reportes). Ver item #557
 * (bloque 1) de la Hoja de Ruta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_registry')
            ->where('slug', 'addon-reportes')
            ->update(['active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('module_registry')
            ->where('slug', 'addon-reportes')
            ->update(['active' => true, 'updated_at' => now()]);
    }
};
