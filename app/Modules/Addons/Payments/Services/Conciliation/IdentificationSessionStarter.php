<?php

namespace App\Modules\Addons\Payments\Services\Conciliation;

use App\Models\Marketing\Conversation;
use App\Models\Marketing\Message;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Models\WhatsappPaymentExtraction;
use App\Modules\Addons\Payments\Services\Identification\IdentificationFsm;

/**
 * Item #204 (FASE A) — arranca una sesión F3 a partir de una extracción YA
 * HECHA (comprobante ya leído por F2), reusando el MISMO motor
 * (IdentificationFsm + ConciliationResponder) que el intake automático
 * (ConciliationIntakeJob::handle, a partir de "F3 — inicia la sesión de
 * identificación"). Pensado para el disparo MANUAL del botón "Identificar
 * cliente" en /finanzas/whatsapp-comprobantes.
 *
 * Puro arranque de sesión: NO aplica pagos (F4 sigue detrás de su propia
 * bifurcación + freno maestro payments.auto_apply_enabled) y NO toca el flujo
 * de ventas (ProcessIncomingMessageJob no se modificó).
 */
class IdentificationSessionStarter
{
    public function __construct(
        private IdentificationFsm $fsm,
        private ConciliationResponder $responder,
    ) {}

    /**
     * @return array{status:string, session_id:int, state:string}
     *   status: created | already_exists | duplicate_clave
     */
    public function startFromExtraction(WhatsappPaymentExtraction $extraction, Message $message): array
    {
        $existing = Session::where('message_id', $message->id)->where('is_simulation', false)->latest('id')->first();
        if ($existing) {
            return ['status' => 'already_exists', 'session_id' => $existing->id, 'state' => $existing->state];
        }

        $fields = $extraction->fields ?? [];
        $clave  = trim((string) ($fields['clave_rastreo']['value'] ?? ''));
        if ($clave === '') {
            $clave = trim((string) ($fields['referencia']['value'] ?? ''));
        }
        $convId = (int) $message->conversation_id;

        // Mismo candado antifraude que el intake automático (ConciliationIntakeJob):
        // un comprobante ya visto en OTRA conversación se escala directo, sin FSM.
        if ($clave !== '' && $this->claveSeenInOtherConversation($clave, $convId)) {
            $session = Session::create([
                'is_simulation'     => false,
                'extraction_id'     => $extraction->id,
                'conversation_id'   => $message->conversation_id,
                'message_id'        => $message->id,
                'state'             => Session::STATE_ESCALATED,
                'escalation_reason' => 'duplicate_clave',
                'attempts'          => 0,
                'expires_at'        => now()->addHours((int) (Setting::get('reconciliation_session_hours', 1) ?? 12)),
            ]);
            return ['status' => 'duplicate_clave', 'session_id' => $session->id, 'state' => $session->state];
        }

        $session = Session::create([
            'is_simulation'   => false,
            'extraction_id'   => $extraction->id,
            'conversation_id' => $message->conversation_id,
            'message_id'      => $message->id,
            'state'           => Session::STATE_DETECTING,
            'attempts'        => 0,
            'expires_at'      => now()->addHours((int) (Setting::get('reconciliation_session_hours', 1) ?? 12)),
        ]);

        $concepto = (string) ($fields['concepto']['value'] ?? '');
        $titular  = $fields['titular_ordenante']['value'] ?? null;
        $phone    = $this->phoneHint($message);

        $step = $this->fsm->start($session, $concepto, $titular, $phone);
        $this->responder->apply($session->fresh(), $step, Conversation::find($message->conversation_id));

        return ['status' => 'created', 'session_id' => $session->id, 'state' => $session->fresh()->state];
    }

    /** ¿Esta clave/referencia ya se recibió en OTRA conversación (otro número)? */
    private function claveSeenInOtherConversation(string $clave, int $conversationId): bool
    {
        return WhatsappPaymentExtraction::where('conversation_id', '!=', $conversationId)
            ->whereNull('discarded_at')
            ->where(function ($q) use ($clave) {
                $q->where('fields->clave_rastreo->value', $clave)
                  ->orWhere('fields->referencia->value', $clave);
            })
            ->exists();
    }

    private function phoneHint(Message $message): ?string
    {
        $jid = $message->metadata['key']['remoteJid'] ?? null;
        return $jid ? EvolutionApiService::jidToPhone($jid) : null;
    }
}
