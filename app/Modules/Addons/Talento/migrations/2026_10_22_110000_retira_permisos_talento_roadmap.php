<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira el roadmap de fases del módulo Talento (/talento/roadmap), primer tiempo: el código ya no
 * lo usa; aquí se quitan sus permisos y su página del manual. Decisión del 2026-10-22, después de
 * verificar fase por fase lo construido (docs/bitacora/2026-10-22-talento-quita-roadmap.md, con
 * la copia de sus filas). La tabla talento_roadmap_items se borra en la contracción.
 */
return new class extends Migration
{
    private const PERMISOS = ['talento.roadmap.view', 'talento.roadmap.manage'];

    public function up(): void
    {
        // Spatie borra en cascada sus asignaciones a roles y usuarios.
        DB::table('permissions')->whereIn('name', self::PERMISOS)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        if (Schema::hasTable('manual_sections')) {
            DB::table('manual_sections')->where('module_slug', 'talento-roadmap')->delete();
        }
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $nombre) {
            DB::table('permissions')->insertOrIgnore(['name' => $nombre, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
