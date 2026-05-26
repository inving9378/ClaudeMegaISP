<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Payment;
use App\Modules\Addons\Payments\Models\PaymentClabe;
use App\Modules\Addons\Payments\Models\PaymentProvider;
use App\Modules\Addons\Payments\Models\PaymentWebhookLog;
use App\Modules\Addons\Payments\Services\OpenPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Endpoints mobile-friendly del módulo Payments — consumidos por la app
 * Flutter MegaFamilia bajo /api/megafamilia/payments/*.
 *
 * Auth: middleware auth:sanctum (resuelto al ruta-level). Cliente activo se
 * deriva con Client::where('user_id', Auth::id()) — patrón consistente con
 * ApiController::facturas/pagos del módulo MegaFamilia.
 *
 * Naming en respuestas: snake_case y plano (la Flutter API espera ese
 * formato — sin objetos anidados profundos).
 */
class MobilePaymentController extends Controller
{
    public function __construct(
        private OpenPayService $openpay
    ) {}

    /**
     * GET /api/megafamilia/payments/clabe
     *
     * Devuelve la CLABE virtual del cliente. Si no tiene, intenta auto-generar
     * (siempre y cuando haya provider OpenPay activo). UX: el usuario abre la
     * pantalla y la CLABE aparece de una sin pasos extra.
     */
    public function getClabe(): JsonResponse
    {
        $client = $this->resolveClient();
        if (!$client) {
            return response()->json([
                'success' => false,
                'error'   => 'No hay cliente asociado a este usuario.',
            ], 404);
        }

        $clabe = PaymentClabe::where('client_id', $client->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        // Auto-generar si no existe
        if (!$clabe) {
            $provider = PaymentProvider::where('provider', 'openpay')
                ->where('is_active', true)
                ->first();
            if (!$provider) {
                return response()->json([
                    'success'        => true,
                    'has_clabe'      => false,
                    'reason'         => 'no_provider',
                    'message'        => 'Cobros SPEI aún no están habilitados. Contacta a tu proveedor.',
                ]);
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
            } catch (Throwable $e) {
                return response()->json([
                    'success' => false,
                    'error'   => 'No se pudo generar tu CLABE: ' . $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'success'      => true,
            'has_clabe'    => true,
            'clabe'        => $clabe->clabe,
            'clabe_format' => $this->formatClabe($clabe->clabe), // "646 180 12345678901234"
            'is_active'    => (bool) $clabe->is_active,
            'created_at'   => optional($clabe->created_at)->toIso8601String(),
            'beneficiario' => 'Meganet Telecomunicaciones',
            'banco'        => 'STP (vía OpenPay)',
            'concepto'     => "Cliente #{$client->id}",
        ]);
    }

    /**
     * POST /api/megafamilia/payments/notify-transfer
     *
     * El cliente notifica manualmente que hizo una transferencia. Sirve para
     * dos casos:
     *   1) El webhook automático de OpenPay aún no llega (latencia) y el
     *      cliente quiere notificarnos para que activemos su servicio.
     *   2) Pagó vía SPEI directo a la cuenta bancaria (no a su CLABE virtual)
     *      y necesita aportar el comprobante para conciliación manual.
     *
     * Guarda la notificación en payment_webhooks_log con provider='manual'.
     * Admin la revisa en la bitácora y aplica el pago manualmente (o cuando
     * llegue el webhook automático, el monto + depositor_name ayudan al match).
     *
     * Multipart fields: declared_amount (numeric), depositor_name (string),
     * receipt (file: jpg|png|pdf, max 5MB), reference (string, opcional).
     */
    public function notifyTransfer(Request $request): JsonResponse
    {
        $client = $this->resolveClient();
        if (!$client) {
            return response()->json([
                'success' => false,
                'error'   => 'No hay cliente asociado a este usuario.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'declared_amount' => 'required|numeric|min:0.01|max:999999.99',
            'depositor_name'  => 'required|string|max:255',
            'receipt'         => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB
            'reference'       => 'sometimes|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => $validator->errors()->first(),
                'fields'  => $validator->errors()->toArray(),
            ], 422);
        }

        // Guardar comprobante (storage/app/payments/spei/mobile/{client_id}/...)
        $file = $request->file('receipt');
        $dir = "payments/spei/mobile/{$client->id}";
        $stored = $file->store($dir, 'local');
        $size   = $file->getSize();
        $orig   = $file->getClientOriginalName();

        // Registrar como "notificación manual" en payment_webhooks_log para que
        // el admin la vea en la misma bitácora que los webhooks de OpenPay.
        $log = PaymentWebhookLog::create([
            'provider'    => 'manual',
            'event_type'  => 'client_notified_transfer',
            'external_id' => null, // se llena si admin lo matchea con webhook OpenPay
            'payload'     => [
                'client_id'       => $client->id,
                'user_id'         => Auth::id(),
                'declared_amount' => (float) $request->input('declared_amount'),
                'depositor_name'  => $request->input('depositor_name'),
                'reference'       => $request->input('reference'),
                'receipt'         => [
                    'path'          => $stored,
                    'original_name' => $orig,
                    'size'          => $size,
                    'mime'          => $file->getMimeType(),
                ],
                'submitted_at'    => now()->toIso8601String(),
                'source'          => 'mobile_app',
            ],
            'status' => 'pending', // admin revisa y resuelve
        ]);

        return response()->json([
            'success'         => true,
            'notification_id' => $log->id,
            'message'         => 'Gracias. Tu notificación fue registrada — un asesor verifica el comprobante y activará tu servicio.',
            'declared_amount' => (float) $request->input('declared_amount'),
            'submitted_at'    => $log->created_at->toIso8601String(),
        ], 201);
    }

    /* ============================================================
     |  Helpers
     * ============================================================ */

    /** Cliente del usuario Sanctum autenticado, o null. */
    private function resolveClient(): ?Client
    {
        return Client::where('user_id', Auth::id())->first();
    }

    /** 18 dígitos → "646 180 123456789012" para display. */
    private function formatClabe(string $clabe): string
    {
        if (strlen($clabe) !== 18) return $clabe;
        return substr($clabe, 0, 3) . ' ' . substr($clabe, 3, 3) . ' ' . substr($clabe, 6);
    }
}
