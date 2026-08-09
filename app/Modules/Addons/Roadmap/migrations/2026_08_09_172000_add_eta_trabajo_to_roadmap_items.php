<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #546 — Reloj en regresión por terminal. ADITIVA / reversible.
 *
 * `trabajo_iniciado_at` = cuándo el terminal EMPEZÓ a trabajar el item (distinto de `claimed_at`,
 * que es el lease del pool; y de `started_at`, campo legado del flujo de subtasks, no wireado al
 * claim). `eta_segundos` = estimación de duración sellada en ese momento (histórico o bucket).
 * `eta_metodo` = 'historico'|'heuristico', para transparencia en la UI (tooltip).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'trabajo_iniciado_at')) {
                $table->timestamp('trabajo_iniciado_at')->nullable()->after('claimed_at');
            }
            if (! Schema::hasColumn('roadmap_items', 'eta_segundos')) {
                $table->integer('eta_segundos')->nullable()->after('trabajo_iniciado_at');
            }
            if (! Schema::hasColumn('roadmap_items', 'eta_metodo')) {
                $table->string('eta_metodo', 20)->nullable()->after('eta_segundos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach (['eta_metodo', 'eta_segundos', 'trabajo_iniciado_at'] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
