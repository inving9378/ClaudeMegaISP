<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #103 — el celular del conductor puede actuar como dispositivo GPS
 * cuando el vehículo no tiene hardware dedicado (Ruptela/Concox/GT06).
 * Suma 'phone' al enum existente de fleet_devices.brand.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fleet_devices MODIFY COLUMN brand ENUM('ruptela','concox','gt06_generic','mock','phone') NOT NULL DEFAULT 'mock'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fleet_devices MODIFY COLUMN brand ENUM('ruptela','concox','gt06_generic','mock') NOT NULL DEFAULT 'mock'");
    }
};
