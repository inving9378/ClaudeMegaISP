<?php

namespace App\Modules\Addons\Payments\Listeners;

use App\Modules\Addons\Payments\Jobs\GatewayConciliationIntakeJob;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppMediaReceived;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Consumidor de MEDIA del gateway → conciliación de comprobantes.
 *
 * Payments (consumidor) se suscribe al gateway ÚNICO; descarga el binario por
 * WhatsAppGateway y despacha el intake gateway-native. Fase 1: parejo (toda
 * image/document); la función de línea 'cobranza' viaja en el evento
 * (`$event->hasFunction('cobranza')`) para gatear en una fase futura.
 */
class ConciliationListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(WhatsAppMediaReceived $event): void
    {
        // Gate por función de línea (Opción A, apagado total): si la línea NO tiene
        // "Conciliación" marcada, el comprobante se ignora ANTES de descargar media
        // o crear cualquier registro → cero rastro, cero respuesta.
        if (!$event->hasFunction('conciliacion')) {
            return;
        }

        $message = WhatsAppMessage::find($event->messageId);
        if (!$message) {
            return;
        }

        $res = app(WhatsAppGateway::class)->downloadMedia($message, $event->rawMessage);
        if (!$res) {
            Log::channel('evolution')->warning('Conciliación gateway: no se pudo descargar la media', [
                'message_id' => $event->messageId,
            ]);
            return;
        }

        GatewayConciliationIntakeJob::dispatch($message->id)->onQueue('default');
    }
}
