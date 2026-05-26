<?php

namespace App\Modules\Addons\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Payments\Models\PaymentProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * CRUD de payment_providers (configuración de proveedores externos).
 *
 * Seguridad clave:
 *   - index() NUNCA expone el config completo — solo merchant_id y sandbox.
 *     api_key, webhook_secret y otros secretos se devuelven SOLO en show()
 *     a usuarios con permission 'payments_manage_providers'.
 *   - store/update reciben el config completo y el modelo lo encripta vía
 *     cast 'encrypted:json'.
 */
class PaymentProviderController extends Controller
{
    public function index(): JsonResponse
    {
        $providers = PaymentProvider::query()
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (PaymentProvider $p) => $this->summary($p));

        return response()->json(['success' => true, 'providers' => $providers]);
    }

    public function show(int $id): JsonResponse
    {
        $provider = PaymentProvider::findOrFail($id);
        // En show sí devolvemos config completo — pero el permission middleware
        // ya gateó la ruta a usuarios con payments_manage_providers.
        return response()->json([
            'success'  => true,
            'provider' => array_merge($this->summary($provider), [
                'config' => $provider->config ?? [],
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);
        try {
            $provider = PaymentProvider::create($data);
            return response()->json([
                'success'  => true,
                'message'  => 'Proveedor creado.',
                'provider' => $this->summary($provider),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $provider = PaymentProvider::findOrFail($id);
        $data = $this->validatedPayload($request, $id);

        // Si config viene vacío en el request, NO sobrescribimos el existente
        // (UX: el form deja los campos password vacíos cuando el usuario no
        // quiere rotar credenciales). Solo actualizamos si llegan claves.
        if (empty($data['config'])) {
            unset($data['config']);
        }

        try {
            $provider->update($data);
            return response()->json([
                'success'  => true,
                'message'  => 'Proveedor actualizado.',
                'provider' => $this->summary($provider->fresh()),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $provider = PaymentProvider::findOrFail($id);
        $provider->delete(); // soft delete
        return response()->json(['success' => true, 'message' => 'Proveedor desactivado (soft delete).']);
    }

    /* ============================================================
     |  Helpers
     * ============================================================ */

    private function summary(PaymentProvider $p): array
    {
        $cfg = $p->config ?? [];
        // Solo metadatos del config — los secretos quedan ocultos
        $cfgPreview = [
            'merchant_id'  => $cfg['merchant_id'] ?? null,
            'sandbox'      => $cfg['sandbox'] ?? false,
            'has_api_key'  => !empty($cfg['api_key']),
            'has_webhook'  => !empty($cfg['webhook_secret']),
        ];
        return [
            'id'           => $p->id,
            'name'         => $p->name,
            'provider'     => $p->provider,
            'is_active'    => $p->is_active,
            'config_meta'  => $cfgPreview,
            'created_at'   => $p->created_at,
            'updated_at'   => $p->updated_at,
        ];
    }

    private function validatedPayload(Request $request, ?int $id = null): array
    {
        $validator = Validator::make($request->all(), [
            'name'                    => 'required|string|max:255',
            'provider'                => 'required|string|in:openpay,stripe,paypal,conekta,spei_manual',
            'is_active'               => 'sometimes|boolean',
            'config'                  => 'sometimes|array',
            'config.merchant_id'      => 'sometimes|string|max:255',
            'config.api_key'          => 'sometimes|string|max:255',
            'config.webhook_secret'   => 'sometimes|string|max:255',
            'config.sandbox'          => 'sometimes|boolean',
        ]);
        $validator->validate();

        return [
            'name'      => $request->input('name'),
            'provider'  => $request->input('provider'),
            'is_active' => (bool) $request->input('is_active', false),
            'config'    => $request->input('config', []),
        ];
    }
}
