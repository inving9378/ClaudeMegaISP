<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1005 (sub-item de #1003 §1) — escape valve para el gate de cierre: un item SIN pantalla que
 * enlazar (migración, refactor, test) marca `sin_ui=true` + `sin_ui_motivo` (cómo se comprobó en
 * su lugar) y queda exento de traer `enlace_revision`. Sin esto, `ThomasService::verificarCierre()`
 * exigiría un link de UI incluso a items que genuinamente no tienen una. Aditiva + idempotente;
 * down() la elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'sin_ui')) {
                $table->boolean('sin_ui')->default(false);
            }
            if (! Schema::hasColumn('roadmap_items', 'sin_ui_motivo')) {
                $table->text('sin_ui_motivo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'sin_ui_motivo')) {
                $table->dropColumn('sin_ui_motivo');
            }
            if (Schema::hasColumn('roadmap_items', 'sin_ui')) {
                $table->dropColumn('sin_ui');
            }
        });
    }
};
