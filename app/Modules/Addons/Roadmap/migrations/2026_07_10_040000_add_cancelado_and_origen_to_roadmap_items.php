<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acciones avanzadas de la bandeja (#320). Aditiva/incremental.
 *  - estado_aprobacion += 'cancelado' (cierre por cancelación, distinto de 'rechazado').
 *  - origen_item_id: item de la Hoja de Ruta que originó este (seguimiento) — trazabilidad.
 * Nullable → no rompe filas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE roadmap_items MODIFY COLUMN estado_aprobacion ENUM('pendiente_revision','aprobado_claude','requiere_irving','rechazado','en_progreso','completado','aprobado_irving','cancelado') NOT NULL DEFAULT 'pendiente_revision'");

        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'origen_item_id')) {
                $table->unsignedBigInteger('origen_item_id')->nullable()->after('opcion_elegida');
                $table->index('origen_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'origen_item_id')) {
                $table->dropIndex(['origen_item_id']);
                $table->dropColumn('origen_item_id');
            }
        });
        DB::statement("ALTER TABLE roadmap_items MODIFY COLUMN estado_aprobacion ENUM('pendiente_revision','aprobado_claude','requiere_irving','rechazado','en_progreso','completado','aprobado_irving') NOT NULL DEFAULT 'pendiente_revision'");
    }
};
