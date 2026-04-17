<?php

namespace App\Services;

use App\Http\Traits\RouterConnection;
use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Jobs\UpdateClientRouterJob;
use App\Models\ClientInternetService;
use App\Models\Helper\Client;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    use RouterConnection;

    protected $connections = [];

    public function getConnection($router)
    {
        $key = md5($router->id);
        if (!isset($this->connections[$key])) {
            $this->connections[$key] = $this->getConnectionByRouter($router);
        }

        return $this->connections[$key];
    }

    public function resetConnection()
    {
        $this->connections = [];
    }


    public function updateIpInMikrotikByClientInternetService(ClientInternetService $clientInternetService, $ipOld = null)
    {
        if(!$ipOld) {
            $ipOld = $clientInternetService->network_ip_used_by->ip;
        }
        $this->liberaLaIpUsada($clientInternetService);
        SetIPToClientInternetServiceJob::dispatch($clientInternetService, $ipOld);
        $clientInternetService->network_ip_used_by->refresh();

        UpdateClientRouterJob::dispatch($clientInternetService, $ipOld, true);
    }
}
