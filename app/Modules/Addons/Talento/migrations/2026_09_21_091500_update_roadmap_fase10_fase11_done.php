<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cierra en la Hoja de Ruta interna de Talento (talento_roadmap_items) las
 * Fases 10 y 11 tras corregirlas: FieldFlowService y WarrantyWindowService ya
 * resuelven dual-source (work_order/task). Detalle completo en
 * docs/bitacora/2026-09-21-talento-roadmap-fase10-fieldflow-dual-source.md y
 * docs/bitacora/2026-09-21-talento-roadmap-fase11-warranty-window-tasks.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('talento_roadmap_items')->where('phase', 10)->update([
            'status'     => 'done',
            'notes'      => 'Resuelto 2026-09-21: FieldFlowService::accept/confirmActivation/onboard ya resuelven dual-source (work_order/task); /firma ya estaba resuelto de antes. Custodia de módem se omite para tasks (mismo guard condicional que ya existía). Detalle: docs/bitacora/2026-09-21-talento-roadmap-fase10-fieldflow-dual-source.md',
            'updated_at' => $now,
        ]);

        DB::table('talento_roadmap_items')->where('phase', 11)->update([
            'status'     => 'done',
            'notes'      => 'Resuelto 2026-09-21: WarrantyWindowService::refreshWindow() reescrito con parámetros desacoplados, acepta tasks. Detalle: docs/bitacora/2026-09-21-talento-roadmap-fase11-warranty-window-tasks.md',
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('talento_roadmap_items')->whereIn('phase', [10, 11])->update(['status' => 'backlog', 'updated_at' => now()]);
    }
};
