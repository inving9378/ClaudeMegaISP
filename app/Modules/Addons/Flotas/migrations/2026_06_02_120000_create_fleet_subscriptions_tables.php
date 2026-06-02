<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 6 — Comercialización SaaS: suscripción de Flotas por cliente ISP + bitácora de eventos.
// Modelo freemium BYOD (ver CLAUDE.md item #65, Sesión 5):
//   - gestion_plus           (sin GPS):  6 meses gratis → 49  MXN/veh/mes
//   - gestion_plus_tracking  (con GPS):  3 meses gratis → 149 MXN/veh/mes
//   - empresa                          : 1 mes gratis  → 299 MXN/veh/mes
return new class extends Migration
{
    public function up(): void
    {
        // 1. Suscripción de flotas (una por cliente ISP)
        Schema::create('fleet_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index(); // cliente ISP dueño de la suscripción
            $table->string('plan', 40)->default('gestion_plus'); // gestion_plus | gestion_plus_tracking | empresa
            $table->enum('status', ['trial', 'active', 'past_due', 'cancelled', 'expired'])->default('trial');

            // Trial
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->unsignedSmallInteger('trial_notified_days')->nullable(); // último umbral notificado (30/15/7/1)

            // Ciclo de vida
            $table->timestamp('started_at')->nullable();   // cuando pasó a active (pagado)
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            // Precio
            $table->unsignedInteger('vehicles_count')->default(0);
            $table->decimal('price_per_vehicle', 8, 2)->default(0);
            $table->decimal('monthly_price', 10, 2)->default(0); // vehicles_count * price_per_vehicle

            // Facturación (integración módulo Pagos)
            $table->date('next_billing_date')->nullable();
            $table->date('last_billed_at')->nullable();
            $table->boolean('auto_renew')->default(true);

            // Retención de datos tras expirar (90 días)
            $table->date('data_retention_until')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('next_billing_date');
        });

        // 2. Bitácora de eventos de suscripción (auditoría + base para analytics de churn)
        Schema::create('fleet_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('fleet_subscriptions')->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->index();
            $table->enum('event_type', [
                'created', 'trial_started', 'trial_reminder', 'trial_expiring',
                'activated', 'billed', 'payment_failed',
                'vehicles_changed', 'plan_changed',
                'cancelled', 'expired', 'reactivated',
            ]);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->index(['client_id', 'occurred_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_subscription_events');
        Schema::dropIfExists('fleet_subscriptions');
    }
};
