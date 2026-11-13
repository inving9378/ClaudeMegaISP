<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1202 (Fase D de planta externa): al cerrar la orden el técnico anota cuánto usó de cada material
 * del checklist de la plantilla. Irving (11-nov): «se van a capturar esos datos, ya sea de fibras, de
 * todo lo que se consumió», para después descontarlo de lo que tiene en custodia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_work_order_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('talento_work_order_checklist_items', 'cantidad_usada')) {
                $table->decimal('cantidad_usada', 10, 2)->nullable()->after('unidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_work_order_checklist_items', function (Blueprint $table) {
            if (Schema::hasColumn('talento_work_order_checklist_items', 'cantidad_usada')) {
                $table->dropColumn('cantidad_usada');
            }
        });
    }
};
