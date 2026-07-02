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
