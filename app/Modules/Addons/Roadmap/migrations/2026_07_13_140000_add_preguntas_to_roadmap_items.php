<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #432 Fase 3 — Brief COMPLETO: un item puede tener VARIAS preguntas de decisión, cada una con sus
 * opciones. Columna JSON aditiva `preguntas` (NO reemplaza `opciones`/`opcion_elegida`, que quedan
 * como fallback tras el feature flag `circuito.multi_pregunta`). Forma de cada entrada:
 *   { id, pregunta, opciones:[{clave,texto,recomendada}], opcion_elegida, fase }
 * Aditiva + idempotente (guard hasColumn); down() la elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'preguntas')) {
                $table->json('preguntas')->nullable()->after('opcion_elegida');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'preguntas')) {
                $table->dropColumn('preguntas');
            }
        });
    }
};
