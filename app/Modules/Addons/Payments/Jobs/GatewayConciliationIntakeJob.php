<?php

namespace App\Modules\Addons\Payments\Jobs;

use App\Models\Marketing\Setting;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Models\WhatsappPaymentExtraction;
use App\Modules\Addons\Payments\Services\Extraction\PaymentReceiptExtractor;
use App\Modules\Addons\Payments\Services\Extraction\Profiles\SpeiTransferProfile;
use App\Modules\Addons\Payments\Services\Identification\IdentificationFsm;
use App\Modules\Addons\Payments\Support\ConciliationSettings;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Intake de conciliación GATEWAY-NATIVE (WhatsAppAgent), en paralelo al de
 * Marketing (ConciliationIntakeJob) — SIN tocarlo. Reusa el core de lectura
 * ({@see PaymentReceiptExtractor}) y el MOTOR de identificación
 * ({@see IdentificationFsm}, tal cual), y persiste con source='gateway' + los ids
 * del gateway (whatsapp_messages / whatsapp_conversations), de modo que no
 * colisiona con la ruta de Marketing (source='marketing').
 *
 * Fase 1.x (Opción B — gateway autocontenido): descarga (hecha por el listener)
 * → extracción (F2) → dedup antifraude source-aware → sesión de identificación
 * (F3, IdentificationFsm) → respuesta gateway-native (WhatsAppGateway, respetando
 * wa_autorespond). La sesión queda en whatsapp_identification_sessions y la cola
 * de /finanzas/conciliacion-cola la lee directo (Propuestos/Escalados) sin tocar
 * la UI. NO se dispara F4 (aplicación de dinero) desde aquí: la confirmación es
 * humana desde la cola; el motor de dinero queda intacto.
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

    public function handle(PaymentReceiptExtractor $extractor, WhatsAppGateway $gateway, IdentificationFsm $fsm): void
    {
        $message = WhatsAppMessage::find($this->messageId);
        if (!$message) {
            return;
        }

        // Idempotencia: si ya hay sesión gateway para este mensaje, no reprocesar.
        if (Session::where('source', 'gateway')
            ->where('source_message_id', $message->id)
            ->where('is_simulation', false)->exists()) {
            return;
        }

        // El binario lo baja el listener antes de despachar; defensivo por si aún no está.
        if (empty($message->media_path) || !Storage::disk('local')->exists($message->media_path)) {
            $this->release(30);
            return;
        }

        // Reusar extracción gateway previa (evita re-cobrar IA en reintentos / en
        // el mensaje que ya se extrajo en la etapa de solo-acuse); si se descartó,
        // no reprocesar.
        $extraction = WhatsappPaymentExtraction::where('source', 'gateway')
            ->where('source_message_id', $message->id)->first();

        if ($extraction && $extraction->discarded_at) {
            return;
        }

        if ($extraction) {
            $result = ['fields' => is_array($extraction->fields) ? $extraction->fields : []];
        } else {
            $bytes  = Storage::disk('local')->get($message->media_path);
            $mime   = $message->media_mime_type ?: 'image/jpeg';
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
        }

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

        $expiresAt = now()->addHours((int) (Setting::get('reconciliation_session_hours', 1) ?? 12));

        // ANTIFRAUDE (source-aware): si esta clave/huella ya se recibió en OTRA
        // conversación DEL GATEWAY, es posible reenvío del comprobante de otra
        // persona → caso directo a ESCALADOS, sin FSM ni auto-respuesta.
        $clave = trim((string) ($result['fields']['clave_rastreo']['value'] ?? ''));
        if ($clave === '') {
            $clave = trim((string) ($result['fields']['referencia']['value'] ?? ''));
        }
        // Detección de duplicado en la ENTRADA (3 tiers). Reemplaza el viejo
        // "solo otra conversación": ahora caza reenvíos del MISMO número y, sobre
        // todo, claves/huellas YA APLICADAS. Ninguna rama corre el FSM.
        $status = $clave !== ''
            ? $this->classifyClaveDuplicate($clave, $extraction->id)
            : $this->classifyFingerprintDuplicate($result['fields'] ?? [], $extraction->id);

        if ($status !== 'none') {
            $reason = $status === 'applied' ? 'duplicate_applied' : 'duplicate_clave';
            $session = Session::create([
                'is_simulation'          => false,
                'source'                 => 'gateway',
                'source_message_id'      => $message->id,
                'source_conversation_id' => $message->conversation_id,
                'extraction_id'          => $extraction->id,
                'conversation_id'        => $message->conversation_id,
                'message_id'             => $message->id,
                'state'                  => Session::STATE_ESCALATED,
                'escalation_reason'      => $reason,
                'attempts'               => 0,
                'expires_at'             => $expiresAt,
            ]);
            Log::channel('evolution')->warning('Conciliación gateway: comprobante duplicado → ESCALADO', [
                'source_message_id' => $message->id,
                'extraction_id'     => $extraction->id,
                'session_id'        => $session->id,
                'status'            => $status,
                'reason'            => $reason,
                'clave'             => $clave !== '' ? $clave : '(huella monto/fecha/banco)',
            ]);

            // Aviso al cliente — UNA sola vez por (conversación + clave). Si reenvía
            // el mismo comprobante, escala en silencio.
            if (!$this->duplicateAlreadyNotified($message, $session, $clave)) {
                $text = $status === 'applied'
                    ? '¡Gracias! 🧾 Este pago ya lo tenemos registrado y aplicado en la cuenta. No necesitas hacer nada más. Si crees que falta algo, nuestro equipo puede revisarlo. 🙌'
                    : '¡Gracias por tu comprobante! 🧾 Este pago ya figura en nuestros registros, así que nuestro equipo lo está validando manualmente para confirmar que se aplique en la cuenta correcta. Te avisamos en cuanto quede listo. 🙌';
                $this->respond($gateway, $message, $text);
            }
            return;
        }

        // F3 — inicia la sesión de identificación reutilizando el FSM tal cual.
        $session = Session::create([
            'is_simulation'          => false,
            'source'                 => 'gateway',
            'source_message_id'      => $message->id,
            'source_conversation_id' => $message->conversation_id,
            'extraction_id'          => $extraction->id,
            'conversation_id'        => $message->conversation_id,
            'message_id'             => $message->id,
            'state'                  => Session::STATE_DETECTING,
            'attempts'               => 0,
            'expires_at'             => $expiresAt,
        ]);

        $concepto = (string) ($result['fields']['concepto']['value'] ?? '');
        $titular  = $result['fields']['titular_ordenante']['value'] ?? null;
        $phone    = $message->conversation?->contact_number;

        $step = $fsm->start($session, $concepto, $titular, $phone);

        // Respuesta gateway-native (reemplaza el acuse genérico): el FSM ya genera
        // el mensaje correcto según identifique (o pida el número de cliente).
        $this->respond($gateway, $message, $step['outbound'] ?? null);

        Log::channel('evolution')->info('Conciliación gateway: intake procesado (F3)', [
            'source_message_id' => $message->id,
            'extraction_id'     => $extraction->id,
            'session_id'        => $session->id,
            'state'             => $session->fresh()->state,
            'certainty'         => $session->fresh()->certainty,
        ]);
    }

    /** Envía la respuesta del FSM por el gateway — SOLO si wa_autorespond está ON. */
    private function respond(WhatsAppGateway $gateway, WhatsAppMessage $message, ?string $text): void
    {
        if (empty($text)) {
            return;
        }
        if (!ConciliationSettings::enabled('wa_autorespond')) {
            Log::channel('evolution')->info('Conciliación gateway: respuesta SILENCIADA (wa_autorespond=false)', [
                'source_message_id' => $message->id,
                'preview'           => mb_substr($text, 0, 60),
            ]);
            return;
        }
        try {
            $slug  = $message->instance?->slug;
            $phone = $message->conversation?->contact_number;
            if ($slug && $phone) {
                $gateway->sendText($slug, $phone, $text);
            }
        } catch (\Throwable $e) {
            Log::channel('evolution')->warning('Conciliación gateway: fallo al enviar respuesta del FSM', [
                'source_message_id' => $message->id,
                'error'             => $e->getMessage(),
            ]);
        }
    }

    /**
     * "Una sola vez": ¿ya se avisó al cliente de un duplicado con esta clave en
     * ESTA conversación? Si existe una sesión gateway escalada por duplicado
     * (duplicate_clave o duplicate_applied) PREVIA (id menor) en la misma
     * conversación con la misma clave, no re-avisar (reenvíos → escalan en
     * silencio). Sin clave (huella) → una sola vez por conversación.
     */
    private function duplicateAlreadyNotified(WhatsAppMessage $message, Session $current, string $clave): bool
    {
        $q = Session::where('source', 'gateway')
            ->whereIn('escalation_reason', ['duplicate_clave', 'duplicate_applied'])
            ->where('source_conversation_id', $message->conversation_id)
            ->where('id', '<', $current->id);

        if ($clave !== '') {
            $extIds = WhatsappPaymentExtraction::where('source', 'gateway')
                ->where(function ($w) use ($clave) {
                    $w->where('fields->clave_rastreo->value', $clave)
                      ->orWhere('fields->referencia->value', $clave);
                })
                ->pluck('id');
            $q->whereIn('extraction_id', $extIds);
        }

        return $q->exists();
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

    /**
     * Clasifica el duplicado de una CLAVE (3 tiers). SOLO LECTURA — no toca el
     * candado de dinero (claveAlreadyUsed/apply), solo lee las mismas tablas.
     *  - 'applied'      → la clave YA está aplicada (payments.number o
     *                     reported_payments.clave_rastreo). El más importante.
     *  - 'pending_seen' → vista en una extracción gateway PREVIA (id < actual),
     *                     en CUALQUIER conversación (caza reenvíos del mismo número).
     *  - 'none'         → no es duplicado.
     */
    private function classifyClaveDuplicate(string $clave, int $currentExtractionId): string
    {
        if ($clave === '') {
            return 'none';
        }

        $applied = DB::table('reported_payments')->where('clave_rastreo', $clave)->whereNull('deleted_at')->exists()
            || DB::table('payments')->where('number', $clave)->whereNull('deleted_at')->exists();
        if ($applied) {
            return 'applied';
        }

        $seen = WhatsappPaymentExtraction::where('source', 'gateway')
            ->where('id', '<', $currentExtractionId)
            ->whereNull('discarded_at')
            ->where(function ($q) use ($clave) {
                $q->where('fields->clave_rastreo->value', $clave)
                  ->orWhere('fields->referencia->value', $clave);
            })
            ->exists();

        return $seen ? 'pending_seen' : 'none';
    }

    /**
     * Sin clave: clasifica el duplicado por HUELLA (monto+fecha+banco), simétrico
     * a la clave.
     *  - 'applied'      → la huella YA está aplicada (reported_payments.dedup_fingerprint,
     *                     mismo formato que el motor de dinero).
     *  - 'pending_seen' → misma huella en una extracción gateway PREVIA (id < actual).
     *  - 'none'         → no es duplicado o la huella es demasiado débil.
     */
    private function classifyFingerprintDuplicate(array $fields, int $currentExtractionId): string
    {
        $fp = $this->buildFingerprint($fields);
        if ($fp !== null) {
            $applied = DB::table('reported_payments')
                ->where('dedup_fingerprint', $fp)->whereNull('deleted_at')->exists();
            if ($applied) {
                return 'applied';
            }
        }

        $monto = trim((string) ($fields['monto']['value'] ?? ''));
        $fecha = trim((string) ($fields['fecha_pago']['value'] ?? ''));
        $banco = trim((string) ($fields['banco_origen']['value'] ?? ''));
        if ($monto === '' || ($fecha === '' && $banco === '')) {
            return 'none'; // huella débil → no marca duplicidad (evita falsos positivos)
        }

        $q = WhatsappPaymentExtraction::where('source', 'gateway')
            ->where('id', '<', $currentExtractionId)
            ->whereNull('discarded_at')
            ->where('fields->monto->value', $fields['monto']['value']);
        if ($fecha !== '') { $q->where('fields->fecha_pago->value', $fields['fecha_pago']['value']); }
        if ($banco !== '') { $q->where('fields->banco_origen->value', $fields['banco_origen']['value']); }

        return $q->exists() ? 'pending_seen' : 'none';
    }

    /**
     * Huella anti-duplicado con el MISMO formato que el motor de dinero
     * (PaymentFromSessionService::paymentFingerprint) para que el Tier-1 case
     * contra reported_payments.dedup_fingerprint. Null si es demasiado débil.
     */
    private function buildFingerprint(array $fields): ?string
    {
        $monto  = $fields['monto']['value'] ?? null;
        $amount = ($monto !== null && is_numeric(str_replace(',', '', (string) $monto)))
            ? (float) str_replace(',', '', (string) $monto) : null;
        $fecha = $this->norm($fields['fecha_pago']['value'] ?? null);
        $banco = $this->norm($fields['banco_origen']['value'] ?? null);
        if ($amount === null || ($fecha === '' && $banco === '')) {
            return null;
        }
        return 'FP:' . number_format($amount, 2, '.', '') . '|' . $fecha . '|' . $banco;
    }

    /** Igual que PaymentFromSessionService::norm (minúsculas + colapsa espacios + trim). */
    private function norm(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $s)));
    }
}
