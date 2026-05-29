<?php

namespace App\Modules\Addons\Marketing\Services\Publishing\Drivers;

use App\Models\Marketing\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPageDriver extends AbstractPublishDriver
{
    private const GRAPH = 'https://graph.facebook.com/v18.0';
    private const GRAPH_VIDEO = 'https://graph-video.facebook.com/v18.0';

    public function getRequiredCredentials(): array
    {
        return ['page_id', 'page_access_token'];
    }

    public function validateCredentials(): array
    {
        $config = $this->channel->platform_config ?? [];
        if (empty($config['page_id']) || empty($config['page_access_token'])) {
            return ['valid' => false, 'message' => 'Falta page_id o page_access_token'];
        }

        $response = Http::timeout(10)->get(self::GRAPH . '/me', [
            'access_token' => $config['page_access_token'],
        ]);

        if ($response->successful()) {
            return ['valid' => true, 'message' => 'Token válido'];
        }

        $error = $response->json()['error']['message'] ?? $response->body();
        return ['valid' => false, 'message' => "Meta API: {$error}"];
    }

    public function publish(Publication $pub): array
    {
        $config    = $this->channel->platform_config;
        $content   = $pub->content;
        $videoPath = $this->videoPath($content);

        if (!file_exists($videoPath)) {
            return ['success' => false, 'error' => "Video no encontrado: {$videoPath}"];
        }

        $caption = $this->buildCaption($pub);

        try {
            $response = Http::timeout(120)
                ->attach('source', fopen($videoPath, 'r'), basename($videoPath))
                ->post(self::GRAPH_VIDEO . "/{$config['page_id']}/videos", [
                    'access_token' => $config['page_access_token'],
                    'description'  => $caption,
                    'published'    => 'true',
                ]);

            if (!$response->successful()) {
                $error = $response->json()['error']['message'] ?? $response->body();
                Log::error('[FB] publish failed', ['error' => $error, 'pub' => $pub->id]);
                return ['success' => false, 'error' => $error];
            }

            $postId = $response->json()['id'] ?? null;
            return [
                'success'          => true,
                'external_post_id' => $postId,
                'external_post_url'=> "https://www.facebook.com/{$config['page_id']}/posts/{$postId}",
            ];
        } catch (\Throwable $e) {
            Log::error('[FB] publish exception', ['e' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function fetchMetrics(Publication $pub): array
    {
        $config = $this->channel->platform_config;
        if (!$pub->external_post_id || empty($config['page_access_token'])) {
            return [];
        }

        $response = Http::timeout(10)->get(self::GRAPH . "/{$pub->external_post_id}", [
            'fields'       => 'likes.summary(true),comments.summary(true),shares,reactions.summary(true)',
            'access_token' => $config['page_access_token'],
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        return [
            'likes'     => $data['likes']['summary']['total_count'] ?? 0,
            'comments'  => $data['comments']['summary']['total_count'] ?? 0,
            'shares'    => $data['shares']['count'] ?? 0,
            'reactions' => $data['reactions']['summary']['total_count'] ?? 0,
        ];
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
