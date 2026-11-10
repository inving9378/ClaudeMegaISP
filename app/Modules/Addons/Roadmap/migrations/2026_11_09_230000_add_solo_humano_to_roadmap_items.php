<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #1230 — candado `solo_humano` F1: columnas aditivas. Aditiva y reversible. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (! Schema::hasColumn('roadmap_items', 'solo_humano')) {
                $t->boolean('solo_humano')->default(false);
            }
            if (! Schema::hasColumn('roadmap_items', 'solo_humano_motivo')) {
                $t->string('solo_humano_motivo', 500)->nullable();
            }
            if (! Schema::hasColumn('roadmap_items', 'solo_humano_por')) {
                $t->string('solo_humano_por', 100)->nullable();
            }
            if (! Schema::hasColumn('roadmap_items', 'solo_humano_at')) {
                $t->timestamp('solo_humano_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            foreach (['solo_humano', 'solo_humano_motivo', 'solo_humano_por', 'solo_humano_at'] as $c) {
                if (Schema::hasColumn('roadmap_items', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
