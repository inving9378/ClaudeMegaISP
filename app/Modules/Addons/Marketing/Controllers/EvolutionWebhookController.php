<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Jobs\ProcessIncomingMessageJob;
use App\Modules\Addons\Marketing\Jobs\UpdateMessageStatusJob;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EvolutionWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Validate token — Evolution v2 sends X-Webhook-Secret, v1 uses query ?token or X-Webhook-Token
        $incomingToken = $request->header('X-Webhook-Secret')
            ?? $request->query('token')
            ?? $request->header('X-Webhook-Token');
        $storedToken   = Setting::get('evolution_webhook_token', 1);

        if ($storedToken && $incomingToken && !hash_equals($storedToken, $incomingToken)) {
            Log::channel('evolution')->warning('Webhook token mismatch', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $event   = $payload['event'] ?? '';

        Log::channel('evolution')->debug('Webhook received', ['event' => $event]);

        match ($event) {
            'messages.upsert'    => $this->handleMessageUpsert($payload),
            'messages.update'    => $this->handleMessageUpdate($payload),
            'connection.update'  => $this->handleConnectionUpdate($payload),
            'qrcode.updated'     => $this->handleQrCode($payload),
            default              => null,
        };

        return response()->json(['ok' => true]);
    }

    private function handleMessageUpsert(array $payload): void
    {
        ProcessIncomingMessageJob::dispatch($payload)->onQueue('default');
    }

    private function handleMessageUpdate(array $payload): void
    {
        UpdateMessageStatusJob::dispatch($payload)->onQueue('default');
    }

    private function handleConnectionUpdate(array $payload): void
    {
        $state    = $payload['data']['state'] ?? '';
        $instance = $payload['instance'] ?? '';
        Log::channel('evolution')->info("Connection update [{$instance}]: state={$state}");

        if ($state === 'close') {
            Log::channel('evolution')->warning("Evolution instance '{$instance}' disconnected!");
        }
    }

    private function handleQrCode(array $payload): void
    {
        $instance = $payload['instance'] ?? 'default';
        $qr       = $payload['data']['qrcode']['base64'] ?? null;
        if ($qr) {
            Cache::put("evolution_qr_{$instance}", $qr, 60);
            Log::channel('evolution')->info("QR code updated for instance: {$instance}");
        }
    }
}
