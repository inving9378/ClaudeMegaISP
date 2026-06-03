<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sub-fase 3.4 — Reglas de notificación con horarios/días como capa ADICIONAL
// (opt-in) sobre las preferencias de 3.3. Si un usuario no tiene reglas activas,
// el comportamiento 3.3 se preserva intacto.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_geofence_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->nullable()->index(); // multi-tenancy
            $table->json('vehicle_ids');     // [] = todos los vehículos del usuario
            $table->json('geofence_ids');    // [] = todas las geocercas
            $table->json('event_types');     // ['enter','exit'] (≥1)
            $table->time('time_from')->nullable(); // null = cualquier hora
            $table->time('time_to')->nullable();   // (time_from > time_to → ventana cruza medianoche)
            $table->json('days_of_week');    // [1..7] ISO (1=lun,7=dom); [] = todos
            $table->json('channels');        // ['email','whatsapp'] subset de los del usuario
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_geofence_rules');
    }
};
