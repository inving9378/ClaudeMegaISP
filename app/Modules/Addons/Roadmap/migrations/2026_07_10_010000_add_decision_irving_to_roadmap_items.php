<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bandeja de decisiones interactiva (#313). Aditiva/incremental (NO migrate:fresh).
 *  - (C) Suma 'aprobado_irving' al ENUM estado_aprobacion (aprobación HUMANA, luz verde
 *    para B/C). Preserva NOT NULL + default 'pendiente_revision'.
 *  - opciones (JSON nullable): forks/opciones que el decisor deja para que Irving elija.
 *  - opcion_elegida (string nullable): la opción que Irving escogió.
 * Nullable → no rompe filas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE roadmap_items MODIFY COLUMN estado_aprobacion ENUM('pendiente_revision','aprobado_claude','requiere_irving','rechazado','en_progreso','completado','aprobado_irving') NOT NULL DEFAULT 'pendiente_revision'");

        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'opciones')) {
                $table->json('opciones')->nullable()->after('comentarios_claude');
            }
            if (! Schema::hasColumn('roadmap_items', 'opcion_elegida')) {
                $table->string('opcion_elegida')->nullable()->after('opciones');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'opcion_elegida')) {
                $table->dropColumn('opcion_elegida');
            }
            if (Schema::hasColumn('roadmap_items', 'opciones')) {
                $table->dropColumn('opciones');
            }
        });

        // Reasignar items en aprobado_irving antes de revertir el enum si los hubiera.
        DB::statement("ALTER TABLE roadmap_items MODIFY COLUMN estado_aprobacion ENUM('pendiente_revision','aprobado_claude','requiere_irving','rechazado','en_progreso','completado') NOT NULL DEFAULT 'pendiente_revision'");
    }
};
