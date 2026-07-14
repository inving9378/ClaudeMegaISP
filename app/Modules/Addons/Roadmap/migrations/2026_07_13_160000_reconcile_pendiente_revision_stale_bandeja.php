<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #429 — decisión de Irving (opción elegida: "Vaciar la bandeja completa, asumiendo
 * 100% stale"). La bandeja `pendiente_revision` está compuesta de registros legacy: quedaron
 * en estado_aprobacion=pendiente_revision pero su `status` real ya avanzó a done/in_progress
 * (dirección INVERSA a la que corrigió la migración 2026_07_13_120000). Reconcilia
 * estado_aprobacion para que coincida con el status real:
 *   - status=done        → completado (completed_at ya poblado o COALESCE con updated_at)
 *   - status=in_progress  → en_progreso
 * Solo toca pendiente_revision cuyo status YA avanzó — NO toca status=pending (backlog fresco
 * real, si lo hubiera), así que la bandeja queda vacía de stale sin arriesgar trabajo vivo.
 * Idempotente (WHERE excluye lo ya corregido).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roadmap_items')
            ->where('estado_aprobacion', 'pendiente_revision')
            ->where('status', 'done')
            ->update([
                'estado_aprobacion' => 'completado',
                'completed_at'      => DB::raw('COALESCE(completed_at, updated_at)'),
            ]);

        DB::table('roadmap_items')
            ->where('estado_aprobacion', 'pendiente_revision')
            ->where('status', 'in_progress')
            ->update([
                'estado_aprobacion' => 'en_progreso',
            ]);
    }

    public function down(): void
    {
        // No-op a propósito: revertir una corrección de datos (bandeja stale ya reconciliada)
        // volvería a llenar pendiente_revision de basura legacy.
    }
};
