<?php

namespace App\Modules\Addons\WhatsAppAgent\Listeners;

use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppAutoReplyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Consumidor de TEXTO del gateway → respuesta automática de la IA.
 *
 * Reemplaza la llamada inline a maybeReply que vivía en el router; ahora corre
 * por cola (worker), no bloquea el webhook. Comportamiento idéntico al de hoy:
 * gateado por config('whatsapp.auto_reply'); un fallo no propaga (best-effort).
 */
class IaAutoReplyListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(WhatsAppTextReceived $event): void
    {
        if (!(bool) config('whatsapp.auto_reply', false)) {
            return;
        }

        $message      = WhatsAppMessage::find($event->messageId);
        $conversation = WhatsAppConversation::find($event->conversationId);
        if (!$message || !$conversation) {
            return;
        }

        try {
            app(WhatsAppAutoReplyService::class)->maybeReply($message, $conversation);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp IA auto-reply (listener): excepción', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
