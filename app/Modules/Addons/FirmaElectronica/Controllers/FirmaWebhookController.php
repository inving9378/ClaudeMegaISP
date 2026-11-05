<?php

namespace App\Modules\Addons\FirmaElectronica\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\FirmaElectronica\Contracts\FirmaProviderInterface;
use App\Modules\Addons\FirmaElectronica\Models\FirmaSolicitud;
use App\Modules\Addons\FirmaElectronica\Services\FirmaSolicitudService;
use Illuminate\Http\Request;

/** Webhook del proveedor: autenticado con firma HMAC (hash_equals), idempotente por evento. */
class FirmaWebhookController extends Controller
{
    public function __invoke(Request $request, FirmaProviderInterface $proveedor, FirmaSolicitudService $svc)
    {
        if (! $proveedor->verificarWebhook($request->getContent(), $request->header('X-Firma-Signature'))) {
            return response()->json(['ok' => false], 401);
        }
        $d = $request->json()->all();
        $s = FirmaSolicitud::where('id_externo', $d['id_externo'] ?? '')->first();
        if (! $s || empty($d['evento_id']) || empty($d['tipo'])) {
            return response()->json(['ok' => false], 422);
        }
        $nuevo = $svc->aplicarEvento($s, (string) $d['evento_id'], (string) $d['tipo'], (array) ($d['payload'] ?? []));
        return response()->json(['ok' => true, 'duplicado' => ! $nuevo]);
    }
}
