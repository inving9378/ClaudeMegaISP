<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-24 (item roadmap #960, Fase 1) — `mapared_correlativos`: contador atómico por
 * zona+tipo para la nomenclatura automática de D24 (`NAP-{ZONA}-{N}` / `T-{ZONA}-FO{HILOS}-{N}`).
 *
 * Se persiste el ÚLTIMO correlativo entregado (no se calcula por MAX/parseo de nombres
 * existentes) para garantizar "sin huecos" bajo concurrencia: el incremento se hace dentro de
 * una transacción con `lockForUpdate()` sobre la fila zona+tipo (ver `NomenclaturaService`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mapared_correlativos')) {
            Schema::create('mapared_correlativos', function (Blueprint $table) {
                $table->id();
                $table->string('zona');
                $table->string('tipo');
                $table->unsignedInteger('ultimo')->default(0);
                $table->timestamps();

                $table->unique(['zona', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_correlativos');
    }
};
