<?php

namespace App\Jobs\NetworkIp;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Traits\RouterConnection;
use App\Models\ClientInternetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SetIPToClientInternetServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $modelInternetService;
    protected $networkIpRepository;
    protected $ipOld;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($modelInternetService,$ipOld = null)
    {
        $this->modelInternetService = $modelInternetService;
        $this->ipOld = $ipOld;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->setIpToClientByAssignmentMethod();
    }

    public function setIpToClientByAssignmentMethod()
    {

        $method = $this->modelInternetService->ipv4_assignment;
        $doActionByMethod = [
            ClientInternetService::IPV4_ASSIGNMENT_STATIC => 'returnStaticIp',
            ClientInternetService::IPV4_ASSIGNMENT_POOL_IP => 'returnPoolIp',
        ];
        if (isset($doActionByMethod[$method])) {
            $run = $doActionByMethod[$method];
            $this->$run();
        }
    }

    public function returnStaticIp()
    {
        $type = class_basename(get_class($this->modelInternetService));
        $this->networkIpRepository = new NetworkIpRepository();
        $ip = $this->networkIpRepository->getNetworkIpById($this->modelInternetService->ipv4);
        if ($this->networkIpRepository->getIfIpIsUsedByOtherClient($ip, $this->modelInternetService->id, $type)) {
            throw ValidationException::withMessages(['ipv4' => ['La direccion IP ya esta en uso.']]);
        } else {
            $this->networkIpRepository->update($ip, [
                'used' => ComunConstantsController::IS_NUMERICAL_TRUE,
                'used_by' => $this->modelInternetService->id,
                'client_id' => $this->modelInternetService->client_id,
                'type_service' => $type,
                'host_category' => 'Customer'
            ]);
        }
        return $ip->id;
    }

    public function returnPoolIp()
    {
        $router_id = $this->modelInternetService->router_id;
        $pool_id = $this->modelInternetService->ipv4_pool;
        $this->isNotExistPoolInMikrotik($router_id, $pool_id);
        return $this->selectAndSetIpInPool();
    }

    public function selectAndSetIpInPool()
    {
        $type = class_basename(get_class($this->modelInternetService));
        $this->networkIpRepository = new NetworkIpRepository();
        $pool_id = $this->modelInternetService->ipv4_pool;
        $router_id = $this->modelInternetService->router_id;

        $ipPool = $this->networkIpRepository->getPoolIp($pool_id, $router_id, $this->ipOld);
        if (!$ipPool) {
            return null;
        }
        $this->networkIpRepository->update($ipPool, [
            'used' => ComunConstantsController::IS_NUMERICAL_TRUE,
            'used_by' => $this->modelInternetService->id,
            'client_id' => $this->modelInternetService->client_id,
            'type_service' => $type,
            'host_category' => 'Customer'
        ]);
        return $ipPool->id;
    }
}
