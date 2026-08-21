<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #921 — un item puede tener fecha futura (`agendado_para`) sin usar `excluir_pool_automatico`,
 * que es un master switch compartido por 6 mecanismos distintos (ver `RoadmapItem::sqlElegibleParaPool`).
 * `sqlElegibleParaPool()` excluye del pool cualquier item con `agendado_para` en el futuro;
 * `circuito:reactivar-agendados` (diario) limpia el campo cuando la fecha ya llegó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'agendado_para')) {
                $table->dateTime('agendado_para')->nullable()->after('excluir_pool_automatico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'agendado_para')) {
                $table->dropColumn('agendado_para');
            }
        });
    }
};
