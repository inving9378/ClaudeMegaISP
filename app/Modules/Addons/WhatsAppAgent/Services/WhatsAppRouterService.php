<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

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

    public function route(WhatsAppInstance $instance, array $payload): void
    {
        $event = $payload['event'] ?? $payload['type'] ?? null;

        match (true) {
            $event && str_contains(strtolower($event), 'message')    => $this->handleMessage($instance, $payload),
            $event && str_contains(strtolower($event), 'connection') => $this->handleConnection($instance, $payload),
            $event && str_contains(strtolower($event), 'qrcode')     => $this->handleQr($instance, $payload),
            default => Log::info('WhatsApp webhook event ignored', ['event' => $event, 'instance' => $instance->slug]),
        };
    }

    private function handleMessage(WhatsAppInstance $instance, array $payload): void
    {
        $messages = $this->extractMessages($payload);

        foreach ($messages as $msg) {
            $messageId = $msg['key']['id'] ?? null;

            if ($messageId && WhatsAppMessage::where('evolution_message_id', $messageId)->exists()) {
                continue;
            }

            // Mensajes salientes (fromMe): solo actualizar estado del existente
            if ($msg['key']['fromMe'] ?? false) {
                if ($messageId) {
                    WhatsAppMessage::where('evolution_message_id', $messageId)
                        ->update([
                            'status'       => 'delivered',
                            'delivered_at' => now(),
                        ]);
                }
                continue;
            }

            $contactNumber = $msg['key']['remoteJid'] ?? '';
            $contactNumber = str_replace(['@s.whatsapp.net', '@g.us'], '', $contactNumber);
            $contactName   = $msg['pushName'] ?? null;

            $body = $msg['message']['conversation']
                ?? $msg['message']['extendedTextMessage']['text']
                ?? null;

            $messageType = $this->resolveMessageType($msg['message'] ?? []);

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
                'message_type'         => $messageType,
                'body'                 => $body,
                'status'               => 'received',
                'evolution_message_id' => $messageId,
            ]);

            $conversation->incrementUnread();
            $conversation->update(['last_message_at' => now()]);

            // Auto-reply: opcional, NO bloqueante. Errores van a logs sin
            // propagarse — el mensaje IN ya quedó persistido arriba.
            if ((bool) config('whatsapp.auto_reply', false)) {
                try {
                    app(WhatsAppAutoReplyService::class)->maybeReply($stored, $conversation);
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp auto-reply: excepción no capturada', [
                        'message_id' => $stored->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
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
