<?php

namespace App\Jobs\Mikrotik;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\RouterRepository;
use App\Services\NetworkIpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use App\Services\MikrotikService;

class MikrotikRemoveClientServiceFromAddressList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $clientService;
    protected $router;
    protected $mikrotik;
    protected $clientMainInformationRepository;
    protected $networkIpService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (is_array($this->clientService)) {
            foreach ($this->clientService as $service) {
                $this->removeClientFromAddressList($service);
            }
        } else {
            $this->removeClientFromAddressList($this->clientService);
        }
    }

    public function removeClientFromAddressList($service)
    {

        $ruterRepository = new RouterRepository();
        $router = $ruterRepository->getRouterById($service->router_id);
        if ($router) {
            $networkIpService = new NetworkIpService($service);
            $mikrotikService = new MikrotikService();
            $connected = $mikrotikService->getConnection($router);
            if ($connected) {
                $clientIp = $networkIpService->getClientIp();
                $constIpFirewallAddressList = ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH;
                if ($clientIp) {
                    $idByIp = $this->getIdByIp($connected, $constIpFirewallAddressList, $clientIp);
                    if ($idByIp) {
                        $this->removeById($connected, $constIpFirewallAddressList, $idByIp);
                        $this->removeServiceInAdressListFromDb();
                        activity()->log('Servicio ' . $service->id . ' ELIMINADO del address_list Cliente #' . $service->client_id);
                    }
                }
            }
        }
    }

    public function removeServiceInAdressListFromDb()
    {
        $this->clientService->service_in_address_list()->delete();
    }
}
