<?php

namespace App\Observers\Facturacion;

use App\Services\Finance\Billing\EstadoCuentaService;
use App\Services\Finance\Billing\ReglasFacturacion;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * FR-02 (#536): «Cancelado» es una baja definitiva.
 *  - updating: ningún proceso automático (pago, promesa, bloqueo, despliegue) saca a un cliente de
 *    Cancelado; solo el guardado manual de la ficha (ReglasFacturacion::cambioManual()).
 *  - updated: al pasar a Cancelado se genera su estado de cuenta final (no se envía).
 *
 * Se registra en las DOS clases de ClientMainInformation (legacy y modular): los eventos de Eloquent
 * no se comparten entre ellas pese a la herencia (ver EventServiceProvider). Las actualizaciones
 * por query builder no pasan por aquí; las que tocan el estado se cuidan donde ocurren
 * (ClientTrait::activarCliente).
 */
class EstadoClienteObserver
{
    public function updating($info): void
    {
        if (! $info->isDirty('estado')) {
            return;
        }
        $antes = $info->getOriginal('estado');
        if ($antes !== ReglasFacturacion::CANCELADO || $info->estado === ReglasFacturacion::CANCELADO || ReglasFacturacion::esCambioManual()) {
            return;
        }

        $intento = (string) $info->estado;
        $info->estado = ReglasFacturacion::CANCELADO;
        Log::info("[ReglasFacturacion] Cliente {$info->client_id} sigue Cancelado: se ignoró el cambio automático a «{$intento}».");
        activity()->tap(function (Activity $activity) use ($info) {
            $activity->client_id = $info->client_id;
        })->log("Cliente #{$info->client_id} sigue Cancelado (baja definitiva): se ignoró el cambio automático a «{$intento}»");
    }

    public function updated($info): void
    {
        if (! $info->wasChanged('estado') || $info->estado !== ReglasFacturacion::CANCELADO) {
            return;
        }
        try {
            $manual = ReglasFacturacion::esCambioManual();
            app(EstadoCuentaService::class)->generarFinal((int) $info->client_id, $manual ? 'usuario' : 'sistema', $manual ? auth()->id() : null);
        } catch (\Throwable $e) {
            Log::error("[ReglasFacturacion] No se generó el estado de cuenta final del cliente {$info->client_id}: " . $e->getMessage());
        }
    }
}
