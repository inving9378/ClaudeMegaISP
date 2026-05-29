<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateMessageStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $updates = $this->payload['data'] ?? [];
        if (!is_array($updates)) {
            $updates = [$updates];
        }

        foreach ($updates as $update) {
            $externalId = $update['key']['id'] ?? null;
            $status     = $update['update']['status'] ?? null;

            if (!$externalId || !$status) {
                continue;
            }

            Message::where('external_message_id', $externalId)
                ->update(['metadata->delivery_status' => $status]);
        }
    }
}
