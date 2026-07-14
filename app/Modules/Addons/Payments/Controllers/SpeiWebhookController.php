<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Payments\Models\PaymentProvider;
use App\Modules\Addons\Payments\Models\PaymentWebhookLog;
use App\Modules\Addons\Payments\Services\OpenPayService;
use App\Modules\Addons\Payments\Services\PaymentApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Endpoint público para webhooks de OpenPay (POST /payments/spei/webhook).
 *
 * Diseño:
 *   1. Log INMEDIATAMENTE el evento (antes de validar firma) — así cualquier
 *      intento, válido o no, queda en bitácora.
 *   2. Validar firma (HTTP Basic Auth — OpenPay manda el password configurado
 *      al alta del webhook). Si falla → marcar `signature_invalid` + 401.
 *   3. Idempotencia: lookup en PaymentWebhookLog por (provider, external_id).
 *      Si ya existe un row processed para esa transacción, responder 200
 *      sin duplicar pago.
 *   4. Buscar cliente por CLABE → aplicar pago → notificar. La reactivación de
 *      servicio NO se dispara aquí: applyPayment() registra paymentable=Client,
 *      lo que ya dispara PaymentObserver→PaymentClientJob→ClientBillingService::
 *      billing() (RUTA AUTORITATIVA, única — roadmap #160), que solo reactiva si
 *      el balance cubre el costo. Igual que ConciliacionService (Portal de Pago).
 *   5. Respuesta SIEMPRE bajo 5s (la notificación se hace mejor effort pero no
 *      espera).
 *
 * Errores no-bloqueantes (WhatsApp falla) NO abortan: el pago ya quedó
 * registrado en BD. Los logueamos y seguimos. Errores reales (CLABE no
 * encontrada, payload inválido) → status=failed, OpenPay no retry (devolvemos
 * 200 porque ya está en bitácora; reintento manual desde admin).
 */
class SpeiWebhookController extends Controller
{
    public function __construct(
        private OpenPayService $openpay,
        private PaymentApplicationService $paymentApp,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $externalId = $this->extractExternalId($payload);

        // 1) Log inmediato — antes incluso de validar firma
        $log = PaymentWebhookLog::create([
            'provider'    => 'openpay',
            'event_type'  => (string) ($payload['type'] ?? 'unknown'),
            'external_id' => $externalId,
            'payload'     => $payload,
            'status'      => 'pending',
        ]);

        try {
            // 2) Buscar provider OpenPay activo
            $provider = PaymentProvider::where('provider', 'openpay')
                ->where('is_active', true)
                ->first();

            if (!$provider) {
                return $this->failLog($log, 'No hay proveedor OpenPay activo configurado.', 503);
            }

            $cfg = $provider->config ?? [];
            $webhookSecret = (string) ($cfg['webhook_secret'] ?? '');
            if ($webhookSecret === '') {
                return $this->failLog($log, 'Provider sin webhook_secret configurado.', 503);
            }

            // 3) Validar autenticación del webhook (Basic Auth)
            if (!$this->openpay->verifyWebhookAuth($request, $webhookSecret)) {
                $log->update([
                    'status'        => 'signature_invalid',
                    'error_message' => 'HTTP Basic Auth mismatch',
                    'processed_at'  => now(),
                ]);
                Log::warning('SPEI webhook: firma inválida', [
                    'external_id' => $externalId,
                    'log_id'      => $log->id,
                ]);
                return response()->json(['ok' => false, 'reason' => 'signature_invalid'], 401);
            }

            // 4) Idempotencia — si ya procesamos este transaction_id, no duplicar
            if ($externalId) {
                $prior = PaymentWebhookLog::where('provider', 'openpay')
                    ->where('external_id', $externalId)
                    ->where('status', 'processed')
                    ->where('id', '!=', $log->id)
                    ->first();
                if ($prior) {
                    $log->update([
                        'status'       => 'duplicate',
                        'payment_id'   => $prior->payment_id,
                        'processed_at' => now(),
                    ]);
                    return response()->json([
                        'ok'         => true,
                        'duplicate'  => true,
                        'payment_id' => $prior->payment_id,
                    ]);
                }
            }

            // 5) Solo procesar eventos relevantes — ignoramos charge.created, etc.
            $eventType = (string) ($payload['type'] ?? '');
            if (!$this->isPaymentSuccessEvent($eventType)) {
                $log->update([
                    'status'       => 'processed',
                    'processed_at' => now(),
                    'error_message' => "evento ignorado ({$eventType})",
                ]);
                return response()->json(['ok' => true, 'ignored' => $eventType]);
            }

            // 6) Encontrar cliente vía CLABE
            $clabe = (string) ($payload['transaction']['payment_method']['clabe'] ?? '');
            if ($clabe === '') {
                return $this->failLog($log, 'Payload sin CLABE en transaction.payment_method.clabe', 200);
            }
            $client = $this->paymentApp->findClientByClabe($clabe);
            if (!$client) {
                return $this->failLog($log, "CLABE {$clabe} no asignada a ningún cliente.", 200);
            }

            // 7) Aplicar pago
            $amount = (float) ($payload['transaction']['amount'] ?? 0);
            if ($amount <= 0) {
                return $this->failLog($log, "Amount inválido: {$amount}", 200);
            }
            $payment = $this->paymentApp->applyPayment([
                'client_id'   => $client->id,
                'amount'      => $amount,
                'external_id' => $externalId,
                'provider'    => 'openpay',
                'comment'     => "SPEI OpenPay — depósito a CLABE {$clabe}",
            ]);

            // 8) Side effects (no-bloqueantes — errors no abortan el webhook)
            // NOTA (roadmap #160): NO se llama activateClientService() aquí — esa
            // ruta secundaria forzaba TODOS los servicios a 'Pagado' sin checar
            // balance, pudiendo reactivar servicio con un pago insuficiente. La
            // ÚNICA ruta de reactivación es la autoritativa, disparada por
            // applyPayment() vía PaymentObserver→PaymentClientJob→
            // ClientBillingService::billing() (igual que ConciliacionService).
            try {
                $this->paymentApp->notifyClient($client, $payment);
            } catch (Throwable $e) {
                Log::warning('SPEI webhook: notifyClient falló', [
                    'error'   => $e->getMessage(),
                    'log_id'  => $log->id,
                ]);
            }
            try {
                $this->paymentApp->saveReceipt($payment, $payload);
            } catch (Throwable $e) {
                Log::warning('SPEI webhook: saveReceipt falló', [
                    'error'   => $e->getMessage(),
                    'log_id'  => $log->id,
                ]);
            }

            // 9) Marcar log como procesado
            $log->update([
                'status'       => 'processed',
                'payment_id'   => $payment->id,
                'processed_at' => now(),
            ]);

            return response()->json([
                'ok'         => true,
                'payment_id' => $payment->id,
            ]);
        } catch (Throwable $e) {
            return $this->failLog($log, $e->getMessage(), 200);
        }
    }

    /* ============================================================
     |  Helpers
     * ============================================================ */

    private function extractExternalId(array $payload): ?string
    {
        return $payload['transaction']['id'] ?? $payload['id'] ?? null;
    }

    /**
     * Eventos de OpenPay que disparan aplicación de pago. Otros (created,
     * pending, refunded) se ignoran (loguean pero no procesan).
     */
    private function isPaymentSuccessEvent(string $eventType): bool
    {
        return in_array($eventType, [
            'charge.succeeded',
            'transaction.completed',
            'transaction.charged',
        ], true);
    }

    private function failLog(PaymentWebhookLog $log, string $reason, int $httpStatus): JsonResponse
    {
        $log->update([
            'status'        => 'failed',
            'error_message' => $reason,
            'processed_at'  => now(),
        ]);
        Log::error('SPEI webhook failed', [
            'log_id'      => $log->id,
            'external_id' => $log->external_id,
            'reason'      => $reason,
        ]);
        // Devolvemos 200 (no 5xx) por default para que OpenPay NO reintente —
        // ya quedó en bitácora y la mayoría de fails son lógicos (CLABE no asignada,
        // monto inválido, etc.) que no se resuelven con retry. Excepción: 401
        // explícito cuando es signature_invalid, y 503 si falta config básica.
        return response()->json(['ok' => false, 'reason' => $reason], $httpStatus);
    }
}
