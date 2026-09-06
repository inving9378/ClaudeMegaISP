<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9990403 — el gate de cierre (`JarvisService::verificarCierre()`) exigía `branch` para cualquier
 * cierre, sin distinguir "no hizo el trabajo" (#9990366) de "el trabajo era no-código" (investigación
 * / premisa incorrecta / duplicado ya entregado por otro item). Este campo es la justificación
 * EXPLÍCITA de esa segunda categoría: si un item cierra sin `branch` ni `merge_commit`, debe traer
 * aquí por qué ("cerrado sin rama porque X"), o el gate lo rebota. Aditiva + idempotente; down() la
 * elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'cierre_sin_codigo_motivo')) {
                $table->text('cierre_sin_codigo_motivo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'cierre_sin_codigo_motivo')) {
                $table->dropColumn('cierre_sin_codigo_motivo');
            }
        });
    }
};
