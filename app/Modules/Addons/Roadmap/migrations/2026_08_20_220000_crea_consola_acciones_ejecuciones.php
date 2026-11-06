<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #876 — Catálogo de acciones de la Consola: el registro de auditoría.
 *
 * Toda acción que pasa por `ConsolaAccionExecutor` deja una fila aquí, sin excepción y sin
 * opción de apagarlo por acción (regla 2 del item). Tabla nueva y aislada: las acciones de
 * consola no siempre están ligadas a un `roadmap_items` (ej. pausar el circuito), así que no
 * encajan en el `log` json de ese modelo.
 *
 * ADITIVA, sin FK (mismo criterio que el resto del módulo: `actor` guarda la etiqueta
 * "irving:login_user", no un id de otra base — los ids no son estables entre entornos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consola_acciones_ejecuciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 128)->index();
            $table->string('actor', 191)->nullable();
            $table->json('payload')->nullable();
            $table->boolean('ok');
            $table->text('mensaje')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consola_acciones_ejecuciones');
    }
};
