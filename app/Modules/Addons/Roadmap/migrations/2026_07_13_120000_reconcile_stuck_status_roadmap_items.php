<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #420 (DEUDA circuito): items con estado_aprobacion=completado pero status=pending
 * inflan el contador "Pendientes" de la Torre (RoadmapTab.vue cuenta por `status`, no por
 * `estado_aprobacion`). Reconcilia SOLO status/completed_at — estado_aprobacion (autoritativo)
 * no se toca. Idempotente (WHERE excluye lo ya corregido).
 *
 * Excluye el #417 a propósito: su comentario lo marca contradicho por el #418 (bundle sin
 * mergear) — ese caso requiere revisión aparte, no reconciliación ciega.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roadmap_items')
            ->where('estado_aprobacion', 'completado')
            ->where('status', '!=', 'done')
            ->where('id', '!=', 417)
            ->update([
                'status'       => 'done',
                'completed_at' => DB::raw('COALESCE(completed_at, updated_at)'),
            ]);
    }

    public function down(): void
    {
        // No-op a propósito: revertir una corrección de datos (status coherente con
        // estado_aprobacion ya cerrado) no tiene sentido — dejaría los items otra vez colgados.
    }
};
