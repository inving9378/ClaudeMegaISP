<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Message;
use App\Modules\Addons\Payments\Models\WhatsappPaymentExtraction;
use App\Modules\Addons\Payments\Services\Conciliation\IdentificationSessionStarter;
use App\Modules\Addons\Payments\Services\Extraction\PaymentReceiptExtractor;
use App\Modules\Addons\Payments\Services\Extraction\Profiles\SpeiTransferProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * FASE 2 — Pantalla de revisión de comprobantes recibidos por WhatsApp.
 *
 * ALCANCE: un admin ve el comprobante (imagen o PDF), pulsa "Extraer con IA"
 * (PaymentReceiptExtractor guarda/muestra campos + confianza + ilegibles +
 * raw) y, ya con la extracción hecha, puede pulsar "Identificar cliente"
 * (item #204 FASE A) para arrancar la MISMA máquina de estados F3 que corre
 * automático en vivo (IdentificationSessionStarter → IdentificationFsm +
 * ConciliationResponder) — disparo manual, no gastar IA/mensajes de más.
 *
 * NO aplica pago (F4 sigue con su propio candado + freno maestro
 * payments.auto_apply_enabled). Gateada por rol (super-administrator|DESARROLLADOR)
 * en las rutas.
 */
class WhatsappReceiptReviewController extends Controller
{
    /** Solo servimos/leemos archivos bajo esta carpeta (comprobantes de WhatsApp). */
    private const MEDIA_PREFIX = 'private/payments/whatsapp/comprobantes/';

    private const REVIEWABLE_TYPES = ['image', 'document'];

    public function index()
    {
        $messages = Message::whereIn('content_type', self::REVIEWABLE_TYPES)
            ->where('direction', 'inbound')
            ->whereNotNull('media_path')
            ->latest('id')
            ->limit(60)
            ->get(['id', 'conversation_id', 'content_type', 'content', 'media_path', 'created_at', 'external_message_id']);

        // Última extracción por mensaje (si ya se procesó). Orden ascendente para
        // que keyBy conserve la MÁS reciente (última ocurrencia gana).
        $extractions = WhatsappPaymentExtraction::whereIn('message_id', $messages->pluck('id'))
            ->orderBy('id')->get()->keyBy('message_id');

        $rows = $messages->map(function ($m) use ($extractions) {
            $ext = $extractions->get($m->id);
            return [
                'id'              => $m->id,
                'conversation_id' => $m->conversation_id,
                'type'            => $m->content_type,
                'ext'             => strtolower(pathinfo($m->media_path, PATHINFO_EXTENSION)),
                'caption'         => (string) $m->content,
                'received_at'     => optional($m->created_at)->format('Y-m-d H:i'),
                'extracted'       => $ext !== null,
                'extracted_ok'    => $ext?->ok,
            ];
        });

        return view('addon-payments::whatsapp-comprobantes', ['rows' => $rows]);
    }

    /** Sirve el binario del comprobante (imagen inline o PDF) para verlo. */
    public function media(int $messageId)
    {
        $message = $this->findReviewable($messageId);
        $path    = $message->media_path;

        abort_unless(str_starts_with($path, self::MEDIA_PREFIX), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type'        => $this->mimeFor($path),
            'Content-Disposition' => 'inline',
        ]);
    }

    /** Corre la extracción por IA y guarda el resultado. SOLO lee/extrae. */
    public function extract(int $messageId, PaymentReceiptExtractor $extractor)
    {
        $message = $this->findReviewable($messageId);
        $path    = $message->media_path;

        abort_unless(str_starts_with($path, self::MEDIA_PREFIX), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $bytes = Storage::disk('local')->get($path);
        $mime  = $this->mimeFor($path);

        $result = $extractor->extract($bytes, $mime, SpeiTransferProfile::TYPE);

        $extraction = WhatsappPaymentExtraction::create([
            'message_id'      => $message->id,
            'conversation_id' => $message->conversation_id,
            'document_type'   => $result['document_type'] ?? SpeiTransferProfile::TYPE,
            'source_mime'     => $mime,
            // Concepto denormalizado a columna top-level (F3 buscará la ref MEG aquí).
            'concepto'        => $result['fields']['concepto']['value'] ?? null,
            'fecha_pago'      => $result['fields']['fecha_pago']['value'] ?? null,
            'ok'              => $result['ok'] ?? false,
            'fields'          => $result['fields'] ?? [],
            'unreadable'      => $result['unreadable'] ?? [],
            'error'           => $result['error'] ?? null,
            'model'           => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
            'raw'             => $result['raw'] ?? null,
            'extracted_by'    => auth()->id(),
            'extracted_at'    => now(),
        ]);

        return response()->json([
            'result'        => $result,
            'extraction_id' => $extraction->id,
            'extracted_at'  => $extraction->extracted_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Item #204 FASE A — arranca la identificación F3 sobre la extracción MÁS
     * RECIENTE de este mensaje (exige haber pulsado "Extraer con IA" antes).
     * Idempotente: si ya existe una sesión real para este mensaje, la devuelve
     * en vez de duplicarla.
     */
    public function identify(int $messageId, IdentificationSessionStarter $starter)
    {
        $message = $this->findReviewable($messageId);

        $extraction = WhatsappPaymentExtraction::where('message_id', $message->id)
            ->whereNull('discarded_at')
            ->latest('id')
            ->first();

        abort_if(!$extraction, 422, 'Primero extrae el comprobante con IA.');

        return response()->json($starter->startFromExtraction($extraction, $message));
    }

    /** Carga un mensaje que sea comprobante de WhatsApp descargado, o 404. */
    private function findReviewable(int $messageId): Message
    {
        $message = Message::whereIn('content_type', self::REVIEWABLE_TYPES)
            ->where('direction', 'inbound')
            ->whereNotNull('media_path')
            ->findOrFail($messageId);

        return $message;
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
