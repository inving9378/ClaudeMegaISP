<?php

namespace App\Modules\Addons\Marketing\Services\Publishing\Drivers;

use App\Models\Marketing\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramStoriesDriver extends AbstractPublishDriver
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

        return $response->successful()
            ? ['valid' => true, 'message' => 'Token IG válido']
            : ['valid' => false, 'message' => $response->json()['error']['message'] ?? $response->body()];
    }

    public function publish(Publication $pub): array
    {
        $config   = $this->channel->platform_config;
        $content  = $pub->content;
        $videoUrl = $content->output_url ?? url('storage/' . ltrim($content->output_path ?? '', '/'));

        try {
            // Stories: media_type = VIDEO (no REELS, no IMAGE)
            $containerResp = Http::timeout(30)->post(
                self::GRAPH . "/{$config['ig_user_id']}/media",
                [
                    'access_token' => $config['page_access_token'],
                    'media_type'   => 'VIDEO',
                    'video_url'    => $videoUrl,
                    'is_stories'   => true,
                ]
            );

            if (!$containerResp->successful()) {
                $error = $containerResp->json()['error']['message'] ?? $containerResp->body();
                return ['success' => false, 'error' => "IG Stories container error: {$error}"];
            }

            $creationId = $containerResp->json()['id'] ?? null;

            for ($i = 0; $i < 12; $i++) {
                sleep(5);
                $statusResp = Http::timeout(10)->get(self::GRAPH . "/{$creationId}", [
                    'fields'       => 'status_code',
                    'access_token' => $config['page_access_token'],
                ]);
                $status = $statusResp->json()['status_code'] ?? 'IN_PROGRESS';
                if ($status === 'FINISHED') break;
                if ($status === 'ERROR') throw new \RuntimeException('IG Stories container failed');
            }

            $publishResp = Http::timeout(30)->post(
                self::GRAPH . "/{$config['ig_user_id']}/media_publish",
                [
                    'access_token' => $config['page_access_token'],
                    'creation_id'  => $creationId,
                ]
            );

            if (!$publishResp->successful()) {
                $error = $publishResp->json()['error']['message'] ?? $publishResp->body();
                return ['success' => false, 'error' => "IG Stories publish error: {$error}"];
            }

            $mediaId = $publishResp->json()['id'] ?? null;
            return [
                'success'          => true,
                'external_post_id' => $mediaId,
                'external_post_url'=> "https://www.instagram.com/stories/{$mediaId}/",
            ];
        } catch (\Throwable $e) {
            Log::error('[IGStories] exception', ['e' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
