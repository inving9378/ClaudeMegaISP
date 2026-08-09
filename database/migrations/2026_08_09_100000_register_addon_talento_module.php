<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta addon-talento en module_registry (estaba en disco pero invisible
 * en Module Manager, corriendo por el fail-open de ModuleManagerService::isActive()
 * ?? true). Solo lo vuelve gobernable/apagable; no cambia su comportamiento
 * actual (queda activo). Ver item #565 de la Hoja de Ruta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_registry')->updateOrInsert(
            ['slug' => 'addon-talento'],
            [
                'name'               => 'Talento Meganet',
                'installed_version'  => '2.0.0',
                'type'               => 'addon',
                'active'             => true,
                'installed_at'       => now(),
                'updated_at'         => now(),
                'created_at'         => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('module_registry')->where('slug', 'addon-talento')->delete();
    }
};
