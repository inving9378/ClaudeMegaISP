<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1 — Denormalización de tenancy en MegaFamilia.
 *
 * Agrega `client_isp_id` (nullable POR AHORA) + índice + FK a `clients` ON DELETE
 * CASCADE en las 14 tablas hijas. Aditiva: no cambia comportamiento. El backfill (M2)
 * y el NOT NULL (M4) van en migraciones posteriores. `parental_accounts` NO se toca
 * (ya tiene la columna desde 2026_05_22_200000).
 */
return new class extends Migration
{
    private array $tables = [
        'parental_profiles',
        'parental_devices',
        'parental_alerts',
        'parental_events',
        'parental_licenses',
        'parental_app_blocks',
        'parental_geofences',
        'parental_rewards',
        'parental_rules',
        'parental_schedules',
        'parental_tasks',
        'parental_web_blocks',
        'parental_requests',
        'parental_locations',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'client_isp_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('client_isp_id')->nullable()->index();
                $t->foreign('client_isp_id')->references('id')->on('clients')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'client_isp_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['client_isp_id']);
                $t->dropColumn('client_isp_id');
            });
        }
    }
};
