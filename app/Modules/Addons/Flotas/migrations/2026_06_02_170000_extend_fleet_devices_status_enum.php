<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Sub-fase 2.3a — añade estados para el ciclo de vida del GPS real:
//   pending_first_connection = dado de alta en la UI, esperando que el dispositivo se conecte por primera vez
//   unregistered             = un dispositivo se conectó con un IMEI que no está vinculado a ningún vehículo
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fleet_devices MODIFY COLUMN status
            ENUM('active','inactive','no_signal','unregistered','pending_first_connection')
            NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        // Normaliza los estados nuevos antes de revertir el enum.
        DB::statement("UPDATE fleet_devices SET status='inactive'
            WHERE status IN ('unregistered','pending_first_connection')");
        DB::statement("ALTER TABLE fleet_devices MODIFY COLUMN status
            ENUM('active','inactive','no_signal') NOT NULL DEFAULT 'active'");
    }
};
