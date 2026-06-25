<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M2 — Backfill de client_isp_id en las 14 tablas hijas (idempotente).
 *
 * Cada tabla deriva client_isp_id desde la cadena hasta parental_accounts.client_isp_id:
 *  - tier-1 (account_id): JOIN directo a parental_accounts.
 *  - tier-2 (profile_id): JOIN profiles → accounts.
 *  - parental_locations (device_id): JOIN devices → accounts.
 * Solo toca filas con client_isp_id NULL (idempotente). El NOT NULL llega en M4.
 */
return new class extends Migration
{
    /** tabla hija => SQL de JOIN hacia parental_accounts (alias a). */
    private array $joins = [
        // tier-1: account_id directo
        'parental_profiles' => 'JOIN parental_accounts a ON c.account_id = a.id',
        'parental_devices'  => 'JOIN parental_accounts a ON c.account_id = a.id',
        'parental_alerts'   => 'JOIN parental_accounts a ON c.account_id = a.id',
        'parental_events'   => 'JOIN parental_accounts a ON c.account_id = a.id',
        'parental_licenses' => 'JOIN parental_accounts a ON c.account_id = a.id',
        // tier-2: profile_id → account
        'parental_app_blocks' => 'JOIN parental_profiles p ON c.profile_id = p.id JOIN parental_accounts a ON p.account_id = a.id',
        'parental_geofences'  => 'JOIN parental_profiles p ON c.profile_id = p.id JOIN parental_accounts a ON p.account_id = a.id',
        'parental_rewards'    => 'JOIN parental_profiles p ON c.profile_id = p.id JOIN parental_accounts a ON p.account_id = a.id',
        'parental_rules'      => 'JOIN parental_profiles p ON c.profile_id = p.id JOIN parental_accounts a ON p.account_id = a.id',
        'parental_schedules'  => 'JOIN parental_profiles p ON c.profile_id = p.id JOIN parental_accounts a ON p.account_id = a.id',
        'parental_tasks'      => 'JOIN parental_profiles p ON c.profile_id = p.id JOIN parental_accounts a ON p.account_id = a.id',
        'parental_web_blocks' => 'JOIN parental_profiles p ON c.profile_id = p.id JOIN parental_accounts a ON p.account_id = a.id',
        'parental_requests'   => 'JOIN parental_profiles p ON c.profile_id = p.id JOIN parental_accounts a ON p.account_id = a.id',
        // tier-2: device_id → account
        'parental_locations'  => 'JOIN parental_devices d ON c.device_id = d.id JOIN parental_accounts a ON d.account_id = a.id',
    ];

    public function up(): void
    {
        foreach ($this->joins as $table => $join) {
            DB::statement(
                "UPDATE `{$table}` c {$join} ".
                "SET c.client_isp_id = a.client_isp_id ".
                "WHERE c.client_isp_id IS NULL AND a.client_isp_id IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->joins) as $table) {
            DB::statement("UPDATE `{$table}` SET client_isp_id = NULL");
        }
    }
};
