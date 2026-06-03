<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sub-fase 3.3 — Notificaciones de eventos de geocerca: preferencias por usuario + log de envíos.
return new class extends Migration
{
    public function up(): void
    {
        // 1. Preferencias: qué usuario recibe qué alertas, de qué vehículos/geocercas, por qué canales.
        Schema::create('fleet_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('vehicle_id')->nullable();   // null = todos los vehículos del tenant
            $table->unsignedBigInteger('geofence_id')->nullable();  // null = todas las geocercas
            $table->json('event_types');                            // ['enter','exit']
            $table->json('channels');                               // ['email','whatsapp'] (+ 'push','sms' a futuro)
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'geofence_id']);
            $table->index('user_id');
        });

        // 2. Log de auditoría de cada envío (un row por usuario × canal × evento).
        Schema::create('fleet_notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fleet_geofence_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('channel', ['email', 'whatsapp', 'push', 'sms']);
            $table->string('destination')->nullable();              // email o teléfono al que se envió
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_notification_log');
        Schema::dropIfExists('fleet_notification_preferences');
    }
};
