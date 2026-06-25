<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M4 — client_isp_id → NOT NULL en las 14 tablas hijas.
 *
 * Último paso: solo tras M2 (backfill verificado, 0 NULL) y con el observer
 * DerivesClientIspId desplegado (filas nuevas se autopueblan). El FK CASCADE creado
 * en M1 es compatible con NOT NULL y NO se toca.
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
            if (! Schema::hasColumn($table, 'client_isp_id')) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` MODIFY client_isp_id BIGINT UNSIGNED NOT NULL");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'client_isp_id')) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` MODIFY client_isp_id BIGINT UNSIGNED NULL");
        }
    }
};
