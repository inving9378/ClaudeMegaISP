<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #620 — Promoción de 3 meses gratis en Flotas. Aditiva: columna is_promo
 * (la promo sigue siendo status 'trial', así nada del acceso/UI existente cambia).
 * Los dos tipos de evento nuevos (promo_started, promo_converted) los agrega
 * 2026_10_23_230000_union_event_types_fleet_subscription_events, junto con los de #621.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fleet_subscriptions', 'is_promo')) {
            Schema::table('fleet_subscriptions', function (Blueprint $table) {
                $table->boolean('is_promo')->default(false)->after('trial_notified_days');
            });
        }
    }

    public function down(): void
    {
        // Vacío a propósito: no se borran datos de promoción ya registrados.
    }
};
