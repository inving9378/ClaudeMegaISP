<?php

namespace App\Modules\Addons\Payments\Jobs;

use App\Modules\Addons\Payments\Models\WhatsappPaymentExtraction;
use App\Modules\Addons\Payments\Services\Extraction\PaymentReceiptExtractor;
use App\Modules\Addons\Payments\Services\Extraction\Profiles\SpeiTransferProfile;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Intake de conciliación GATEWAY-NATIVE (WhatsAppAgent), en paralelo al de
 * Marketing (ConciliationIntakeJob) — SIN tocarlo. Reusa el core de lectura
 * ({@see PaymentReceiptExtractor}) y persiste con source='gateway' + los ids del
 * gateway (whatsapp_messages / whatsapp_conversations), de modo que no colisiona
 * con la ruta de Marketing (source='marketing').
 *
 * Alcance Fase 1: descarga (hecha por el listener) → extracción → persistencia →
 * ACUSE al cliente por el gateway. La identificación conversacional (FSM) y la
 * cola humana source-aware quedan para Fase 1.x.
 */
class GatewayConciliationIntakeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // La cola se fija con ->onQueue('default') al despachar. NO redeclarar la
    // propiedad $queue: el trait Queueable ya la define y una redeclaración
    // TIPADA es incompatible en PHP 8 (FatalError al componer la clase).

    public function __construct(public int $messageId)
    {
    }

    public function handle(PaymentReceiptExtractor $extractor, WhatsAppGateway $gateway): void
    {
        $message = WhatsAppMessage::find($this->messageId);
        if (!$message) {
            return;
        }

        // Idempotencia scoping por (source=gateway, source_message_id).
        if (WhatsappPaymentExtraction::where('source', 'gateway')
            ->where('source_message_id', $message->id)->exists()) {
            return;
        }

        // El binario lo baja el listener antes de despachar; defensivo por si aún no está.
        if (empty($message->media_path) || !Storage::disk('local')->exists($message->media_path)) {
            $this->release(30);
            return;
        }

        $bytes = Storage::disk('local')->get($message->media_path);
        $mime  = $message->media_mime_type ?: 'image/jpeg';

        $result = $extractor->extract($bytes, $mime, SpeiTransferProfile::TYPE);

        $extraction = WhatsappPaymentExtraction::create([
            'source'                 => 'gateway',
            'source_message_id'      => $message->id,
            'source_conversation_id' => $message->conversation_id,
            'message_id'             => $message->id,            // legacy NOT NULL; queries de Marketing van scoped a source='marketing'
            'conversation_id'        => $message->conversation_id,
            'document_type'          => $result['document_type'] ?? SpeiTransferProfile::TYPE,
            'source_mime'            => $mime,
            'concepto'               => $result['fields']['concepto']['value'] ?? null,
            'fecha_pago'             => $result['fields']['fecha_pago']['value'] ?? null,
            'ok'                     => $result['ok'] ?? false,
            'fields'                 => $result['fields'] ?? [],
            'unreadable'             => $result['unreadable'] ?? [],
            'error'                  => $result['error'] ?? null,
            'model'                  => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
            'raw'                    => $result['raw'] ?? null,
            'extracted_at'           => now(),
        ]);

        // ¿Es comprobante? Umbral conservador (monto O clave). Si no, se descarta
        // EN SILENCIO (no se responde) — igual criterio que la ruta de Marketing.
        if (!$this->looksLikeReceipt($result)) {
            $extraction->update(['discarded_at' => now(), 'discard_reason' => 'no_payment_fields']);
            Log::channel('evolution')->info('Conciliación gateway: imagen descartada (no es comprobante)', [
                'source_message_id' => $message->id,
                'extraction_id'     => $extraction->id,
            ]);
            return;
        }

        // ACUSE al cliente por el GATEWAY (instancia correcta), no por Marketing.
        try {
            $slug  = $message->instance?->slug;
            $phone = $message->conversation?->contact_number;
            if ($slug && $phone) {
                $gateway->sendText(
                    $slug,
                    $phone,
                    'Recibimos tu comprobante 🧾. Lo estamos revisando y un asesor confirmará tu pago en breve. ¡Gracias!'
                );
            }
        } catch (\Throwable $e) {
            Log::channel('evolution')->warning('Conciliación gateway: fallo al enviar acuse', [
                'source_message_id' => $message->id,
                'error'             => $e->getMessage(),
            ]);
        }

        Log::channel('evolution')->info('Conciliación gateway: comprobante extraído + acuse enviado', [
            'source_message_id' => $message->id,
            'extraction_id'     => $extraction->id,
            'ok'                => $extraction->ok,
        ]);
    }

    /** Es comprobante si trae monto O clave de rastreo (cualquier valor no vacío). */
    private function looksLikeReceipt(array $result): bool
    {
        $fields = $result['fields'] ?? [];
        $has = static function (string $key) use ($fields): bool {
            $v = $fields[$key]['value'] ?? null;
            return $v !== null && trim((string) $v) !== '';
        };

        return $has('monto') || $has('clave_rastreo');
    }
}
