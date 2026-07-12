<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma del worker (#334 parte A): qué slot del equipo (wt-1..wt-N) reclamó/ejecutó el item.
 * Se sella en el RECLAMO atómico (scheduler + pool continuo) → traceable en el item, la rejilla de
 * Terminales y la vista de Integración ("ejecutado por wt-3"). Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (! Schema::hasColumn('roadmap_items', 'worker_sid')) {
                $t->string('worker_sid', 16)->nullable()->after('en_desarrollo_humano');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (Schema::hasColumn('roadmap_items', 'worker_sid')) {
                $t->dropColumn('worker_sid');
            }
        });
    }
};
