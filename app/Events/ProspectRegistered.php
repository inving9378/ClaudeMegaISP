<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\CrmLeadInformation;

class ProspectRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $prospect;

    /**
     * Create a new event instance.
     */
    public function __construct(CrmLeadInformation $prospect)
    {
        $this->prospect = $prospect;
    }

}
