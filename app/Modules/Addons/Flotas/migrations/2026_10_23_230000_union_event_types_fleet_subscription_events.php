<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #620 — deja en fleet_subscription_events.event_type la UNIÓN de los tipos de
 * #621 (block_change_notice) y #620 (promo_started, promo_converted).
 *
 * Antes, cada migración reescribía el ENUM completo con solo sus propios valores, así que
 * la que corría al último borraba los del otro: en dev quedaron fuera los de la promoción
 * (insertarlos falla con STRICT_TRANS_TABLES) y en prod, con el orden por fecha, se habría
 * borrado block_change_notice (500 al registrar una unidad que cambia de bloque).
 * Aditiva: solo agrega valores; ninguno de los existentes se quita.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fleet_subscription_events MODIFY event_type ENUM(
            'created','trial_started','trial_reminder','trial_expiring',
            'activated','billed','payment_failed','vehicles_changed','plan_changed',
            'cancelled','expired','reactivated','block_change_notice',
            'promo_started','promo_converted'
        ) NOT NULL");
    }

    public function down(): void
    {
        // Vacío a propósito: quitar valores del ENUM rompería las filas que ya los usan.
    }
};
