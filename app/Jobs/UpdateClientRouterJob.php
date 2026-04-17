<?php

namespace App\Jobs;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\RouterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use App\Services\MikrotikService;
use App\Services\NetworkIpService;

class UpdateClientRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $model;
    protected $ipOld;
    protected $createIfNotExist;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($model, $ipOld, $createIfNotExist = false)
    {
        $this->model = $model;
        $this->ipOld = $ipOld;
        $this->createIfNotExist = $createIfNotExist;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->updateIp();
    }

    public function updateIp()
    {
        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById($this->model->router_id);
        $mikrotikService = new MikrotikService();
        if ($router) {
            $connected = $mikrotikService->getConnection($router);
            if ($connected) {
                $this->updateFirewallAddressListIfExist($connected);
                $this->updateSecretIfExist();
            }
        }
    }

    public function updateSecretIfExist()
    {
        CreateClientWithServiceJob::dispatch($this->model);
    }

    public function updateFirewallAddressListIfExist($connected)
    {
        $networkIpService = new NetworkIpService($this->model);
        $constIpFirewallAddressList = ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH;
        $type = class_basename(get_class($this->model));
        $networkIp = $this->model->network_ip_used_by ? $this->model->network_ip_used_by->ip : $networkIpService->getIpFilterByPlanId($this->model->id, $type);
        $idByIp = $this->getIdByIp($connected, $constIpFirewallAddressList, $this->ipOld);
        if ($idByIp) {
            $this->setvalueArrayById(
                $connected,
                $constIpFirewallAddressList,
                $idByIp,
                [
                    'address' => $networkIp,
                ]
            );
        }
    }
}
