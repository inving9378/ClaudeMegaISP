<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-11-23 (pedido de Irving) — el módulo «Cobranza» se llama «Campañas/Blaster»:
 * ya no solo cobra, también lanza avisos, anuncios y cortes por zona.
 *
 * Solo cambia el nombre que se ve. Lo interno se queda igual (slug
 * addon-cobranza-blaster, rutas /cobranza/*, permisos cobranza.*, tablas cobranza_*),
 * para no romper roles ya asignados, enlaces ni el historial.
 *
 * El menú lateral lee la etiqueta de module_sidebar_config, así que hay que cambiarla
 * en la base además de en el seeder. Si alguien la había personalizado (ya no dice
 * «Cobranza»), se respeta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('module_sidebar_config')) {
            DB::table('module_sidebar_config')
                ->where('module_key', 'cobranza-blaster')
                ->where('sidebar_label', 'Cobranza')
                ->update(['sidebar_label' => 'Campañas/Blaster', 'updated_at' => now()]);
        }

        if (Schema::hasTable('module_registry')) {
            DB::table('module_registry')
                ->where('slug', 'addon-cobranza-blaster')
                ->where('name', 'CobranzaBlaster')
                ->update(['name' => 'Campañas/Blaster', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('module_sidebar_config')) {
            DB::table('module_sidebar_config')
                ->where('module_key', 'cobranza-blaster')
                ->where('sidebar_label', 'Campañas/Blaster')
                ->update(['sidebar_label' => 'Cobranza', 'updated_at' => now()]);
        }

        if (Schema::hasTable('module_registry')) {
            DB::table('module_registry')
                ->where('slug', 'addon-cobranza-blaster')
                ->where('name', 'Campañas/Blaster')
                ->update(['name' => 'CobranzaBlaster', 'updated_at' => now()]);
        }
    }
};
