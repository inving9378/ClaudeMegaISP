<?php

namespace App\Modules\Addons\Payments\Jobs;

use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Services\Conciliation\PaymentFromSessionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * FASE 4 (F4.4) — Bifurcación de la aplicación de un pago identificado.
 *
 * Se dispara cuando una sesión de identificación queda resuelta. Intenta la
 * aplicación AUTOMÁTICA (PaymentFromSessionService) y rutea según el resultado:
 *   - applied               → listo (solo ocurre con freno maestro encendido + exact).
 *   - auto_apply_disabled    → candidato a auto, FRENADO por flag → espera (no molesta a Tere).
 *   - needs_human_confirmation / multiple_services_needs_human / duplicate_clave /
 *     no_amount / no_clave / apply_error → a la cola de Tere (confirma/revisa en F6).
 *
 * No mueve dinero por sí mismo (delega en el servicio, que respeta el freno).
 */
class ApplyIdentifiedPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    /** Motivos que NO requieren intervención humana (esperan flag o ya están hechos). */
    private const NO_ENQUEUE = ['auto_apply_disabled', 'already_applied', 'simulation'];

    /** Fallos de DATOS del comprobante → la sesión pasa a 'escalated' (pestaña Escalados). */
    private const DATA_FAILURE = ['no_amount', 'no_clave', 'duplicate_clave'];

    public function __construct(public int $sessionId, public ?int $confirmedBy = null) {}

    public function handle(PaymentFromSessionService $applier): void
    {
        $session = Session::find($this->sessionId);
        if (!$session || $session->is_simulation || $session->state !== Session::STATE_RESOLVED) {
            return;
        }
        if ($session->applied_at !== null && $session->applied_payment_id !== null) {
            return; // ya aplicado
        }

        $result = $applier->apply($session, $this->confirmedBy);

        if ($result['applied']) {
            Log::channel('evolution')->info('F4: aplicación OK', ['session_id' => $session->id, 'payment_id' => $result['payment_id']]);
            return;
        }

        $reason = $result['reason'];

        // Opción A: la COLA de Tere lee las sesiones directamente, no tickets.
        //  - proposed / multi-servicio: la sesión ya está 'resolved' → aparece en
        //    la pestaña PROPUESTOS por su estado. No se hace nada aquí.
        //  - auto_apply_disabled / apply_error: espera (flag o reintento). No cola.
        //  - fallo de DATOS (no_amount/no_clave/duplicate_clave): el comprobante no
        //    se pudo procesar → se ESCALA la sesión (pestaña ESCALADOS) para que
        //    Tere lo revise a mano.
        if (in_array($reason, self::DATA_FAILURE, true)) {
            $session->update([
                'state'             => Session::STATE_ESCALATED,
                'escalation_reason' => $reason,
            ]);
        }

        Log::channel('evolution')->info('F4: no aplicado automáticamente', [
            'session_id' => $session->id, 'reason' => $reason,
        ]);
    }
}
