<?php

namespace App\Services;

use App\Http\Repository\NetworkIpRepository;
use App\Http\Traits\RouterConnection;
use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Jobs\UpdateClientRouterJob;
use App\Models\NetworkIp;

class NetworkIpService
{
    use RouterConnection;
    protected $clientService;
    public function __construct($clientService = null)
    {
        $this->clientService = $clientService;
    }

    public function getClientIp()
    {
        if (!$this->clientService) return null;
        $networkIp = $this->clientService->network_ip_used_by;
        if (!$networkIp) return null;
        $clientIp = $networkIp->ip;
        return $clientIp;
    }

    public function getIpFilterByPlanId($planId, $type)
    {
        $networkIpRepository = new NetworkIpRepository();
        return $networkIpRepository->getIpFilterUsedByPlan($planId, $type);
    }

    public function simulate(): NetworkIp
    {
        // Create a fake user
        $fakeNetwork = NetworkIp::factory()->create();
        return $fakeNetwork;
    }
}
