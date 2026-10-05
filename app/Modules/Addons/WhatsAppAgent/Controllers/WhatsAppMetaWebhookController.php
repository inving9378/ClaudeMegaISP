<?php

namespace App\Modules\Addons\WhatsAppAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Services\MetaCloudApiService;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de la API OFICIAL de WhatsApp (Meta Cloud API) -- hermano de
 * {@see WhatsAppWebhookController}, que es el de Evolution. Dos rutas
 * distintas a propósito: el formato de payload, la autenticación y el
 * handshake de verificación son completamente distintos entre proveedores
 * (ver WhatsAppProviderInterface para el porqué), así que mezclarlos en un
 * solo controller solo complicaría ambos casos sin ganar nada.
 *
 * Ruta PÚBLICA (sin auth de framework) -- listada en PUBLIC_ROUTES de
 * CheckRoutePermission, igual que el webhook de Evolution.
 */
class WhatsAppMetaWebhookController extends Controller
{
    /**
     * GET /whatsapp/webhook/meta/{slug}
     * Handshake de verificación que Meta manda UNA vez al guardar la URL del
     * webhook en developers.facebook.com. Debe responder con el `hub.challenge`
     * tal cual, en texto plano (NO json), o Meta rechaza la configuración.
     */
    public function verify(Request $request, string $slug): Response|JsonResponse
    {
        $instance = WhatsAppInstance::where('slug', $slug)->where('active', true)->first();
        if (!$instance) {
            return response()->json(['error' => 'Instance not found'], 404);
        }

        $meta = new MetaCloudApiService((int) ($instance->company_id ?? 1));

        $challenge = $meta->verifyHandshake(
            $request->query('hub_mode') ?? $request->query('hub.mode'),
            $request->query('hub_verify_token') ?? $request->query('hub.verify_token'),
            $request->query('hub_challenge') ?? $request->query('hub.challenge'),
        );

        if ($challenge === null) {
            Log::warning('WhatsApp Meta webhook verify_token mismatch', ['slug' => $slug]);
            return response()->json(['error' => 'Verification failed'], 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * POST /whatsapp/webhook/meta/{slug}
     * Mensajes entrantes reales. Autenticación por firma HMAC-SHA256
     * (header X-Hub-Signature-256), NO por secreto compartido simple como
     * Evolution -- hay que verificar sobre el BODY crudo, antes de que
     * Laravel lo parsee a array (por eso $request->getContent()).
     */
    public function handle(Request $request, string $slug, WhatsAppRouterService $router): JsonResponse
    {
        $instance = WhatsAppInstance::where('slug', $slug)->where('active', true)->first();
        if (!$instance) {
            return response()->json(['error' => 'Instance not found'], 404);
        }

        $meta = new MetaCloudApiService((int) ($instance->company_id ?? 1));

        $signature = $request->header('X-Hub-Signature-256');
        if (!$meta->verifySignature($request->getContent(), $signature)) {
            Log::warning('WhatsApp Meta webhook signature mismatch', ['slug' => $slug]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $router->routeMeta($instance, $meta, $request->all());
        } catch (\Throwable $e) {
            Log::error('WhatsApp Meta webhook error', [
                'error'   => $e->getMessage(),
                'payload' => $request->all(),
            ]);
        }

        // Meta espera 200 rápido (si no, reintenta el POST) -- igual que Evolution.
        return response()->json(['status' => 'ok']);
    }
}
