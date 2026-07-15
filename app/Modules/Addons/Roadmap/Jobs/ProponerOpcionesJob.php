<?php

namespace App\Modules\Addons\Roadmap\Jobs;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * #437 — Gate de completitud (Fase 3, pieza 1), opción async elegida por Irving: al escalar un item
 * a la bandeja (requiere_irving) SIN brief todavía, se encola ESTE job en background para poblar
 * `preguntas` (multi-pregunta) con el mismo motor que usa `circuito:proponer-opciones`. No bloquea
 * el guardado del estado ni acopla RevisorService a una llamada síncrona a la IA. Falla-segura: si
 * la IA no responde o el item cambió mientras esperaba en cola, no escribe nada (la Torre sigue
 * mostrando el semáforo "brief pendiente" y el ejecutor/Irving pueden regenerarlo a mano).
 */
class ProponerOpcionesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $itemId)
    {
    }

    public function handle(RevisorService $revisor): void
    {
        if (! RoadmapItem::multiPreguntaEnabled()) {
            return;
        }

        $item = RoadmapItem::find($this->itemId);
        if (! $item || $item->estado_aprobacion !== 'requiere_irving' || ! empty($item->preguntas)) {
            return;   // ya no aplica: se re-aprobó, se cerró, o alguien más ya escribió el brief
        }

        $r     = $revisor->proponerPreguntas($item);
        $pregs = $r['preguntas'] ?? [];
        if (empty($pregs)) {
            Log::channel('roadmap_externo')->warning('proponer-opciones-async: sin brief utilizable', [
                'item' => $this->itemId, 'error' => $r['error'] ?? null,
            ]);

            return;
        }

        // Re-chequeo fresco antes de escribir (el item pudo cambiar mientras esperaba en cola).
        $fresh = RoadmapItem::find($this->itemId);
        if (! $fresh || $fresh->estado_aprobacion !== 'requiere_irving' || ! empty($fresh->preguntas)) {
            return;
        }

        $revisor->aplicarPreguntas($fresh, $pregs, 'revisor:brief-async(#437)');
        Log::channel('roadmap_externo')->info('proponer-opciones-async: brief escrito', [
            'item' => $this->itemId, 'n' => count($pregs), 'modelo' => $r['modelo'] ?? null,
        ]);
    }
}
