<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Segunda pasada de backfill: cubre ONUs cuyo cliente tiene exactamente
     * UN servicio en estado 'Pendiente' (omitidos por la migración original
     * que solo buscaba estados Activo/Activado/active).
     *
     * También re-corre estados Activo para ONUs añadidas después del backfill
     * original (junio 2026). Ambos casos exigen HAVING COUNT = 1 para garantizar
     * asignación unívoca.
     */
    public function up(): void
    {
        $allStates = ['Activo', 'Activado', 'activo', 'active', 'Active', 'Pendiente'];
        $placeholders = implode(',', array_fill(0, count($allStates), '?'));

        $updated = DB::affectingStatement("
            UPDATE olt_onus o
            JOIN (
                SELECT o2.id AS onu_id, MIN(c.id) AS svc_id
                FROM olt_onus o2
                INNER JOIN client_internet_services c ON c.client_id = o2.client_id
                WHERE o2.client_id IS NOT NULL
                  AND o2.service_id IS NULL
                  AND c.estado IN ({$placeholders})
                GROUP BY o2.id
                HAVING COUNT(c.id) = 1
            ) backfill ON backfill.onu_id = o.id
            SET o.service_id = backfill.svc_id
        ", $allStates);

        \Illuminate\Support\Facades\Log::info(
            "[GR-2 backfill-2] service_id poblado en {$updated} filas adicionales de olt_onus."
        );
    }

    public function down(): void
    {
        // No-op intencional — igual que la migración original.
    }
};
