<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Jobs\ProcessMetaLeadJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaAdsWebhookController extends Controller
{
    public function handle(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        if ($request->isMethod('get')) {
            return $this->verify($request);
        }
        return $this->receive($request);
    }

    private function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $storedToken = Setting::get('meta_verify_token') ?? '';

        if ($mode === 'subscribe' && hash_equals($storedToken, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[Meta Webhook] Verification failed', ['mode' => $mode]);
        return response('Forbidden', 403)->header('Content-Type', 'text/plain');
    }

    private function receive(Request $request): \Illuminate\Http\JsonResponse
    {
        $signature = $request->header('X-Hub-Signature-256', '');
        $appSecret = Setting::get('meta_app_secret') ?? '';
        $rawBody   = $request->getContent();

        if ($appSecret === '') {
            Log::error('[Meta Webhook] meta_app_secret vacío: webhook rechazado (fail-closed)');
            return response()->json(['error' => 'config'], 503);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

        if (!hash_equals($expected, $signature)) {
            Log::warning('[Meta Webhook] Invalid HMAC signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();
        $object  = $payload['object'] ?? '';

        if ($object !== 'page') {
            return response()->json(['status' => 'ignored']);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'leadgen') {
                    continue;
                }
                $value = $change['value'] ?? [];
                ProcessMetaLeadJob::dispatch(
                    $value['leadgen_id'] ?? '',
                    $value['form_id'] ?? '',
                    $value['page_id'] ?? '',
                    $value['ad_id'] ?? null,
                    $value['adgroup_id'] ?? null,
                    (int) ($value['created_time'] ?? time()),
                )->onQueue('default');
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
