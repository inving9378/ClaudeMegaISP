<?php

namespace App\Modules\Addons\WhatsAppAgent\Listeners;

use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Addons\WhatsAppAgent\Services\AgenteVentas\AgenteVentasReplyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Consumidor de TEXTO del gateway → el Agente de Ventas (IA) AISLADO.
 *
 * Mismo patrón que {@see IaAutoReplyListener} (el bot genérico), pero gateado
 * por la función NUEVA 'agente_ventas' — cero código compartido con ese
 * listener. Corre por cola (worker), best-effort (un fallo no propaga).
 */
class AgenteVentasTextListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(WhatsAppTextReceived $event): void
    {
        if (!(bool) config('whatsapp.agente_ventas_enabled', false)) {
            return;
        }

        if (!$event->hasFunction('agente_ventas')) {
            return;
        }

        // Candado cruzado: si por error de configuración la misma línea
        // también tiene activa la función genérica 'ventas', NO responder
        // aquí — evita que el prospecto reciba DOS respuestas al mismo
        // mensaje. 'ventas' y 'agente_ventas' son mutuamente excluyentes por
        // diseño; esto solo blinda contra un mal armado del panel.
        if ($event->hasFunction('ventas')) {
            Log::warning('AgenteVentas: línea con "ventas" Y "agente_ventas" activas a la vez — se omite para evitar doble respuesta', [
                'instance_slug' => $event->instanceSlug,
            ]);
            return;
        }

        $message      = WhatsAppMessage::find($event->messageId);
        $conversation = WhatsAppConversation::find($event->conversationId);
        if (!$message || !$conversation) {
            return;
        }

        try {
            app(AgenteVentasReplyService::class)->maybeReply($message, $conversation);
        } catch (\Throwable $e) {
            Log::warning('AgenteVentas (listener): excepción', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
