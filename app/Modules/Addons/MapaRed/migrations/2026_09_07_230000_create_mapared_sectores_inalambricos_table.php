<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-26 Fase 3 (item roadmap #9990524) — sectores inalámbricos (AP/torre sectorizada):
 * azimut/apertura/alcance/altura. Entidad NUEVA y aditiva, no reutiliza
 * mapared_layers/dialog='region' (ver #9990494, que investigó y descartó 'region' como
 * concepto de polígono de cobertura). D29: sin simulador de propagación RF — el frontend
 * (Fase 4) dibuja el cono azimut+apertura+alcance sobre el mapa, sin cálculo real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_sectores_inalambricos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('azimut_grados', 5, 2);
            $table->decimal('apertura_grados', 5, 2);
            $table->unsignedInteger('alcance_metros');
            $table->decimal('altura_metros', 6, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_sectores_inalambricos');
    }
};
