<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de ejecuciones del ejecutor on-box del Circuito (#319). Aditiva.
 * Una fila por vuelta del cron: timing, modo, qué tocó, cuántas propuestas/decisiones,
 * si ejecutó o solo propuso. Alimenta el panel "Ejecuciones" de la Torre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('circuito_ejecuciones')) {
            return;
        }
        Schema::create('circuito_ejecuciones', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duracion_seg')->nullable();
            $table->string('modo', 20)->default('aviso_previo');
            $table->boolean('pausado')->default(false);
            $table->integer('rc')->nullable();
            $table->json('items_tocados')->nullable();
            $table->integer('n_propuestas')->default(0);
            $table->integer('n_decisiones')->default(0);
            $table->boolean('ejecuto')->default(false);
            $table->text('resumen')->nullable();
            $table->string('log_path')->nullable();
            $table->timestamps();
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuito_ejecuciones');
    }
};
