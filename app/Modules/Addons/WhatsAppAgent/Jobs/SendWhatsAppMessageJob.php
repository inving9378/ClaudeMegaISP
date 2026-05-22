<?php

namespace App\Modules\Addons\WhatsAppAgent\Jobs;

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Addons\WhatsAppAgent\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;
    public int $backoff = 30;

    public function __construct(private int $messageId)
    {
    }

    public function handle(EvolutionApiService $service): void
    {
        $message = WhatsAppMessage::with(['instance', 'conversation'])->find($this->messageId);

        if (!$message || $message->status !== 'pending') {
            return;
        }

        $service->sendTextViaApi($message);
    }

    public function failed(\Throwable $e): void
    {
        WhatsAppMessage::find($this->messageId)?->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
