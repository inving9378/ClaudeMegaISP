<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rellena olt_onus.service_id para las ONUs cuyo cliente tiene
     * exactamente UN servicio de internet activo — inferencia unívoca.
     *
     * ONUs con 0 servicios activos o sin client_id quedan en NULL (correcto).
     * Clientes con 2+ servicios activos: 0 casos en el dataset actual.
     */
    public function up(): void
    {
        $states = ['Activo', 'Activado', 'activo', 'active', 'Active'];
        $placeholders = implode(',', array_fill(0, count($states), '?'));

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
        ", $states);

        \Illuminate\Support\Facades\Log::info(
            "[GR-2 backfill] service_id poblado en {$updated} filas de olt_onus."
        );
    }

    public function down(): void
    {
        // El backfill no se revierte automáticamente — se perdería la asignación manual
        // que pueda haberse hecho después. No-op intencional.
    }
};
