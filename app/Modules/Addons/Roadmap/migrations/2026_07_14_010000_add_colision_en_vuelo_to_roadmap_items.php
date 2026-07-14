<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #438 — colisión en vuelo entre dos items paralelos con footprint no conocido al despachar.
 * ADITIVA / reversible. `colision_pausada_por` = id del item GANADOR que causó la pausa
 * (mientras no esté null, el item perdedor se autopausa en `circuito:integrar` en vez de
 * mergear). `colision_pausada_at` = cuándo se detectó, solo informativo/depuración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'colision_pausada_por')) {
                $table->unsignedBigInteger('colision_pausada_por')->nullable()->after('worker_sid');
            }
            if (! Schema::hasColumn('roadmap_items', 'colision_pausada_at')) {
                $table->timestamp('colision_pausada_at')->nullable()->after('colision_pausada_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'colision_pausada_at')) {
                $table->dropColumn('colision_pausada_at');
            }
            if (Schema::hasColumn('roadmap_items', 'colision_pausada_por')) {
                $table->dropColumn('colision_pausada_por');
            }
        });
    }
};
