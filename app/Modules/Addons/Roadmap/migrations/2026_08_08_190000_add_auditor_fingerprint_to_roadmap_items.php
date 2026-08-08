<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOTOR DE AUDITORÍA CONTINUA (#559) — huella de dedup.
 *
 * Por qué una columna y no un marcador en el título: el motor debe poder comparar contra items
 * ABIERTOS **y CERRADOS** (si ya cerramos "hueco ruteado en BoxInputController@update", no se
 * vuelve a crear jamás). Un marcador en el título ensucia la Torre y se pierde cuando alguien
 * renombra el item; una columna indexada sobrevive al renombrado y al cierre.
 *
 * Aditiva y reversible: nullable, sin default, sin backfill. Los items existentes quedan con NULL
 * (= "no lo creó el auditor"), que es exactamente lo correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roadmap_items')) {
            return;
        }
        if (Schema::hasColumn('roadmap_items', 'auditor_fingerprint')) {
            return;
        }

        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->string('auditor_fingerprint', 64)->nullable()->after('origen_item_id');
            $table->index('auditor_fingerprint', 'roadmap_items_auditor_fingerprint_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roadmap_items') || ! Schema::hasColumn('roadmap_items', 'auditor_fingerprint')) {
            return;
        }

        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->dropIndex('roadmap_items_auditor_fingerprint_idx');
            $table->dropColumn('auditor_fingerprint');
        });
    }
};
