<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Conversation;
use App\Models\Marketing\Message;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOutboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(
        public int $conversationId,
        public string $text,
        public string $sender = 'ai_agent',
        public ?int $senderUserId = null,
    ) {}

    public function handle(EvolutionApiService $evolution): void
    {
        $conv = Conversation::with('lead')->find($this->conversationId);
        if (!$conv) {
            Log::channel('evolution')->warning("SendOutbound: conversation {$this->conversationId} not found");
            return;
        }

        $toJid = $conv->external_thread_id;
        if (empty($toJid)) {
            Log::channel('evolution')->error("SendOutbound: conversation {$this->conversationId} has no external_thread_id");
            return;
        }

        // Persist outbound message before sending (so it's always in history)
        $message = Message::create([
            'conversation_id'   => $conv->id,
            'direction'         => 'outbound',
            'content'           => $this->text,
            'content_type'      => 'text',
            'sender'            => $this->sender,
            'sender_user_id'    => $this->senderUserId,
            'sent_at'           => now(),
        ]);

        try {
            $result = $evolution->sendText($toJid, $this->text);
            $message->update(['external_message_id' => $result['key']['id'] ?? null]);
        } catch (\Throwable $e) {
            Log::channel('evolution')->error("SendOutbound failed: " . $e->getMessage());
            throw $e;
        }

        $conv->update([
            'last_outbound_at' => now(),
            'last_message_at'  => now(),
        ]);
    }
}
