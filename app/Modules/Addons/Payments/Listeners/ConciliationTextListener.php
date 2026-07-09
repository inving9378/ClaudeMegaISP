<?php

namespace App\Modules\Addons\Payments\Listeners;

use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Services\Identification\IdentificationFsm;
use App\Modules\Addons\Payments\Support\ConciliationSettings;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Consumidor de TEXTO del gateway → identificación de conciliación EN CURSO.
 *
 * Equivalente gateway-native del guard `activeSession` que el router de Marketing
 * ya tiene: si la conversación tiene una sesión de identificación ACTIVA (source
 * gateway, no terminal), la respuesta del cliente (ej. "MEG1234", "sí soy yo") la
 * maneja el FSM — NO el bot de ventas. El bot de ventas se auto-inhibe con el
 * mismo criterio en WhatsAppAutoReplyService (evita doble respuesta).
 *
 * Reutiliza IdentificationFsm::handleReply TAL CUAL. NO dispara F4 (dinero): la
 * sesión resuelta/escalada aparece en la cola y un humano confirma.
 */
class ConciliationTextListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private IdentificationFsm $fsm, private WhatsAppGateway $gateway)
    {
    }

    public function handle(WhatsAppTextReceived $event): void
    {
        if (!ConciliationSettings::enabled('wa_conciliation')) {
            return;
        }

        $session = Session::where('source', 'gateway')
            ->where('source_conversation_id', $event->conversationId)
            ->where('is_simulation', false)
            ->whereNotIn('state', [Session::STATE_RESOLVED, Session::STATE_ESCALATED])
            ->latest('id')
            ->first();

        if (!$session) {
            return; // no hay identificación en curso → no es asunto de conciliación
        }

        try {
            $step = $this->fsm->handleReply($session, (string) $event->text, $event->contactNumber);
        } catch (\Throwable $e) {
            Log::channel('evolution')->warning('Conciliación gateway: FSM handleReply falló', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
            return;
        }

        // Respuesta gateway-native del FSM — SOLO si wa_autorespond está ON.
        $outbound = $step['outbound'] ?? null;
        if (empty($outbound)) {
            return;
        }
        if (!ConciliationSettings::enabled('wa_autorespond')) {
            Log::channel('evolution')->info('Conciliación gateway: respuesta FSM SILENCIADA (wa_autorespond=false)', [
                'session_id' => $session->id,
                'preview'    => mb_substr($outbound, 0, 60),
            ]);
            return;
        }
        try {
            $this->gateway->sendText($event->instanceSlug, $event->contactNumber, $outbound);
        } catch (\Throwable $e) {
            Log::channel('evolution')->warning('Conciliación gateway: fallo al enviar respuesta FSM', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
