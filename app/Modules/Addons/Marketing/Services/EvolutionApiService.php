<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\Setting;
use App\Services\Core\UsesApiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
{
    use UsesApiIntegration;
    protected string $baseUrl;
    protected string $apiKey;
    protected string $instance;

    public function __construct(int $companyId = 1)
    {
        $integration    = $this->resolveApiIntegration('evolution', $companyId);
        $this->baseUrl  = rtrim($integration?->config['endpoint'] ?? Setting::get('evolution_api_url', $companyId) ?? '', '/');
        $this->apiKey   = $this->resolveApiKey('evolution', 'WHATSAPP_API_KEY', 'evolution_api_key', $companyId) ?? '';
        $this->instance = Setting::get('evolution_instance_name', $companyId) ?? '';
    }

    // ── Public API ──────────────────────────────────────────────────────────────

    public function sendText(string $toJid, string $text): array
    {
        return $this->post("/message/sendText/{$this->instance}", [
            'number' => explode('@', $toJid)[0],
            'text'   => $text,
            'delay'  => 1200,
        ]);
    }

    public function sendMedia(string $toJid, string $mediaUrl, string $mediaType, ?string $caption = null): array
    {
        $phone = explode('@', $toJid)[0];
        $payload = [
            'number'    => $phone,  // already extracted from JID above
            'mediatype' => $mediaType,
            'media'     => $mediaUrl,
        ];
        if ($caption !== null) {
            $payload['caption'] = $caption;
        }
        return $this->post("/message/sendMedia/{$this->instance}", $payload);
    }

    public function fetchInstanceStatus(): array
    {
        return $this->get("/instance/connectionState/{$this->instance}");
    }

    public function setWebhook(string $webhookUrl, array $events = []): array
    {
        if (empty($events)) {
            $events = ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'];
        }
        // Evolution API v2 wraps config in a "webhook" object
        return $this->post("/webhook/set/{$this->instance}", [
            'webhook' => [
                'enabled'        => true,
                'url'            => $webhookUrl,
                'webhookByEvents'=> false,
                'webhookBase64'  => false,
                'events'         => $events,
            ],
        ]);
    }

    public function markAsRead(string $jid, string $messageId): array
    {
        return $this->post("/chat/markMessageAsRead/{$this->instance}", [
            'readMessages' => [['id' => $messageId, 'fromMe' => false, 'remoteJid' => $jid]],
        ]);
    }

    public function sendTyping(string $jid, int $duration = 2000): array
    {
        // sendPresence needs full international number (with country code), not stripped local format
        $number = explode('@', $jid)[0];
        return $this->post("/chat/sendPresence/{$this->instance}", [
            'number'   => $number,
            'presence' => 'composing',
            'delay'    => $duration,
        ]);
    }

    // ── Phone helpers ────────────────────────────────────────────────────────────

    public static function phoneToJid(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        // Mexico: 10 digits → prefix with 521 (WhatsApp MX format)
        if (strlen($digits) === 10) {
            $digits = '521' . $digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            $digits = '521' . substr($digits, 2);
        }
        return $digits . '@s.whatsapp.net';
    }

    public static function jidToPhone(string $jid): string
    {
        $phone = explode('@', $jid)[0];
        // Strip 521 MX prefix → 10 digits
        if (str_starts_with($phone, '521') && strlen($phone) === 13) {
            return substr($phone, 3);
        }
        // Strip 52 prefix → 10 digits
        if (str_starts_with($phone, '52') && strlen($phone) === 12) {
            return substr($phone, 2);
        }
        return $phone;
    }

    // ── HTTP helpers ─────────────────────────────────────────────────────────────

    protected function get(string $path): array
    {
        return $this->withRetry(fn () => Http::withHeaders($this->headers())
            ->retry(3, 200, fn ($e) => $e->response?->status() >= 500, throw: false)
            ->get($this->baseUrl . $path));
    }

    protected function post(string $path, array $body): array
    {
        return $this->withRetry(fn () => Http::withHeaders($this->headers())
            ->retry(3, 200, fn ($e) => $e->response?->status() >= 500, throw: false)
            ->post($this->baseUrl . $path, $body));
    }

    protected function withRetry(callable $fn): array
    {
        $response = $fn();
        $status   = $response->status();

        Log::channel('evolution')->info('Evolution API', [
            'status' => $status,
            'body'   => $response->json(),
        ]);

        if ($status >= 400) {
            Log::channel('evolution')->error('Evolution API error', [
                'status' => $status,
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("Evolution API error {$status}: " . $response->body());
        }

        return $response->json() ?? [];
    }

    protected function headers(): array
    {
        return ['apikey' => $this->apiKey, 'Content-Type' => 'application/json'];
    }
}
