<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Message;
use App\Modules\Addons\Marketing\Services\AiAgentService;
use App\Modules\Addons\Marketing\Services\ConversationResolverService;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    public function __construct(public array $evolutionPayload) {}

    public function handle(
        ConversationResolverService $resolver,
        AiAgentService $agent,
        EvolutionApiService $evolution,
    ): void {
        $payload = $this->evolutionPayload;
        $data    = $payload['data'] ?? [];
        $key     = $data['key'] ?? [];

        // 1. Skip messages sent by us
        if ($key['fromMe'] ?? false) {
            return;
        }

        // 2. Skip group messages (jid ends in @g.us)
        $remoteJid = $key['remoteJid'] ?? '';
        if (str_ends_with($remoteJid, '@g.us')) {
            return;
        }

        // 3. Extract text and content type
        $msgData    = $data['message'] ?? [];
        $text       = $this->extractText($msgData);
        $contentType = $this->detectContentType($msgData);

        if (empty($text) && $contentType === 'text') {
            return;
        }

        // 4. Resolve lead + conversation
        $resolved     = $resolver->resolveFromEvolutionMessage($payload);
        $lead         = $resolved['lead'];
        $conversation = $resolved['conversation'];

        // 5. Persist inbound message
        $sentAt = isset($data['messageTimestamp'])
            ? Carbon::createFromTimestamp($data['messageTimestamp'])
            : now();

        $message = Message::create([
            'conversation_id'   => $conversation->id,
            'direction'         => 'inbound',
            'content'           => $text,
            'content_type'      => $contentType,
            'sender'            => 'lead',
            'external_message_id' => $key['id'] ?? null,
            'sent_at'           => $sentAt,
            'metadata'          => $data,
        ]);

        // 5b. Descarga aislada del binario si es imagen/documento (comprobantes).
        //     Best-effort: un fallo aquí NO afecta la persistencia del mensaje ni
        //     el flujo de ventas (ya ocurrieron arriba). Job en cola aparte.
        if (in_array($contentType, ['image', 'document'], true)) {
            try {
                DownloadWhatsAppMediaJob::dispatch($message->id)->onQueue('default');
            } catch (\Throwable $e) {
                Log::channel('evolution')->warning('dispatch DownloadWhatsAppMediaJob falló: ' . $e->getMessage());
            }
        }

        // 6. Update conversation counters
        $conversation->update([
            'last_inbound_at' => now(),
            'last_message_at' => now(),
            'unread_count'    => $conversation->unread_count + 1,
        ]);

        // 6b. F3.5 — Ruteo de conciliación de pagos (ADITIVO, detrás del flag
        //     MAESTRO wa_conciliation). Con el flag apagado (default) esto es un
        //     no-op TOTAL y el mensaje sigue EXACTAMENTE igual a ventas. Con el
        //     flag encendido, un mensaje detectado como pago se desvía a
        //     conciliación y NO llega al bot de ventas; si NO es pago, cae a
        //     ventas sin cambios. Un fallo aquí no rompe ventas (fail-open).
        //     Se lee vía ConciliationSettings (tabla, fallback a config/.env) →
        //     el toggle del panel tiene efecto inmediato aun en workers vivos.
        if (\App\Modules\Addons\Payments\Support\ConciliationSettings::enabled('wa_conciliation')) {
            try {
                $router = app(\App\Modules\Addons\Payments\Services\Conciliation\ConciliationRouter::class);
                if ($router->shouldHandle($conversation, $contentType, $text, $lead)) {
                    $router->route($conversation, $message, $contentType, $text);
                    return; // desviado a conciliación
                }
            } catch (\Throwable $e) {
                Log::channel('evolution')->error('Conciliación routing falló, sigue a ventas: ' . $e->getMessage());
            }
        }

        // 7. Skip if not AI-handled or conversation closed
        if (!$conversation->ai_handled || $conversation->status === 'closed') {
            return;
        }

        // 8. Typing indicator (best effort)
        try {
            $evolution->sendTyping($remoteJid, 2000);
        } catch (\Throwable $e) {
            Log::channel('evolution')->debug("sendTyping failed: " . $e->getMessage());
        }

        // 9. Generate AI reply
        $reply = $agent->respondTo($conversation->fresh(), $message);

        if ($reply === null) {
            return;
        }

        // 10. Dispatch outbound job
        SendOutboundMessageJob::dispatch($conversation->id, $reply, 'ai_agent')->onQueue('default');
    }

    private function extractText(array $message): string
    {
        return $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? $message['imageMessage']['caption']
            ?? $message['videoMessage']['caption']
            ?? '';
    }

    private function detectContentType(array $message): string
    {
        if (isset($message['imageMessage']))    return 'image';
        if (isset($message['videoMessage']))    return 'video';
        if (isset($message['audioMessage']))    return 'audio';
        if (isset($message['documentMessage'])) return 'document';
        if (isset($message['locationMessage'])) return 'location';
        if (isset($message['stickerMessage']))  return 'sticker';
        return 'text';
    }
}
