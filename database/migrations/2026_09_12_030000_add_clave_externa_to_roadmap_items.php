<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CIRC-05 pieza A (#9990946) — idempotencia de alta externa de items.
 *
 * `clave_externa` es la clave que el emisor (Cowork) manda para que un reintento de su POST
 * (no puede saber si el anterior llegó) NO duplique el item: `RoadmapIntakeService::crear()`
 * busca por esta columna antes de insertar. NULLABLE + UNIQUE: MySQL permite múltiples NULL en
 * un índice unique, así que los items que no declaran clave_externa (la inmensa mayoría, todo
 * lo interno) conviven sin chocar entre sí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roadmap_items')) {
            return;
        }

        Schema::table('roadmap_items', function (Blueprint $t) {
            if (! Schema::hasColumn('roadmap_items', 'clave_externa')) {
                $t->string('clave_externa', 191)->nullable()->unique()->after('origen_item_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('roadmap_items')) {
            return;
        }

        Schema::table('roadmap_items', function (Blueprint $t) {
            if (Schema::hasColumn('roadmap_items', 'clave_externa')) {
                $t->dropUnique(['clave_externa']);
                $t->dropColumn('clave_externa');
            }
        });
    }
};
