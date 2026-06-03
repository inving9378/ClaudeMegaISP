<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sub-fase 3.2 — Detección automática: bitácora de entradas/salidas de geocercas.
// (No existía en 3.1; el pre-requisito asumía que sí. Se crea aquí.)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_geofence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('geofence_id')->constrained('fleet_geofences')->cascadeOnDelete();
            $table->enum('event_type', ['enter', 'exit']);
            $table->foreignId('position_id')->nullable()->constrained('fleet_positions')->nullOnDelete();
            $table->timestamp('occurred_at');               // timestamp de la posición que disparó el evento
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vehicle_id', 'occurred_at']);
            $table->index('geofence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_geofence_events');
    }
};
