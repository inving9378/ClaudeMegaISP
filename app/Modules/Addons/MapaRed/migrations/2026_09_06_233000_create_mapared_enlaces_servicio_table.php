<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-14 — enlace_de_servicio: cliente/ONT ↔ puerto de NAP ↔ hilo (aditiva, item roadmap #950).
 *
 * Es la costura entre la planta (mapared_puertos de MR-10, mapared_hilos de MR-11) y el negocio
 * (cliente). Por D19, el cliente se guarda **por nombre** (`cliente_nombre`, más un
 * `cliente_numero_contrato` opcional) y el ONT **por serie** (`ont_serie`, casa con
 * `olt_onus.sn`), NUNCA por `client_id`/PK interno: dev y prod tienen IDs distintos.
 *
 * `puerto_nap_id` y `hilo_id` son enteros sueltos (sin `->constrained()`) apuntando a
 * `mapared_puertos.id`/`mapared_hilos.id` — misma convención ya usada por `mapared_empalmes`
 * (MR-12) de no cruzar FKs reales entre tablas `mapared_*` en evolución paralela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_enlaces_servicio', function (Blueprint $table) {
            $table->id();
            $table->string('cliente_nombre');
            $table->string('cliente_numero_contrato')->nullable();
            $table->string('ont_serie');
            $table->unsignedBigInteger('puerto_nap_id');
            $table->unsignedBigInteger('hilo_id')->nullable();
            $table->date('fecha_alta');
            $table->enum('estado', ['activo', 'suspendido', 'cancelado', 'baja'])->default('activo');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('cliente_nombre');
            $table->index('ont_serie');
            $table->index('puerto_nap_id');
            $table->index('hilo_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_enlaces_servicio');
    }
};
