<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciclo de vida + clasificación UI/backend de los items del Circuito (#334):
 * - revision_ui: true = verificable por UI (tocó .vue/.blade/CSS/resources-js, efecto visible) →
 *   va a la cola "Para revisar (visual)". false = backend/interno (sin efecto visible) → NO pide
 *   revisión visual; se auto-archiva tras el merge. null = sin clasificar aún.
 * - ui_hint: nota de qué cambió / dónde mirarlo / qué probar (solo para los UI).
 * - archivado_at/por: fuera del radar activo, pero consultable/reversible en el Historial.
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (! Schema::hasColumn('roadmap_items', 'revision_ui')) {
                $t->boolean('revision_ui')->nullable()->after('marcado_version');
            }
            if (! Schema::hasColumn('roadmap_items', 'ui_hint')) {
                $t->text('ui_hint')->nullable()->after('revision_ui');
            }
            if (! Schema::hasColumn('roadmap_items', 'archivado_at')) {
                $t->timestamp('archivado_at')->nullable()->after('ui_hint');
            }
            if (! Schema::hasColumn('roadmap_items', 'archivado_por')) {
                $t->string('archivado_por')->nullable()->after('archivado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            foreach (['revision_ui', 'ui_hint', 'archivado_at', 'archivado_por'] as $c) {
                if (Schema::hasColumn('roadmap_items', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
