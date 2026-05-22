<?php

namespace App\Modules\Addons\WhatsAppAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * POST /whatsapp/webhook/{slug}
     * Ruta PÚBLICA (sin auth). Validación por X-Webhook-Secret / apikey header
     * contra el secret persistido en whatsapp_instances.
     */
    public function handle(Request $request, string $slug): JsonResponse
    {
        $instance = WhatsAppInstance::where('slug', $slug)
            ->where('active', true)
            ->first();

        if (!$instance) {
            return response()->json(['error' => 'Instance not found'], 404);
        }

        $providedSecret = $request->header('X-Webhook-Secret')
            ?? $request->header('apikey')
            ?? null;

        if (!hash_equals((string) $instance->webhook_secret, (string) $providedSecret)) {
            Log::warning('WhatsApp webhook secret mismatch', ['slug' => $slug]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            app(WhatsAppRouterService::class)->route($instance, $request->all());
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook error', [
                'error'   => $e->getMessage(),
                'payload' => $request->all(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
