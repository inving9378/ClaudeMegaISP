<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\RoadmapItemRespuesta;
use Illuminate\Support\Collection;

/**
 * CIRC-02c Fase 1 — watchdog del hilo de respuestas (`roadmap_item_respuestas`, CIRC-02b).
 *
 * Dos señales distintas de "algo no se está consumiendo":
 *  (a) una respuesta con `ejecutar=true` lleva >60 min sin `consumida_at`, mientras el circuito
 *      está corriendo (scheduler latiendo hace <5 min) — una terminal debió tomarla y no lo hizo.
 *  (b) un item `requiere_irving` lleva >7 días sin ninguna respuesta — nadie lo ha contestado.
 *
 * NO es el `WatchdogService` existente (salud de workers/scheduler, contexto distinto — solo
 * coincide el nombre).
 */
class RespuestasWatchdogService
{
    /** "Circuito corriendo" = el scheduler latió hace menos de 5 minutos (criterio propio, q2). */
    private const SCHEDULER_VIVO_SECS = 300;

    public function __construct(private readonly RoadmapCircuitoService $circuito)
    {
    }

    /** Respuestas ejecutables sin consumir hace más de 60 min, con el circuito corriendo. */
    public function pendientes60min(): Collection
    {
        $beat = $this->circuito->schedulerBeatSecs();
        if ($beat === null || $beat >= self::SCHEDULER_VIVO_SECS) {
            return collect();
        }

        return RoadmapItemRespuesta::sinConsumir()
            ->where('ejecutar', true)
            ->where('created_at', '<=', now()->subMinutes(60))
            ->with('item:id,title')
            ->get()
            ->map(fn (RoadmapItemRespuesta $r) => [
                'item_id' => $r->item_id,
                'title'   => $r->item?->title,
                'autor'   => $r->autor,
                'cuerpo'  => $r->cuerpo,
                'minutos' => (int) $r->created_at->diffInMinutes(now()),
            ])
            ->values();
    }

    /** Items `requiere_irving` sin ninguna respuesta en 7+ días (o nunca revisados). */
    public function sinRespuesta7dias(): Collection
    {
        return RoadmapItem::where('estado_aprobacion', 'requiere_irving')
            ->whereDoesntHave('respuestas')
            ->where(fn ($q) => $q->whereNull('revisado_at')->orWhere('revisado_at', '<=', now()->subDays(7)))
            ->get(['id', 'title', 'revisado_at', 'created_at'])
            ->map(fn (RoadmapItem $i) => [
                'item_id' => $i->id,
                'title'   => $i->title,
                'dias'    => (int) ($i->revisado_at ?? $i->created_at)->diffInDays(now()),
            ])
            ->values();
    }

    /** Shape completo del endpoint GET /api/roadmap/torre/watchdog-respuestas. */
    public function resumen(): array
    {
        $pendientes60min   = $this->pendientes60min();
        $sinRespuesta7dias = $this->sinRespuesta7dias();

        return [
            'pendientes_60min'    => $pendientes60min,
            'sin_respuesta_7dias' => $sinRespuesta7dias,
            'contador_pendientes' => $pendientes60min->count() + $sinRespuesta7dias->count(),
        ];
    }
}
