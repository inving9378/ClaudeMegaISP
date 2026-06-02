<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sub-fase 2.1 — Tracking GPS: cabecera del dispositivo, pings de posición y eventos.
return new class extends Migration
{
    public function up(): void
    {
        // 1. Dispositivos GPS (cabecera)
        Schema::create('fleet_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained('fleet_vehicles')->nullOnDelete();
            $table->string('imei', 20)->unique();
            $table->enum('brand', ['ruptela', 'concox', 'gt06_generic', 'mock'])->default('mock');
            $table->string('model')->nullable();
            $table->string('sim_number', 20)->nullable();
            $table->string('sim_carrier', 50)->nullable();
            $table->enum('status', ['active', 'inactive', 'no_signal'])->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('total_pings')->default(0);
            $table->timestamp('installed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // 2. Posiciones (cada ping del GPS) — tabla de alto volumen
        Schema::create('fleet_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('fleet_devices')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('speed', 5, 2)->default(0);     // km/h
            $table->decimal('heading', 5, 2)->default(0);   // grados 0-360
            $table->decimal('altitude', 7, 2)->nullable();  // metros
            $table->unsignedTinyInteger('satellites')->nullable();
            $table->decimal('hdop', 4, 2)->nullable();
            $table->boolean('ignition')->nullable();
            $table->decimal('battery', 5, 2)->nullable();
            $table->timestamp('recorded_at');               // timestamp del GPS
            $table->timestamp('received_at')->useCurrent(); // timestamp del servidor
            $table->softDeletes();

            // Índice compuesto para historial rápido (vehicle_id + recorded_at)
            $table->index(['vehicle_id', 'recorded_at']);
            $table->index('device_id');
        });

        // 3. Eventos especiales del dispositivo
        Schema::create('fleet_device_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('fleet_devices')->cascadeOnDelete();
            $table->enum('event_type', [
                'ignition_on', 'ignition_off', 'sos', 'low_battery',
                'harsh_brake', 'harsh_acceleration', 'over_speed',
                'geofence_enter', 'geofence_exit', 'no_signal', 'back_online',
            ]);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['vehicle_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_device_events');
        Schema::dropIfExists('fleet_positions');
        Schema::dropIfExists('fleet_devices');
    }
};
