<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1078 — vueltas que terminan sin ningún cambio. Del 23 al 27-oct, 268 de 361 vueltas vacías
 * fueron el mismo item relanzado al minuto siguiente. `vueltas_vacias` cuenta las seguidas y
 * `ultima_vuelta_vacia_at` marca cuándo fue la última: el pool espera 1 h, 4 h y luego 24 h antes
 * de volver a darle el item a una terminal (ver RoadmapItem::sqlElegibleParaPool). Una acción de
 * una persona sobre el item reinicia el conteo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'vueltas_vacias')) {
                $table->unsignedSmallInteger('vueltas_vacias')->default(0);
            }
            if (! Schema::hasColumn('roadmap_items', 'ultima_vuelta_vacia_at')) {
                $table->timestamp('ultima_vuelta_vacia_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach (['vueltas_vacias', 'ultima_vuelta_vacia_at'] as $c) {
                if (Schema::hasColumn('roadmap_items', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
