<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #561 — ADITIVA / reversible. Contador de reclamos fallidos (worker muerto/colgado con el item
 * en_progreso) para que el reaper pueda re-encolar automáticamente en vez de escalar directo a la
 * bandeja de Irving, y solo escale tras superar el tope (`circuito.reaper.max_reintentos`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'reap_count')) {
                $table->unsignedInteger('reap_count')->default(0)->after('claimed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'reap_count')) {
                $table->dropColumn('reap_count');
            }
        });
    }
};
