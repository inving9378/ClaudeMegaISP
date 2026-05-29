<?php

namespace App\Observers\Marketing;

use App\Models\Marketing\Conversation;
use App\Models\Marketing\Message;

class MessageObserver
{
    public function created(Message $message): void
    {
        $conversation = Conversation::find($message->conversation_id);
        if (!$conversation) {
            return;
        }

        if ($message->direction === 'outbound') {
            $conversation->updateQuietly(['unread_count' => 0]);
        }

        // Update lead's last_activity_at (safety net if job didn't do it)
        if ($conversation->lead_id) {
            \App\Models\Marketing\Lead::where('id', $conversation->lead_id)
                ->update(['last_activity_at' => now()]);
        }
    }
}
