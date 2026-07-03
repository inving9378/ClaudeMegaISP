<?php

namespace App\Modules\Addons\Payments\Jobs;

use App\Models\Marketing\Conversation;
use App\Models\Marketing\Message;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Models\WhatsappPaymentExtraction;
use App\Modules\Addons\Payments\Services\Conciliation\ConciliationResponder;
use App\Modules\Addons\Payments\Services\Extraction\PaymentReceiptExtractor;
use App\Modules\Addons\Payments\Services\Extraction\Profiles\SpeiTransferProfile;
use App\Modules\Addons\Payments\Services\Identification\IdentificationFsm;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * FASE 3.5 — Ingesta de un comprobante recibido por WhatsApp al flujo de
 * conciliación: espera a que F1 baje el binario, lo lee con F2
 * (PaymentReceiptExtractor) e inicia la sesión de identificación F3.
 *
 * Aislado y best-effort. NO envía mensajes reales salvo que wa_autorespond=true
 * (lo decide el ConciliationResponder). Idempotente por mensaje.
 */
class ConciliationIntakeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;
    public array $backoff = [15, 30, 30, 60, 60, 120, 120];

    public function __construct(public int $messageId) {}

    public function handle(
        PaymentReceiptExtractor $extractor,
        IdentificationFsm $fsm,
        ConciliationResponder $responder,
    ): void {
        $message = Message::find($this->messageId);
        if (!$message) {
            return;
        }

        // Idempotencia: si ya hay sesión para este mensaje, no reprocesar.
        if (Session::where('message_id', $message->id)->where('is_simulation', false)->exists()) {
            return;
        }

        // Idempotencia: si ya se descartó este mensaje (no era comprobante), no
        // reprocesar — evita re-llamar a la IA sobre la misma imagen basura.
        if (WhatsappPaymentExtraction::where('message_id', $message->id)->whereNotNull('discarded_at')->exists()) {
            return;
        }

        // Esperar a que F1 haya descargado el binario.
        if (empty($message->media_path) || !Storage::disk('local')->exists($message->media_path)) {
            $this->release(30); // reintenta; el binario aún no está
            return;
        }

        $bytes = Storage::disk('local')->get($message->media_path);
        $mime  = $this->mimeFor($message->media_path);

        // F2 — extracción por IA.
        $result = $extractor->extract($bytes, $mime, SpeiTransferProfile::TYPE);

        $extraction = WhatsappPaymentExtraction::create([
            'message_id'      => $message->id,
            'conversation_id' => $message->conversation_id,
            'document_type'   => $result['document_type'] ?? SpeiTransferProfile::TYPE,
            'source_mime'     => $mime,
            'concepto'        => $result['fields']['concepto']['value'] ?? null,
            'fecha_pago'      => $result['fields']['fecha_pago']['value'] ?? null,
            'ok'              => $result['ok'] ?? false,
            'fields'          => $result['fields'] ?? [],
            'unreadable'      => $result['unreadable'] ?? [],
            'error'           => $result['error'] ?? null,
            'model'           => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
            'raw'             => $result['raw'] ?? null,
            'extracted_at'    => now(),
        ]);

        // FILTRO: ¿es un comprobante? Umbral CONSERVADOR — solo se descarta si NO
        // hay ningún dato de pago (ni monto ni clave de rastreo). Ante duda (algún
        // dato presente, aun de baja confianza) se trata como comprobante y sigue
        // el flujo, para NO perder pagos reales. Una imagen sin monto ni clave
        // (captura de chat, meme, foto del módem) se descarta EN SILENCIO: no crea
        // sesión ni caso en la cola y NO se responde al cliente.
        if (!$this->looksLikeReceipt($result)) {
            $extraction->update(['discarded_at' => now(), 'discard_reason' => 'no_payment_fields']);
            Log::channel('evolution')->info('Conciliación: imagen descartada (no es comprobante)', [
                'message_id'    => $message->id,
                'extraction_id' => $extraction->id,
            ]);
            return;
        }

        // ANTIFRAUDE: si esta clave de rastreo ya se recibió antes desde OTRA
        // conversación (otro número), es posible reenvío del comprobante de otra
        // persona. NO se auto-procesa: el caso se crea directo en ESCALADOS
        // marcado como duplicidad sospechosa, para revisión humana (la
        // comparativa de números se calcula al abrir el caso). No se responde
        // al cliente. El anti-duplicado de F4 sigue vigente al aplicar.
        $clave = trim((string) ($result['fields']['clave_rastreo']['value'] ?? ''));
        if ($clave !== '' && $this->claveSeenInOtherConversation($clave, (int) $message->conversation_id)) {
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
            Log::channel('evolution')->warning('Conciliación: clave duplicada desde otra conversación → ESCALADO (posible fraude)', [
                'message_id'    => $message->id,
                'extraction_id' => $extraction->id,
                'session_id'    => $session->id,
                'clave'         => $clave,
            ]);
            return; // NO FSM, NO auto-respuesta
        }

        // F3 — inicia la sesión de identificación.
        $session = Session::create([
            'is_simulation'   => false,
            'extraction_id'   => $extraction->id,
            'conversation_id' => $message->conversation_id,
            'message_id'      => $message->id,
            'state'           => Session::STATE_DETECTING,
            'attempts'        => 0,
            'expires_at'      => now()->addHours((int) (Setting::get('reconciliation_session_hours', 1) ?? 12)),
        ]);

        $concepto = (string) ($result['fields']['concepto']['value'] ?? '');
        $titular  = $result['fields']['titular_ordenante']['value'] ?? null;
        $phone    = $this->phoneHint($message);

        $step = $fsm->start($session, $concepto, $titular, $phone);
        $responder->apply($session->fresh(), $step, Conversation::find($message->conversation_id));

        Log::channel('evolution')->info('Conciliación: intake procesado', [
            'message_id'    => $message->id,
            'extraction_id' => $extraction->id,
            'session_id'    => $session->id,
            'state'         => $session->fresh()->state,
        ]);
    }

    /**
     * Umbral CONSERVADOR: es comprobante si la extracción trae monto O clave de
     * rastreo (cualquier valor no vacío, aun de baja confianza). Solo cuando
     * AMBOS críticos están vacíos se considera que la imagen no es un comprobante.
     * Se calibra hacia NO perder pagos: preferimos un caso de más en la cola
     * (que un humano descarte) a ignorar un pago real.
     */
    private function looksLikeReceipt(array $result): bool
    {
        $fields = $result['fields'] ?? [];
        $has = static function (string $key) use ($fields): bool {
            $v = $fields[$key]['value'] ?? null;
            return $v !== null && trim((string) $v) !== '';
        };

        return $has('monto') || $has('clave_rastreo');
    }

    /** ¿Esta clave de rastreo ya se recibió en OTRA conversación (otro número)? */
    private function claveSeenInOtherConversation(string $clave, int $conversationId): bool
    {
        return WhatsappPaymentExtraction::where('conversation_id', '!=', $conversationId)
            ->whereNull('discarded_at')
            ->where('fields->clave_rastreo->value', $clave)
            ->exists();
    }

    private function phoneHint(Message $message): ?string
    {
        $jid = $message->metadata['key']['remoteJid'] ?? null;
        return $jid ? EvolutionApiService::jidToPhone($jid) : null;
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
