<?php

namespace App\Modules\Addons\Marketing\Listeners;

use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppMediaReceived;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppMessageReceived;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador de Marketing hacia el gateway único de WhatsApp (item roadmap
 * #203, Fase A — preparación técnica). Se suscribe a los eventos del gateway
 * (WhatsAppAgent) para que, en fases futuras aprobadas por separado, Marketing
 * pueda dejar de depender de su integración propia (EvolutionApiService +
 * webhook `/webhooks/marketing/evolution` + ProcessIncomingMessageJob). Esta
 * clase NO reemplaza el camino actual: ver config('marketing_gateway.mode').
 *
 * Modos (config/marketing_gateway.php):
 * - legacy  (default): no-op total.
 * - shadow: procesa en paralelo SOLO para comparar (logging), nunca responde.
 *   Requiere aprobación del item #203-B antes de activarse.
 * - unified: reservado para el cutover supervisado (#203-C). Intencionalmente
 *   NO implementado aquí — activar este modo hoy solo deja constancia en log,
 *   no envía ni responde nada.
 *
 * Rollback: fijar WHATSAPP_MARKETING_GATEWAY_MODE=legacy (o quitar la env, es
 * el default) vuelve este listener a no-op inmediato. Para revertir del todo:
 * quitar los dos Event::listen() de Marketing\ModuleServiceProvider::boot() y
 * borrar este archivo — no crea tablas ni persiste estado propio.
 */
class GatewayMessageListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handleText(WhatsAppTextReceived $event): void
    {
        $this->dispatch($event, 'text');
    }

    public function handleMedia(WhatsAppMediaReceived $event): void
    {
        $this->dispatch($event, 'media');
    }

    private function dispatch(WhatsAppMessageReceived $event, string $kind): void
    {
        // Solo nos interesan líneas de ventas/marketing — no pisar conciliación u otras.
        if (!$event->hasFunction('ventas')) {
            return;
        }

        match (config('marketing_gateway.mode', 'legacy')) {
            'shadow'  => $this->handleShadow($event, $kind),
            'unified' => $this->handleUnified($event, $kind),
            default   => null, // legacy: no-op
        };
    }

    /** Solo observa y compara — NUNCA responde al cliente (activar en #203-B). */
    private function handleShadow(WhatsAppMessageReceived $event, string $kind): void
    {
        Log::channel('evolution')->info('MarketingGateway[shadow]: evento observado (sin responder)', [
            'kind'            => $kind,
            'conversation_id' => $event->conversationId,
            'contact'         => $event->contactNumber,
            'type'            => $event->type,
        ]);
    }

    /** Reservado para el cutover supervisado (#203-C). Intencionalmente no implementado. */
    private function handleUnified(WhatsAppMessageReceived $event, string $kind): void
    {
        Log::channel('evolution')->warning('MarketingGateway[unified]: modo aún no implementado, evento ignorado', [
            'kind'            => $kind,
            'conversation_id' => $event->conversationId,
        ]);
    }
}
