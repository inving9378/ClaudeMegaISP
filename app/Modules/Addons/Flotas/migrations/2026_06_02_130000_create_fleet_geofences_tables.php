<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sub-fase 3.1 — Geocercas: definición de polígonos + pivot de vehículos asignados.
// NOTA: la detección automática entrada/salida (punto-en-polígono) es Sub-fase 3.2; aquí solo CRUD + visual.
return new class extends Migration
{
    public function up(): void
    {
        // 1. Geocercas (polígono + metadatos)
        Schema::create('fleet_geofences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->index(); // null = flota interna Meganet
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['enter', 'exit', 'both'])->default('both');
            $table->json('polygon');                       // array de [lat, lng] formando polígono cerrado
            $table->string('color', 9)->default('#3388ff'); // color de visualización
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('active');
        });

        // 2. Pivot geocerca ⇄ vehículo (a qué vehículos aplica cada geocerca)
        Schema::create('fleet_geofence_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geofence_id')->constrained('fleet_geofences')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['geofence_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_geofence_vehicles');
        Schema::dropIfExists('fleet_geofences');
    }
};
