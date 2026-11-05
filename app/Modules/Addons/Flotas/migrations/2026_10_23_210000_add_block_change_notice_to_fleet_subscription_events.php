<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El aviso de cambio de bloque (#619/#621) registra el evento `block_change_notice`,
 * que no existía en el enum → 500 al registrar una unidad que cambia de bloque.
 * Aditiva: solo AGREGA el valor al enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fleet_subscription_events MODIFY event_type ENUM('created','trial_started','trial_reminder','trial_expiring','activated','billed','payment_failed','vehicles_changed','plan_changed','cancelled','expired','reactivated','block_change_notice') NOT NULL");
    }

    public function down(): void
    {
        // Vacío a propósito: quitar el valor fallaría si ya hay filas con él.
    }
};
