<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Jobs\SendWhatsAppMessageJob;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EvolutionApiService
{
    private string $baseUrl;
    private string $apiKey;
    private bool   $fakeMode;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('whatsapp.api_url'), '/');
        $this->apiKey   = (string) config('whatsapp.api_key');
        $this->fakeMode = (bool) config('whatsapp.fake', false);
    }

    /**
     * Punto de entrada principal desde otros módulos.
     * Persiste mensaje + dispatch del job; el envío real ocurre en sendTextViaApi().
     */
    public function sendAndLog(
        string  $to,
        string  $body,
        ?string $instanceSlug = null,
        array   $context = []
    ): WhatsAppMessage {
        $instance = $instanceSlug
            ? WhatsAppInstance::where('slug', $instanceSlug)->where('active', true)->firstOrFail()
            : WhatsAppInstance::active()->default()->firstOrFail();

        $conversation = $this->resolveConversation($instance, $to);

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'instance_id'     => $instance->id,
            'direction'       => 'out',
            'message_type'    => 'text',
            'body'            => $body,
            'status'          => 'pending',
            'context'         => $context ?: null,
        ]);

        SendWhatsAppMessageJob::dispatch($message->id);

        return $message;
    }

    /**
     * Envío real — llamado desde el Job.
     */
    public function sendTextViaApi(WhatsAppMessage $message): void
    {
        if ($this->fakeMode) {
            $message->update([
                'status'               => 'sent',
                'sent_at'              => now(),
                'evolution_message_id' => 'fake_' . uniqid(),
            ]);
            Log::info('WhatsApp FAKE send', [
                'to'   => $message->conversation->contact_number,
                'body' => $message->body,
            ]);
            return;
        }

        $instance = $message->instance;
        $number   = $message->conversation->contact_number;
        $apiKey   = $this->decryptInstanceKey($instance);

        $response = Http::withHeaders([
            'apikey'       => $apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/message/sendText/{$instance->instance_id}", [
            'number' => $number,
            'text'   => $message->body,
            'delay'  => 1000,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $message->update([
                'status'               => 'sent',
                'sent_at'              => now(),
                'evolution_message_id' => $data['key']['id'] ?? null,
            ]);
            return;
        }

        $message->update([
            'status'        => 'failed',
            'error_message' => $response->body(),
        ]);
        throw new RuntimeException('Evolution API error: ' . $response->body());
    }

    public function getQrCode(WhatsAppInstance $instance): array
    {
        if ($this->fakeMode) {
            return [
                'qrcode'      => 'data:image/png;base64,FAKE_QR_FOR_DEV',
                'pairingCode' => '12345678',
                'fake'        => true,
            ];
        }

        $response = Http::withHeaders(['apikey' => $this->apiKey])
            ->get("{$this->baseUrl}/instance/connect/{$instance->instance_id}");

        return $response->json() ?? [];
    }

    public function getConnectionStatus(WhatsAppInstance $instance): array
    {
        if ($this->fakeMode) {
            return ['state' => 'open', 'fake' => true];
        }

        $response = Http::withHeaders(['apikey' => $this->apiKey])
            ->get("{$this->baseUrl}/instance/connectionState/{$instance->instance_id}");

        return $response->json() ?? ['state' => 'unknown'];
    }

    /**
     * Perfil de la instancia desde fetchInstances: número real (ownerJid, sin el sufijo
     * @s.whatsapp.net) y nombre de perfil. Devuelve [] si no se puede leer. NO conecta ni
     * modifica nada — solo lectura. (Fase 3: para persistir phone_number en el sync.)
     */
    public function getInstanceProfile(WhatsAppInstance $instance): array
    {
        if ($this->fakeMode) {
            return [];
        }

        $response = Http::withHeaders(['apikey' => $this->apiKey])
            ->get("{$this->baseUrl}/instance/fetchInstances");

        $list = $response->json();
        if (! is_array($list)) {
            return [];
        }

        foreach ($list as $it) {
            $name = $it['name'] ?? $it['instanceName'] ?? null;
            if ($name === $instance->instance_id) {
                $jid    = $it['ownerJid'] ?? null;
                $number = $jid ? explode('@', $jid)[0] : ($it['number'] ?? null);

                return [
                    'number'      => $number,
                    'profileName' => $it['profileName'] ?? null,
                ];
            }
        }

        return [];
    }

    /**
     * Cierra la sesión de WhatsApp de la instancia en Evolution (logout). Distinto de
     * borrar la fila local: aquí la línea queda "disconnected" y se revincula por QR.
     * Evolution v2.3.7: DELETE /instance/logout/{instance} — el nombre va en el path, así
     * que SOLO afecta a esa instancia. No-op en fakeMode.
     */
    public function disconnect(WhatsAppInstance $instance): array
    {
        if ($this->fakeMode) {
            return ['fake' => true, 'disconnected' => true];
        }

        $response = Http::withHeaders(['apikey' => $this->apiKey])
            ->delete("{$this->baseUrl}/instance/logout/{$instance->instance_id}");

        return $response->json() ?? [];
    }

    public function createInstance(WhatsAppInstance $instance): array
    {
        if ($this->fakeMode) {
            return [
                'instance' => [
                    'instanceName' => $instance->instance_id,
                    'status'       => 'created',
                ],
                'fake' => true,
            ];
        }

        $webhookUrl = rtrim((string) config('whatsapp.webhook_base_url'), '/')
            . '/whatsapp/webhook/' . $instance->slug;

        $response = Http::withHeaders([
            'apikey'       => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/instance/create", [
            'instanceName'    => $instance->instance_id,
            'token'           => $this->decryptInstanceKey($instance),
            'qrcode'          => true,
            'webhook'         => $webhookUrl,
            'webhookByEvents' => false,
            'events'          => [
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'CONNECTION_UPDATE',
                'QRCODE_UPDATED',
            ],
        ]);

        return $response->json() ?? [];
    }

    private function resolveConversation(WhatsAppInstance $instance, string $number): WhatsAppConversation
    {
        return WhatsAppConversation::firstOrCreate(
            [
                'instance_id'    => $instance->id,
                'contact_number' => $number,
            ],
            [
                'status' => 'open',
            ]
        );
    }

    private function decryptInstanceKey(WhatsAppInstance $instance): string
    {
        try {
            return Crypt::decryptString($instance->api_key);
        } catch (\Throwable $e) {
            return (string) $instance->api_key;
        }
    }
}
