<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios Contratables — FASE 1 (paquetes por rango).
 *
 * `contratable_packages` = los paquetes de un servicio, cada uno cubre un RANGO de
 * unidades ([rango_min, rango_max]) con un precio fijo mensual. `rango_max` NULL =
 * paquete sin tope superior (el "techo" que absorbe el exceso → auto-ajuste).
 *
 * La validación de "rangos sin solaparse dentro del mismo servicio" se enforza en
 * la capa de aplicación (Fase 3 / validador), no como constraint de BD.
 *
 * Aditiva y reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratable_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contratable_service_id')
                ->constrained('contratable_services')
                ->cascadeOnDelete();
            $table->string('nombre');                          // "1 vehículo", "2-4 vehículos"
            $table->unsignedInteger('rango_min');
            $table->unsignedInteger('rango_max')->nullable();  // NULL = sin tope superior
            $table->decimal('precio', 10, 2);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['contratable_service_id', 'rango_min']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratable_packages');
    }
};
