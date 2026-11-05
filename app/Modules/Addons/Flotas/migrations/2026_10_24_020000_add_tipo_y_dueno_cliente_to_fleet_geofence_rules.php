<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Item #644 (Flotas Clientes P4c) — las reglas de alerta del CLIENTE reutilizan fleet_geofence_rules
// (sin motor paralelo). Cambios ADITIVOS: `tipo` (default 'geocerca' = comportamiento actual),
// `params` (umbrales por tipo) y `user_id` nullable (una regla del portal pertenece al cliente
// por client_id, no a un usuario del panel; así nunca aparece en las reglas/alertas de /flotas).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('fleet_geofence_rules', 'tipo')) {
            Schema::table('fleet_geofence_rules', function (Blueprint $table) {
                $table->string('tipo', 30)->default('geocerca')->after('description');
                $table->json('params')->nullable()->after('tipo');
                $table->string('origen', 12)->default('panel')->after('params')->index();
            });
        }

        DB::statement('ALTER TABLE fleet_geofence_rules MODIFY user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Aditiva a propósito: no se revierte user_id (puede haber reglas de cliente sin usuario).
        if (Schema::hasColumn('fleet_geofence_rules', 'tipo')) {
            Schema::table('fleet_geofence_rules', function (Blueprint $table) {
                $table->dropIndex(['origen']);
                $table->dropColumn(['tipo', 'params', 'origen']);
            });
        }
    }
};
