<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira los niveles de colaborador (Fase 7b, 2026-06-04), primer tiempo: el código ya no los
 * usa; aquí se quitan sus permisos y su página del manual. Nadie los usaba (0 colaboradores con
 * nivel, 0 promociones, 0 tipos con nivel requerido) y duplicaban lo que ya se maneja en
 * Administración. Decisión del 2026-10-21.
 *
 * Las tablas talento_levels/talento_level_assignments y las columnas level_id/required_level_id
 * se borran en la migración de contracción (rama contraccion/talento-niveles-puestos), cuando
 * esta versión lleve los días mínimos en producción (guardrail #1018).
 */
return new class extends Migration
{
    private const PERMISOS = ['talento.levels.view', 'talento.levels.manage'];

    public function up(): void
    {
        // Spatie borra en cascada sus asignaciones a roles y usuarios.
        DB::table('permissions')->whereIn('name', self::PERMISOS)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        if (Schema::hasTable('manual_sections')) {
            DB::table('manual_sections')->where('module_slug', 'talento-niveles')->delete();
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
