<?php

namespace App\Observers;

use App\Models\Mikrotik;
use App\Jobs\Mikrotik\MikrotikRulesJob;
use App\Jobs\Mikrotik\MicrotikDeleteRulesJob;
use Illuminate\Support\Facades\Log;


class MikrotikObserver
{
    public function updated(Mikrotik $mikrotik)
    {
        MikrotikRulesJob::dispatchAfterResponse($mikrotik);
    }
}
