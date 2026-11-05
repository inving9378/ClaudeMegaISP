<?php

namespace App\Modules\Addons\MegaFamilia\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente FCM. Usa HTTP v1 si FIREBASE_PROJECT_ID + FIREBASE_CREDENTIALS_PATH apuntan a un
 * service account legible; si no, cae a la API legacy (server key).
 *
 * Google deprecó la API legacy en favor de HTTP v1 (OAuth2 + service account),
 * pero el endpoint legacy sigue funcionando y es notablemente más simple para
 * envíos de "fan-out" a tokens conocidos. Cuando se requiera HTTP v1, esta
 * clase se reemplaza pero el resto del módulo no debería cambiar.
 *
 * Server key se lee vía MegaFamiliaSettingsService (BD persistente, item #73),
 * o como fallback de env FCM_SERVER_KEY.
 */
class FcmService
{
    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send';
    private const BATCH_SIZE = 1000; // FCM legacy registration_ids cap

    public function __construct(private MegaFamiliaSettingsService $settings)
    {
    }

    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'Sin tokens destino', 'sent' => 0];
        }

        if ($this->v1Configured()) {
            return $this->sendV1($tokens, $title, $body, $data);
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

    /** HTTP v1 se activa solo si hay project_id + service account JSON legible (FIREBASE_*). */
    private function v1Configured(): bool
    {
        $path = config('services.firebase.credentials_path');
        return config('services.firebase.project_id') && $path && is_readable($path);
    }

    private function sendV1(array $tokens, string $title, string $body, array $data): array
    {
        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return ['success' => false, 'error' => 'No se pudo obtener access token OAuth2 de Firebase', 'sent' => 0];
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . config('services.firebase.project_id') . '/messages:send';
        $data = array_map('strval', $data); // v1 exige data string=>string
        $results = ['success' => true, 'sent' => 0, 'failed' => 0, 'batches' => []];

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)->timeout(15)->post($url, ['message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => (object) $data,
                'android' => ['priority' => 'high'],
            ]]);
            if ($response->successful()) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['success'] = false;
                Log::warning('FCM v1 envio fallido', ['http_status' => $response->status(), 'error' => mb_substr($response->body(), 0, 300)]);
            }
        }

        return $results;
    }

    /** Access token OAuth2 (JWT bearer firmado con la llave del service account), cacheado ~55 min. */
    private function accessToken(): ?string
    {
        return \Illuminate\Support\Facades\Cache::remember('megafamilia.fcm.v1.token', 3300, function () {
            $sa = json_decode((string) file_get_contents(config('services.firebase.credentials_path')), true);
            if ($sa === null || empty($sa['client_email']) || empty($sa['private_key'])) {
                return null;
            }
            $b64 = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
                'iss' => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (! openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }
            $res = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned . '.' . $b64($sig),
            ]);
            return $res->successful() ? $res->json('access_token') : null;
        });
    }

    private function serverKey(): ?string
    {
        $key = $this->settings->get('firebase_server_key');
        if (! $key) {
            $key = config('services.firebase.server_key');
        }
        return $key ?: null;
    }
}
