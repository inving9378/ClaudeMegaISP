<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\ClientMainInformation;

class ClientRegistered
{
    use Dispatchable, SerializesModels;

    public $client;
    public $crmId;

    /**
     * Create a new event instance.
     */
    public function __construct(ClientMainInformation $client, $crmId)
    {
        $this->client = $client;
        $this->crmId = $crmId;
    }
}
