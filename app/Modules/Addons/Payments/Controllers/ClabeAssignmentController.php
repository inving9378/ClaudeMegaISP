<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Modules\Addons\Payments\Exceptions\ClabeEmissionDisabledException;
use App\Modules\Addons\Payments\Models\PaymentClabe;
use App\Modules\Addons\Payments\Models\PaymentProvider;
use App\Modules\Addons\Payments\Models\PaymentWebhookLog;
use App\Modules\Addons\Payments\Services\OpenPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asignación y consulta de CLABE virtual por cliente.
 *
 * Endpoints:
 *   GET  /finanzas/clients/{id}/clabe         → ficha (clabe + historial)
 *   POST /finanzas/clients/{id}/assign-clabe  → genera CLABE en OpenPay y persiste
 */
class ClabeAssignmentController extends Controller
{
    public function __construct(
        private OpenPayService $openpay
    ) {}

    /**
     * Datos para el ClientClabeCard.vue: CLABE actual + últimos 10 pagos.
     */
    public function show(int $clientId): JsonResponse
    {
        $client = Client::findOrFail($clientId);

        $clabe = PaymentClabe::where('client_id', $client->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $recentPayments = Payment::where('paymentable_type', Client::class)
            ->where('paymentable_id', $client->id)
            ->where('payment_method_id', $this->speiMethodId())
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['id', 'number', 'amount', 'date', 'comment', 'created_at']);

        $webhooks = PaymentWebhookLog::query()
            ->whereIn('payment_id', $recentPayments->pluck('id'))
            ->get(['id', 'status', 'external_id', 'payment_id', 'processed_at'])
            ->keyBy('payment_id');

        return response()->json([
            'success'         => true,
            'client_id'       => $client->id,
            'emission_enabled' => (bool) config('openpay.clabe_emission_enabled', false),
            'clabe'           => $clabe ? [
                'id'         => $clabe->id,
                'clabe'      => $clabe->clabe,
                'openpay_id' => $clabe->openpay_id,
                'is_active'  => $clabe->is_active,
                'created_at' => $clabe->created_at,
            ] : null,
            'recent_payments' => $recentPayments->map(fn ($p) => [
                'id'         => $p->id,
                'number'     => $p->number,
                'amount'     => $p->amount,
                'date'       => $p->date,
                'comment'    => $p->comment,
                'created_at' => $p->created_at,
                'webhook'    => $webhooks->get($p->id),
            ]),
        ]);
    }

    /**
     * Crea CLABE virtual para el cliente vía OpenPay y la guarda en BD.
     */
    public function assign(int $clientId): JsonResponse
    {
        $client = Client::findOrFail($clientId);

        // Idempotencia básica: si el cliente ya tiene CLABE activa, devolverla
        $existing = PaymentClabe::where('client_id', $client->id)->where('is_active', true)->first();
        if ($existing) {
            return response()->json([
                'success'  => true,
                'message'  => 'El cliente ya tiene CLABE activa.',
                'clabe'    => $existing->clabe,
                'reused'   => true,
            ]);
        }

        $provider = PaymentProvider::where('provider', 'openpay')->where('is_active', true)->first();
        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'No hay proveedor OpenPay activo. Configúralo en /finanzas/payment-providers.',
            ], 422);
        }

        try {
            $result = $this->openpay->createClabe($client, $provider);
            $clabe = PaymentClabe::create([
                'client_id'           => $client->id,
                'clabe'               => $result['clabe'],
                'openpay_id'          => $result['openpay_id'] ?? null,
                'payment_provider_id' => $provider->id,
                'is_active'           => true,
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'CLABE asignada correctamente.',
                'clabe'     => $clabe->clabe,
                'is_stub'   => !empty($result['_stub']),
                'reused'    => false,
            ], 201);
        } catch (ClabeEmissionDisabledException $e) {
            // Gate de emisión: feature pendiente de activación → 503, mensaje amable.
            return response()->json([
                'success' => false,
                'code'    => 'clabe_emission_disabled',
                'message' => ClabeEmissionDisabledException::USER_MESSAGE,
            ], 503);
        } catch (Throwable $e) {
            // No filtrar el mensaje crudo de BD/proveedor al usuario; queda en el log.
            Log::error('ClabeAssignment: createClabe falló', [
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar la CLABE en este momento. Intenta de nuevo más tarde.',
            ], 500);
        }
    }

    private function speiMethodId(): ?int
    {
        return \App\Models\MethodOfPayment::where('type', 'SPEI OpenPay')->value('id');
    }
}
