<?php

namespace App\Services;

use App\Models\Network;
use App\Models\User;
use Database\Factories\NetworkFactory;

class NetworkService
{

    public function simulate(): Network
    {
        // Create a fake user
        $fakeNetwork = Network::factory()->create();
        return $fakeNetwork;
    }
}
