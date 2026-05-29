<?php

namespace App\Modules\Addons\Marketing\Services\Publishing\Drivers;

use App\Models\Marketing\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramReelsDriver extends AbstractPublishDriver
{
    private const GRAPH = 'https://graph.facebook.com/v18.0';

    public function getRequiredCredentials(): array
    {
        return ['ig_user_id', 'page_access_token'];
    }

    public function validateCredentials(): array
    {
        $config = $this->channel->platform_config ?? [];
        if (empty($config['ig_user_id']) || empty($config['page_access_token'])) {
            return ['valid' => false, 'message' => 'Falta ig_user_id o page_access_token'];
        }

        $response = Http::timeout(10)->get(self::GRAPH . "/{$config['ig_user_id']}", [
            'fields'       => 'id,name',
            'access_token' => $config['page_access_token'],
        ]);

        if ($response->successful()) {
            return ['valid' => true, 'message' => 'Token IG válido'];
        }

        $error = $response->json()['error']['message'] ?? $response->body();
        return ['valid' => false, 'message' => "Meta IG API: {$error}"];
    }

    public function publish(Publication $pub): array
    {
        $config  = $this->channel->platform_config;
        $content = $pub->content;

        // IG Graph API requiere URL pública del video, no subida directa
        $videoUrl = $this->publicVideoUrl($content);
        if (!$videoUrl) {
            return ['success' => false, 'error' => 'No se puede construir URL pública para el video'];
        }

        $caption = $this->buildCaption($pub);

        try {
            // Paso 1: Crear container
            $containerResp = Http::timeout(30)->post(
                self::GRAPH . "/{$config['ig_user_id']}/media",
                [
                    'access_token' => $config['page_access_token'],
                    'media_type'   => 'REELS',
                    'video_url'    => $videoUrl,
                    'caption'      => $caption,
                    'share_to_feed'=> 'true',
                ]
            );

            if (!$containerResp->successful()) {
                $error = $containerResp->json()['error']['message'] ?? $containerResp->body();
                return ['success' => false, 'error' => "IG container error: {$error}"];
            }

            $creationId = $containerResp->json()['id'] ?? null;
            if (!$creationId) {
                return ['success' => false, 'error' => 'IG no devolvió creation_id'];
            }

            // Esperar a que IG procese el video (poll hasta 60s)
            $this->waitForContainerReady($config['ig_user_id'], $creationId, $config['page_access_token']);

            // Paso 2: Publicar container
            $publishResp = Http::timeout(30)->post(
                self::GRAPH . "/{$config['ig_user_id']}/media_publish",
                [
                    'access_token' => $config['page_access_token'],
                    'creation_id'  => $creationId,
                ]
            );

            if (!$publishResp->successful()) {
                $error = $publishResp->json()['error']['message'] ?? $publishResp->body();
                return ['success' => false, 'error' => "IG publish error: {$error}"];
            }

            $mediaId = $publishResp->json()['id'] ?? null;
            return [
                'success'          => true,
                'external_post_id' => $mediaId,
                'external_post_url'=> "https://www.instagram.com/p/{$mediaId}/",
            ];
        } catch (\Throwable $e) {
            Log::error('[IGReels] exception', ['e' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function fetchMetrics(Publication $pub): array
    {
        $config = $this->channel->platform_config;
        if (!$pub->external_post_id || empty($config['page_access_token'])) {
            return [];
        }

        $response = Http::timeout(10)->get(self::GRAPH . "/{$pub->external_post_id}/insights", [
            'metric'       => 'plays,reach,likes,comments,shares,saved',
            'access_token' => $config['page_access_token'],
        ]);

        if (!$response->successful()) {
            return [];
        }

        $metrics = [];
        foreach ($response->json()['data'] ?? [] as $item) {
            $metrics[$item['name']] = $item['values'][0]['value'] ?? 0;
        }

        return [
            'views'    => $metrics['plays'] ?? 0,
            'reach'    => $metrics['reach'] ?? 0,
            'likes'    => $metrics['likes'] ?? 0,
            'comments' => $metrics['comments'] ?? 0,
            'shares'   => $metrics['shares'] ?? 0,
            'saved'    => $metrics['saved'] ?? 0,
        ];
    }

    private function publicVideoUrl($content): ?string
    {
        if ($content->output_url) {
            return $content->output_url;
        }

        // Build from output_path
        $path = ltrim($content->output_path ?? '', '/');
        if (!$path) {
            return null;
        }

        return url('storage/' . $path);
    }

    private function waitForContainerReady(string $igUserId, string $creationId, string $token): void
    {
        for ($i = 0; $i < 12; $i++) {
            sleep(5);
            $resp = Http::timeout(10)->get(self::GRAPH . "/{$creationId}", [
                'fields'       => 'status_code',
                'access_token' => $token,
            ]);

            $status = $resp->json()['status_code'] ?? 'IN_PROGRESS';
            if ($status === 'FINISHED') {
                return;
            }
            if ($status === 'ERROR') {
                throw new \RuntimeException('IG container processing failed');
            }
        }
    }

    private function buildCaption(Publication $pub): string
    {
        $caption  = $pub->caption ?? '';
        $hashtags = $pub->hashtags ?? [];
        if ($hashtags) {
            $caption .= "\n\n" . implode(' ', array_map(fn($h) => str_starts_with($h, '#') ? $h : "#{$h}", $hashtags));
        }
        return trim($caption);
    }
}
