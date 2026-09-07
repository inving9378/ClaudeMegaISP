<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-23 fase 4a (item #9990518) — enlace_trazado: par de nodos del mapa (ambos
 * SIEMPRE registros de `mapared_layers`, el modelo unificado de "elementos del mapa")
 * conectados por la acción "Trazar". Ambas columnas apuntan directo a `mapared_layers.id`
 * (sin polimorfismo, sin tabla de nodos nueva — decisión ya tomada en #9990451).
 *
 * Sin FK real hacia `mapared_layers` — misma convención ya establecida por
 * `mapared_empalmes`/`mapared_puertos`/`mapared_enlaces_servicio` de no cruzar FKs reales
 * entre tablas `mapared_*` en evolución paralela (evita acoplar el orden de migración).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_enlaces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nodo_origen_id');
            $table->unsignedBigInteger('nodo_destino_id');
            $table->enum('tipo', ['fibra', 'inalambrico', 'cobre'])->default('fibra');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();

            $table->index('nodo_origen_id');
            $table->index('nodo_destino_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_enlaces');
    }
};
