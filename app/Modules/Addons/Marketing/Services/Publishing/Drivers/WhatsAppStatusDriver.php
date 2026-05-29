<?php

namespace App\Modules\Addons\Marketing\Services\Publishing\Drivers;

use App\Models\Marketing\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppStatusDriver extends AbstractPublishDriver
{
    public function getRequiredCredentials(): array
    {
        return ['evolution_url', 'evolution_api_key', 'evolution_instance'];
    }

    public function validateCredentials(): array
    {
        $config = $this->channel->platform_config ?? [];

        // Fallback to Hub evolution integration
        $evolutionUrl = $config['evolution_url']
            ?? $this->resolveApiKey('evolution', 'WHATSAPP_API_BASE')
            ?? env('WHATSAPP_API_BASE');

        $apiKey = $config['evolution_api_key']
            ?? $this->resolveApiKey('evolution', 'WHATSAPP_API_KEY')
            ?? env('WHATSAPP_API_KEY');

        $instance = $config['evolution_instance']
            ?? env('WHATSAPP_INSTANCE', 'meganet-ventas');

        if (!$evolutionUrl || !$apiKey || !$instance) {
            return ['valid' => false, 'message' => 'Faltan credenciales Evolution API'];
        }

        $response = Http::timeout(8)
            ->withHeaders(['apikey' => $apiKey])
            ->get(rtrim($evolutionUrl, '/') . "/instance/connectionState/{$instance}");

        if (!$response->successful()) {
            return ['valid' => false, 'message' => 'Evolution API no responde: ' . $response->status()];
        }

        $state = $response->json()['instance']['state'] ?? 'unknown';
        if ($state !== 'open') {
            return ['valid' => false, 'message' => "WhatsApp desconectado (estado: {$state}). Escanear QR primero."];
        }

        return ['valid' => true, 'message' => "WhatsApp conectado (estado: {$state})"];
    }

    public function publish(Publication $pub): array
    {
        $config    = $this->channel->platform_config ?? [];
        $content   = $pub->content;
        $videoPath = $this->videoPath($content);

        $evolutionUrl = rtrim(
            $config['evolution_url'] ?? env('WHATSAPP_API_BASE', ''),
            '/'
        );
        $apiKey   = $config['evolution_api_key'] ?? env('WHATSAPP_API_KEY', '');
        $instance = $config['evolution_instance'] ?? env('WHATSAPP_INSTANCE', 'meganet-ventas');

        if (!file_exists($videoPath)) {
            return ['success' => false, 'error' => "Video no encontrado: {$videoPath}"];
        }

        try {
            $base64  = base64_encode(file_get_contents($videoPath));
            $caption = $pub->caption ?? '';

            $response = Http::timeout(60)
                ->withHeaders(['apikey' => $apiKey])
                ->post("{$evolutionUrl}/message/sendMedia/{$instance}", [
                    'number'    => 'status@broadcast',
                    'mediatype' => 'video',
                    'media'     => $base64,
                    'caption'   => $caption,
                    'fileName'  => basename($videoPath),
                ]);

            if (!$response->successful()) {
                $error = $response->json()['message'] ?? $response->body();
                Log::error('[WA Status] publish failed', ['error' => $error]);
                return ['success' => false, 'error' => $error];
            }

            $msgId = $response->json()['key']['id'] ?? null;
            return [
                'success'          => true,
                'external_post_id' => $msgId,
                'external_post_url'=> null,
            ];
        } catch (\Throwable $e) {
            Log::error('[WA Status] exception', ['e' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
