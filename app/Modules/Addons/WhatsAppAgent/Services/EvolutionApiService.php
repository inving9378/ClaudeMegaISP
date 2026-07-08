<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Jobs\SendWhatsAppMessageJob;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
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
     * Identificador de la instancia hacia Evolution — FUENTE ÚNICA DE VERDAD.
     *
     * Se usa el SLUG (sin espacios, minúsculas, URL-safe) en TODAS las llamadas
     * (create, connect, connectionState, qr, logout, sendText y el match de
     * fetchInstances). El campo `instance_id` de la BD puede traer espacios/
     * mayúsculas heredados (ej. "Pruebas DEV") y solo sirve como etiqueta en la UI;
     * si se manda con espacios, Evolution crea con un nombre y luego no lo encuentra
     * al consultar → 404 y el QR nunca aparece. Normalización conservadora e
     * idempotente: no altera slugs ya limpios (meganet-ventas, pruebasdev).
     */
    private function evolutionName(WhatsAppInstance $instance): string
    {
        $raw  = ($instance->slug !== null && $instance->slug !== '')
            ? $instance->slug
            : (string) $instance->instance_id;

        $name = strtolower(trim($raw));
        $name = preg_replace('/[^a-z0-9._-]+/', '-', $name);
        $name = trim($name, '-._');

        return $name !== '' ? $name : 'instance-' . $instance->id;
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

        // Autenticación INVISIBLE: siempre la apikey global del .env (== AUTHENTICATION_API_KEY
        // de Evolution). Nunca una key por-instancia provista por el usuario.
        $response = Http::withHeaders([
            'apikey'       => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/message/sendText/{$this->evolutionName($instance)}", [
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

        // Crear-si-no-existe: la fila local puede existir sin que Evolution conozca la
        // instancia (tarjeta sembrada por migración, o create previo fallido). Sin esto,
        // connect da 404 "instance does not exist" y el QR nunca aparece. Idempotente:
        // si ya existe (p.ej. meganet-ventas), no crea nada.
        $this->ensureInstanceExists($instance);

        $response = Http::withHeaders(['apikey' => $this->apiKey])
            ->get("{$this->baseUrl}/instance/connect/{$this->evolutionName($instance)}");

        return $response->json() ?? [];
    }

    /**
     * Garantiza que la instancia exista en Evolution ANTES de pedir el QR. Consulta
     * fetchInstances por el nombre (slug) y, si no está, la crea. Best-effort: si la
     * verificación falla, intenta crear igual (create es idempotente del lado de Evolution).
     */
    public function ensureInstanceExists(WhatsAppInstance $instance): bool
    {
        if ($this->fakeMode) {
            return true;
        }

        $wanted = $this->evolutionName($instance);

        try {
            $response = Http::withHeaders(['apikey' => $this->apiKey])
                ->get("{$this->baseUrl}/instance/fetchInstances");
            foreach ((is_array($response->json()) ? $response->json() : []) as $it) {
                $name = $it['name'] ?? $it['instanceName'] ?? null;
                if ($name === $wanted) {
                    return true; // ya existe → nada que crear
                }
            }
        } catch (\Throwable $e) {
            Log::warning('fetchInstances falló al verificar existencia de instancia', [
                'instance' => $wanted,
                'error'    => $e->getMessage(),
            ]);
        }

        $created = $this->createInstance($instance);
        Log::info('Instancia creada on-demand en Evolution (flujo QR)', [
            'instance' => $wanted,
            'ok'       => empty($created['error']),
        ]);

        return true;
    }

    public function getConnectionStatus(WhatsAppInstance $instance): array
    {
        if ($this->fakeMode) {
            return ['state' => 'open', 'fake' => true];
        }

        $response = Http::withHeaders(['apikey' => $this->apiKey])
            ->get("{$this->baseUrl}/instance/connectionState/{$this->evolutionName($instance)}");

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

        $wanted = $this->evolutionName($instance);
        foreach ($list as $it) {
            $name = $it['name'] ?? $it['instanceName'] ?? null;
            if ($name === $wanted) {
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
            ->delete("{$this->baseUrl}/instance/logout/{$this->evolutionName($instance)}");

        return $response->json() ?? [];
    }

    public function createInstance(WhatsAppInstance $instance): array
    {
        if ($this->fakeMode) {
            return [
                'instance' => [
                    'instanceName' => $this->evolutionName($instance),
                    'status'       => 'created',
                ],
                'fake' => true,
            ];
        }

        $webhookUrl = rtrim((string) config('whatsapp.webhook_base_url'), '/')
            . '/whatsapp/webhook/' . $instance->slug;

        // Evolution v2 exige `integration` (sin él → 400 "Invalid integration") y espera
        // `webhook` como OBJETO {url,byEvents,base64,events}, no como string plano. Sin esto
        // la instancia NUNCA se crea → connect da 404 → el QR jamás aparece.
        $response = Http::withHeaders([
            'apikey'       => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/instance/create", [
            'instanceName' => $this->evolutionName($instance),
            'qrcode'       => true,
            'integration'  => 'WHATSAPP-BAILEYS',
            'webhook'      => [
                'url'      => $webhookUrl,
                'byEvents' => false,
                'base64'   => true,
                'events'   => [
                    'MESSAGES_UPSERT',
                    'MESSAGES_UPDATE',
                    'CONNECTION_UPDATE',
                    'QRCODE_UPDATED',
                ],
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
}
