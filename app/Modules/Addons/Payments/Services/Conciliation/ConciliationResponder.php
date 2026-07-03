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

    /** Procesa el step: envía (si aplica), escala (si aplica), y dispara F4 al resolver. */
    public function apply(Session $session, array $step, $conversation): void
    {
        $this->respond($conversation, $step['outbound'] ?? null);

        if (($step['escalated'] ?? false) || $session->state === Session::STATE_ESCALATED) {
            $this->escalate($session, $conversation, $step['reason'] ?? ($session->escalation_reason ?? 'reconciliation'));
        }

        // F4 — al resolver, dispara la bifurcación de aplicación (auto/espera/Tere).
        if ($session->state === Session::STATE_RESOLVED && !$session->is_simulation) {
            \App\Modules\Addons\Payments\Jobs\ApplyIdentifiedPaymentJob::dispatch($session->id)->onQueue('default');
        }
    }

    /** Envía la respuesta al cliente — SOLO si el flag wa_autorespond está encendido. */
    public function respond($conversation, ?string $text): void
    {
        if (empty($text)) {
            return;
        }
        if (!\App\Modules\Addons\Payments\Support\ConciliationSettings::enabled('wa_autorespond')) {
            Log::channel('evolution')->info('Conciliación: respuesta SILENCIADA (wa_autorespond=false)', [
                'conversation_id' => $conversation->id ?? null,
                'preview'         => mb_substr($text, 0, 60),
            ]);
            return;
        }
        SendOutboundMessageJob::dispatch($conversation->id, $text, 'reconciliation')->onQueue('default');
    }

    /**
     * Escala el caso a revisión humana marcando la conversación de Marketing a
     * human_review (sale del bot de ventas).
     *
     * Opción A (F6): NO se crea un reconciliation_ticket. La cola de Tere lee las
     * SESIONES directamente (la sesión ya quedó en state=escalated), así que el
     * caso aparece en la pestaña ESCALADOS sin duplicar registro.
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
