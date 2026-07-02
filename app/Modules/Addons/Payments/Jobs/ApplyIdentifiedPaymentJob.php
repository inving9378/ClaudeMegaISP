<?php

namespace App\Modules\Addons\Payments\Jobs;

use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Services\Conciliation\ConciliationResponder;
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

    public function __construct(public int $sessionId, public ?int $confirmedBy = null) {}

    public function handle(PaymentFromSessionService $applier, ConciliationResponder $responder): void
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

        if (in_array($reason, self::NO_ENQUEUE, true) || str_starts_with($reason, 'apply_error')) {
            // 'auto_apply_disabled' = candidato exact frenado por el flag: espera,
            // NO se manda a Tere. 'apply_error' se reintenta (backoff), tampoco a Tere aún.
            Log::channel('evolution')->info('F4: no aplicado, sin encolar a humano', [
                'session_id' => $session->id, 'reason' => $reason,
            ]);
            return;
        }

        // proposed / multi-servicio / duplicate_clave / no_amount / no_clave → Tere confirma/revisa.
        $responder->enqueueForTere($session, 'apply_' . $reason);
        Log::channel('evolution')->info('F4: encolado para confirmación humana (Tere)', [
            'session_id' => $session->id, 'reason' => $reason,
        ]);
    }
}
