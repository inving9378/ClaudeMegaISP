<?php

namespace App\Modules\Addons\Payments\Services\Conciliation;

use App\Modules\Addons\Marketing\Jobs\SendOutboundMessageJob;
use App\Modules\Addons\Marketing\Services\AgentTools\AssignToHumanTool;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use Illuminate\Support\Facades\Log;

/**
 * FASE 3.5/3.6 — Efectos hacia el mundo real de un "step" del FSM.
 *
 * - respond(): manda la respuesta al cliente SOLO si wa_autorespond=true. En
 *   desarrollo (flag apagado) NO se envía ningún WhatsApp: la sesión avanza en
 *   silencio.
 * - escalate(): al llegar a 'escalated', reusa la cola de conciliación
 *   EXISTENTE (ReconciliationService/reconciliation_tickets) para que Tere lo
 *   revise, y pasa la conversación de Marketing a human_review (AssignToHumanTool).
 */
class ConciliationResponder
{
    public function __construct(private AssignToHumanTool $assignTool) {}

    /** Procesa el step: envía (si aplica) y escala (si aplica). */
    public function apply(Session $session, array $step, $conversation): void
    {
        $this->respond($conversation, $step['outbound'] ?? null);

        if (($step['escalated'] ?? false) || $session->state === Session::STATE_ESCALATED) {
            $this->escalate($session, $conversation, $step['reason'] ?? ($session->escalation_reason ?? 'reconciliation'));
        }
    }

    /** Envía la respuesta al cliente — SOLO si el flag wa_autorespond está encendido. */
    public function respond($conversation, ?string $text): void
    {
        if (empty($text)) {
            return;
        }
        if (!config('payments.wa_autorespond')) {
            Log::channel('evolution')->info('Conciliación: respuesta SILENCIADA (wa_autorespond=false)', [
                'conversation_id' => $conversation->id ?? null,
                'preview'         => mb_substr($text, 0, 60),
            ]);
            return;
        }
        SendOutboundMessageJob::dispatch($conversation->id, $text, 'reconciliation')->onQueue('default');
    }

    /**
     * Escala el caso a revisión humana: marca la conversación de Marketing a
     * human_review (sale del bot de ventas). La conexión con la cola de
     * conciliación de Tere (reconciliation_tickets) se agrega en F3.6.
     */
    public function escalate(Session $session, $conversation, string $reason): void
    {
        if ($conversation) {
            try {
                $this->assignTool->execute($conversation, 'Conciliación de pago: ' . $reason);
            } catch (\Throwable $e) {
                Log::channel('evolution')->warning('assign_to_human en conciliación falló: ' . $e->getMessage());
            }
        }
    }
}
