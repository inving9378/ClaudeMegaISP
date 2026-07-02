<?php

namespace App\Modules\Addons\Payments\Services\Conciliation;

use App\Models\Marketing\Lead;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\Payments\Jobs\ConciliationIntakeJob;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Services\Identification\IdentificationFsm;
use Illuminate\Support\Facades\DB;

/**
 * FASE 3.5 — Ruteo de conciliación de pagos sobre mensajes entrantes.
 *
 * ADITIVO y NO invasivo: ProcessIncomingMessageJob solo lo invoca si el flag
 * maestro payments.wa_conciliation está encendido. shouldHandle() decide si el
 * mensaje pertenece a conciliación; si devuelve false, el mensaje sigue su curso
 * normal al bot de VENTAS, sin cambios.
 *
 * Detección híbrida (decidida por Irving):
 *  - imagen/PDF = probable comprobante.
 *  - texto tipo "ya pagué" + el número YA es cliente = intención de pago.
 *  - además, si la conversación ya está en una sesión de identificación en
 *    curso, la respuesta del cliente se rutea al FSM.
 */
class ConciliationRouter
{
    /** Palabras que sugieren intención de pago en texto. */
    private const PAYMENT_HINTS = [
        'ya pague', 'ya pagué', 'ya deposite', 'ya deposité', 'ya transferi', 'ya transferí',
        'hice el pago', 'hice mi pago', 'realice el pago', 'realicé el pago', 'aqui esta mi pago',
        'aquí está mi pago', 'le mando mi pago', 'mi comprobante', 'comprobante de pago',
        'adjunto mi pago', 'pague mi', 'pagué mi', 'ya esta pagado', 'ya está pagado',
    ];

    public function __construct(
        private IdentificationFsm $fsm,
        private ConciliationResponder $responder,
    ) {}

    /** ¿Este mensaje pertenece al flujo de conciliación (y NO al de ventas)? */
    public function shouldHandle($conversation, string $contentType, ?string $text, Lead $lead): bool
    {
        if ($this->activeSession($conversation->id)) {
            return true;
        }
        return $this->looksLikePayment($contentType, $text, $lead);
    }

    /**
     * Rutea el mensaje. Devuelve un código de acción (para logging/pruebas):
     * reply_handled | intake_dispatched | session_started | ignored.
     */
    public function route($conversation, $message, string $contentType, ?string $text): string
    {
        $phone = $this->phoneHint($conversation, $message);

        // 1) Respuesta a una identificación en curso → FSM.
        $session = $this->activeSession($conversation->id);
        if ($session) {
            $step = $this->fsm->handleReply($session, (string) $text, $phone);
            $this->responder->apply($session->fresh(), $step, $conversation);
            return 'reply_handled';
        }

        // 2) Comprobante (imagen/PDF) → intake asíncrono (espera descarga, extrae, inicia).
        if (in_array($contentType, ['image', 'document'], true)) {
            ConciliationIntakeJob::dispatch($message->id)->onQueue('default');
            return 'intake_dispatched';
        }

        // 3) Texto de intención de pago de un cliente → inicia sesión (pedirá ID).
        $session = Session::create([
            'is_simulation'   => false,
            'extraction_id'   => 0, // sin comprobante todavía
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
            'state'           => Session::STATE_DETECTING,
            'attempts'        => 0,
            'expires_at'      => now()->addHours((int) (\App\Models\Marketing\Setting::get('reconciliation_session_hours', 1) ?? 12)),
        ]);
        $step = $this->fsm->start($session, '', null, $phone);
        $this->responder->apply($session->fresh(), $step, $conversation);
        return 'session_started';
    }

    // ── Detección ────────────────────────────────────────────────────────────

    public function looksLikePayment(string $contentType, ?string $text, Lead $lead): bool
    {
        if (in_array($contentType, ['image', 'document'], true)) {
            return true; // probable comprobante
        }
        if ($contentType === 'text' && $this->isPaymentIntentText($text) && $this->isClient($lead)) {
            return true;
        }
        return false;
    }

    public function isPaymentIntentText(?string $text): bool
    {
        if (!$text) {
            return false;
        }
        $t = mb_strtolower($text, 'UTF-8');
        foreach (self::PAYMENT_HINTS as $hint) {
            if (str_contains($t, $hint)) {
                return true;
            }
        }
        return false;
    }

    /** ¿El teléfono del lead corresponde a un cliente real del ISP? */
    public function isClient(Lead $lead): bool
    {
        $phone = preg_replace('/\D/', '', (string) ($lead->whatsapp ?: $lead->phone));
        if (strlen($phone) < 10) {
            return false;
        }
        $last10 = substr($phone, -10);
        return DB::table('client_main_information as cmi')
            ->join('clients as c', 'c.id', '=', 'cmi.client_id')
            ->whereNull('c.deleted_at')
            ->where(function ($q) use ($last10) {
                foreach (['phone', 'phone2', 'phone3'] as $col) {
                    $q->orWhere($col, 'like', '%' . $last10);
                }
            })
            ->exists();
    }

    /** Sesión de identificación en curso (no terminal, no simulación) de la conversación. */
    public function activeSession(int $conversationId): ?Session
    {
        return Session::where('conversation_id', $conversationId)
            ->where('is_simulation', false)
            ->whereNotIn('state', [Session::STATE_RESOLVED, Session::STATE_ESCALATED])
            ->latest('id')
            ->first();
    }

    private function phoneHint($conversation, $message): ?string
    {
        $jid = $conversation->external_thread_id ?? ($message->metadata['key']['remoteJid'] ?? null);
        return $jid ? EvolutionApiService::jidToPhone($jid) : null;
    }
}
