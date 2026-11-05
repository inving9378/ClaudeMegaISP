<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoOrderEvent;
use Illuminate\Support\Collection;

/**
 * Único punto de escritura y consulta del historial de eventos de orden (UNI-T E2).
 * Quien necesite registrar un evento llama a registrar(); nadie inserta en
 * talento_order_events por otra vía. D2 lo invoca desde transicionar().
 */
class TalentoOrderEventRecorder
{
    public static function registrar(
        string $origen,
        int $ordenId,
        string $evento,
        ?string $estadoDe = null,
        ?string $estadoA = null,
        ?int $actorId = null,
        ?string $motivo = null,
        ?array $datos = null
    ): TalentoOrderEvent {
        return TalentoOrderEvent::create([
            'origen'    => $origen,
            'orden_id'  => $ordenId,
            'evento'    => $evento,
            'estado_de' => $estadoDe,
            'estado_a'  => $estadoA,
            'actor_id'  => $actorId,
            'motivo'    => $motivo !== null ? mb_substr($motivo, 0, 500) : null,
            'datos'     => $datos,
        ]);
    }

    /** Eventos de una orden, del más antiguo al más reciente (ficha y app). */
    public static function deOrden(string $origen, int $ordenId): Collection
    {
        return TalentoOrderEvent::deOrden($origen, $ordenId)->get();
    }
}
