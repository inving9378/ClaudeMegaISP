<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppMediaReceived;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppMessageReceived;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class WhatsAppRouterService
{
    public function __construct(
        private WhatsAppContactMatcherService $matcher
    ) {
    }

    /**
     * Entrada Evolution (sin cambios de comportamiento respecto a antes del
     * refactor -- es el webhook LIVE, cero riesgo tolerado aquí). Extrae y
     * delega a processInboundNormalized(), que es la pieza que ahora
     * comparte con Meta.
     */
    public function route(WhatsAppInstance $instance, array $payload): void
    {
        $event = $payload['event'] ?? $payload['type'] ?? null;

        match (true) {
            $event && str_contains(strtolower($event), 'message')    => $this->handleEvolutionMessage($instance, $payload),
            $event && str_contains(strtolower($event), 'connection') => $this->handleConnection($instance, $payload),
            $event && str_contains(strtolower($event), 'qrcode')     => $this->handleQr($instance, $payload),
            default => Log::info('WhatsApp webhook event ignored', ['event' => $event, 'instance' => $instance->slug]),
        };
    }

    /**
     * Entrada Meta Cloud API: el payload ya lo normaliza
     * MetaCloudApiService::extractMessages() a la MISMA forma que
     * processInboundNormalized() espera -- esta función solo hace el loop +
     * delega, igual que handleEvolutionMessage() hace con el formato Evolution.
     */
    public function routeMeta(WhatsAppInstance $instance, MetaCloudApiService $meta, array $payload): void
    {
        foreach ($meta->extractMessages($payload) as $msg) {
            $this->processInboundNormalized(
                $instance,
                externalMessageId: $msg['messageId'] ?? null,
                contactNumber: (string) ($msg['contactNumber'] ?? ''),
                contactName: $msg['contactName'] ?? null,
                type: $msg['type'] ?? 'text',
                body: $msg['body'] ?? null,
                rawMessage: $msg['rawMessage'] ?? [],
            );
        }
    }

    private function handleEvolutionMessage(WhatsAppInstance $instance, array $payload): void
    {
        foreach ($this->extractMessages($payload) as $msg) {
            $messageId = $msg['key']['id'] ?? null;

            // Mismo orden EXACTO que antes del refactor: primero dedupe (si ya
            // existe, se ignora por completo, incluso si es fromMe), y SOLO
            // después se revisa fromMe. processInboundNormalized() vuelve a
            // dedupear internamente (lo necesita Meta, que no tiene este
            // branch de fromMe) -- redundante pero inofensivo para Evolution.
            if ($messageId && WhatsAppMessage::where('evolution_message_id', $messageId)->exists()) {
                continue;
            }

            // Mensajes salientes (fromMe): solo actualizar estado del existente.
            // Esto es específico de Evolution (confirmación de un envío propio
            // que regresa por el mismo webhook) -- Meta no manda esto como
            // "message", lo manda aparte en `statuses`, por eso vive solo aquí.
            if ($msg['key']['fromMe'] ?? false) {
                if ($messageId) {
                    WhatsAppMessage::where('evolution_message_id', $messageId)
                        ->update(['status' => 'delivered', 'delivered_at' => now()]);
                }
                continue;
            }

            $contactNumber = str_replace(
                ['@s.whatsapp.net', '@g.us'],
                '',
                $msg['key']['remoteJid'] ?? ''
            );

            $body = $msg['message']['conversation']
                ?? $msg['message']['extendedTextMessage']['text']
                ?? null;

            $this->processInboundNormalized(
                $instance,
                externalMessageId: $messageId,
                contactNumber: $contactNumber,
                contactName: $msg['pushName'] ?? null,
                type: $this->resolveMessageType($msg['message'] ?? []),
                body: $body,
                rawMessage: $msg,
            );
        }
    }

    /**
     * Pieza COMPARTIDA entre proveedores: dedupe por id externo, resolver/
     * actualizar la conversación, persistir el mensaje y publicar los
     * eventos del gateway. Evolution y Meta llegan aquí ya normalizados --
     * de aquí para abajo no hay NADA específico de un proveedor en particular.
     */
    private function processInboundNormalized(
        WhatsAppInstance $instance,
        ?string $externalMessageId,
        string $contactNumber,
        ?string $contactName,
        string $type,
        ?string $body,
        array $rawMessage,
    ): void {
        if ($externalMessageId && WhatsAppMessage::where('evolution_message_id', $externalMessageId)->exists()) {
            return;
        }

        $match    = $this->matcher->match($contactNumber);
        $client   = $match['client'];
        $sellerId = $match['seller_id'];

        $conversation = WhatsAppConversation::firstOrCreate(
            [
                'instance_id'    => $instance->id,
                'contact_number' => $contactNumber,
            ],
            [
                'contact_name' => $contactName,
                'client_id'    => $client?->id,
                'seller_id'    => $sellerId,
                'status'       => 'open',
            ]
        );

        if ($contactName && $conversation->contact_name !== $contactName) {
            $conversation->update(['contact_name' => $contactName]);
        }

        $stored = WhatsAppMessage::create([
            'conversation_id'      => $conversation->id,
            'instance_id'          => $instance->id,
            'direction'            => 'in',
            'message_type'         => $type,
            'body'                 => $body,
            'status'               => 'received',
            // Columna histórica (nombre heredado de cuando solo existía
            // Evolution) -- se reusa tal cual para CUALQUIER proveedor como
            // "id externo del mensaje", en vez de agregar una columna
            // paralela redundante.
            'evolution_message_id' => $externalMessageId,
        ]);

        $conversation->incrementUnread();
        $conversation->update(['last_message_at' => now()]);

        $this->publish($instance, $conversation, $stored, $contactNumber, $type, $body, $rawMessage);
    }

    /**
     * Publica los eventos del gateway para un mensaje entrante ya persistido:
     * el base {@see WhatsAppMessageReceived} (suscriptores genéricos) más el
     * especializado por tipo (texto → IA, media → conciliación). `lineFunctions`
     * son los slugs de las funciones asignadas a la línea (cobranza/ventas/…),
     * para que los listeners puedan autogatearse.
     */
    private function publish(
        WhatsAppInstance $instance,
        WhatsAppConversation $conversation,
        WhatsAppMessage $stored,
        string $contactNumber,
        string $type,
        ?string $body,
        array $rawMessage
    ): void {
        try {
            $lineFunctions = $instance->functions()->pluck('whatsapp_functions.slug')->all();
        } catch (\Throwable $e) {
            $lineFunctions = [];
        }

        $args = [
            $instance->id,
            (string) $instance->slug,
            $conversation->id,
            $stored->id,
            $contactNumber,
            $type,
            $body,
            $lineFunctions,
            $rawMessage,
        ];

        WhatsAppMessageReceived::dispatch(...$args);

        if (in_array($type, ['image', 'document'], true)) {
            WhatsAppMediaReceived::dispatch(...$args);
        } else {
            WhatsAppTextReceived::dispatch(...$args);
        }
    }

    private function handleConnection(WhatsAppInstance $instance, array $payload): void
    {
        $state = $payload['data']['state'] ?? $payload['state'] ?? null;
        $status = match ($state) {
            'open'    => 'connected',
            'close'   => 'disconnected',
            'refused' => 'disconnected',
            default   => 'disconnected',
        };
        $instance->update(['status' => $status]);
    }

    private function handleQr(WhatsAppInstance $instance, array $payload): void
    {
        $qr = $payload['data']['qrcode'] ?? $payload['qrcode'] ?? $payload['data']['base64'] ?? null;
        if ($qr) {
            $instance->update([
                'qr_code'       => $qr,
                'qr_expires_at' => now()->addMinutes(2),
                'status'        => 'qr_pending',
            ]);
        }
    }

    private function extractMessages(array $payload): array
    {
        // Evolution v2: {data: {key:..., message:...}}
        // Evolution v1: {messages: [...]}
        // Variantes con array directo
        $data = $payload['data'] ?? null;

        if (is_array($data) && isset($data['key'])) {
            return [$data];
        }
        if (is_array($data) && array_is_list($data)) {
            return $data;
        }
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            return $payload['messages'];
        }
        if (isset($payload['key'])) {
            return [$payload];
        }
        return [];
    }

    private function resolveMessageType(array $message): string
    {
        if (isset($message['conversation']) || isset($message['extendedTextMessage'])) {
            return 'text';
        }
        if (isset($message['imageMessage'])) {
            return 'image';
        }
        if (isset($message['documentMessage'])) {
            return 'document';
        }
        if (isset($message['audioMessage'])) {
            return 'audio';
        }
        if (isset($message['videoMessage'])) {
            return 'video';
        }
        if (isset($message['locationMessage'])) {
            return 'location';
        }
        if (isset($message['stickerMessage'])) {
            return 'sticker';
        }
        return 'text';
    }
}
