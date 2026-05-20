<?php

namespace App\Modules\Addons\MegaFamilia\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente FCM (legacy server-key HTTP API).
 *
 * Google deprecó la API legacy en favor de HTTP v1 (OAuth2 + service account),
 * pero el endpoint legacy sigue funcionando y es notablemente más simple para
 * envíos de "fan-out" a tokens conocidos. Cuando se requiera HTTP v1, esta
 * clase se reemplaza pero el resto del módulo no debería cambiar.
 *
 * Server key se lee desde Cache::get('megafamilia:settings').firebase_server_key
 * (que escribe ConfiguracionController), o como fallback de env FCM_SERVER_KEY.
 */
class FcmService
{
    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send';
    private const BATCH_SIZE = 1000; // FCM legacy registration_ids cap

    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'Sin tokens destino', 'sent' => 0];
        }

        $serverKey = $this->serverKey();
        if (! $serverKey) {
            return [
                'success' => false,
                'error' => 'firebase_server_key no configurado en Configuración MegaFamilia ni en FCM_SERVER_KEY env',
                'sent' => 0,
            ];
        }

        $results = ['success' => true, 'sent' => 0, 'failed' => 0, 'batches' => []];

        foreach (array_chunk($tokens, self::BATCH_SIZE) as $batch) {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post(self::ENDPOINT, [
                'registration_ids' => $batch,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $data,
                'priority' => 'high',
            ]);

            $batchResult = [
                'http_status' => $response->status(),
                'count' => count($batch),
            ];

            if (! $response->successful()) {
                $batchResult['error'] = mb_substr($response->body(), 0, 300);
                $results['failed'] += count($batch);
                $results['success'] = false;
                Log::warning('FCM batch failed', $batchResult);
            } else {
                $payload = $response->json();
                $batchResult['fcm_success'] = (int) ($payload['success'] ?? 0);
                $batchResult['fcm_failure'] = (int) ($payload['failure'] ?? 0);
                $results['sent'] += $batchResult['fcm_success'];
                $results['failed'] += $batchResult['fcm_failure'];
            }

            $results['batches'][] = $batchResult;
        }

        return $results;
    }

    private function serverKey(): ?string
    {
        $settings = Cache::get('megafamilia:settings', []);
        $key = $settings['firebase_server_key'] ?? null;
        if (! $key) {
            $key = env('FCM_SERVER_KEY');
        }
        return $key ?: null;
    }
}
