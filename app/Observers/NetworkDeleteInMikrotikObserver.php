<?php

namespace App\Observers;

use App\Models\Network;
use App\Models\MikrotikItemToExcecuteAction;
use Illuminate\Support\Facades\Log;

class NetworkDeleteInMikrotikObserver
{
    public function updated(Network $network)
    {
        MikrotikItemToExcecuteAction::create([
            'Model' => 'Network',
            'place' => '/ip/pool/',
            'flag' => 'title',
            'value' => $network,
            'action' => 'update',
        ]);
    }

    public function deleted(Network $network)
    {
        MikrotikItemToExcecuteAction::create([
            'Model' => 'Network',
            'place' => '/ip/pool/',
            'flag' => 'title',
            'value' => $network,
            'action' => 'delete',
        ]);
    }
}
