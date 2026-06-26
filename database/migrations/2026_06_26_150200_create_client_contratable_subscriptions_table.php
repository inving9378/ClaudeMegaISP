<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios Contratables — FASE 1 (suscripción del cliente).
 *
 * `client_contratable_subscriptions` = la suscripción de un cliente a un servicio
 * contratable. Es la "instancia" (análoga a ClientCustomService) que en Fase 2
 * expondrá el interface del motor. Aislamiento por cliente vía `client_id` +
 * BelongsToClientTenant (patrón estándar #1). Una suscripción por (cliente, servicio).
 *
 * Las dos vías de activación (cliente/admin) conviven sobre la MISMA fila
 * (`activated_by` distingue el origen). Suspensión: solo admin (lógica de Fase 2).
 *
 * Aditiva y reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contratable_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();
            $table->foreignId('contratable_service_id')
                ->constrained('contratable_services')
                ->cascadeOnDelete();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedSmallInteger('trial_invoices_remaining')->nullable(); // prueba gratis restante
            $table->enum('activated_by', ['client', 'admin'])->default('client');  // origen de la activación
            $table->unsignedBigInteger('created_by')->nullable();                  // user admin si aplica
            $table->timestamps();
            $table->softDeletes();

            // Una suscripción por (cliente, servicio); cubre también el lookup por client_id (prefijo).
            $table->unique(['client_id', 'contratable_service_id'], 'client_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contratable_subscriptions');
    }
};
