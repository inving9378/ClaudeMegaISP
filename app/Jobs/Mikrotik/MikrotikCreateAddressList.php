<?php

namespace App\Jobs\Mikrotik;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\RouterRepository;
use App\Services\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use App\Services\NetworkIpService;

class MikrotikCreateAddressList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $clientService;
    protected $router;
    protected $mikrotik;
    protected $networkIpService;
    protected $forceCreate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientService, $forceCreate = false)
    {
        $this->clientService = $clientService ?? null;
        $this->router = $this->clientService->router ?? null;
        $this->mikrotik = $this->router->mikrotik ?? null;
        $this->forceCreate = $forceCreate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(MikrotikService $mikrotikService)
    {
        $this->networkIpService = new NetworkIpService($this->clientService);
        if ($this->clientService) {
            $clientName = $this->clientService->client->client_main_information->user ?? 'Meganet-ClienteId-' . $this->clientService->client_id;
            if ($this->router && $this->router->type_of_nas == 'Mikrotik') {
                if ($this->mikrotik) {
                    $connected = $mikrotikService->getConnection($this->router);
                    if ($connected) {
                        $constIpFirewallAddressList = ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH;
                        $clientIp = $this->networkIpService->getClientIp();
                        if ($this->mikrotik->active) {
                            if ($this->forceCreate) {
                                $this->addItem($connected, $constIpFirewallAddressList, (['list' => 'MgNet_Morosos', 'address' => $clientIp, 'comment' => $clientName . '-' . $this->clientService->id]));
                                return;
                            }
                            $idByIp = $this->getIdByIp($connected, $constIpFirewallAddressList, $clientIp);
                            if (!$idByIp) {
                                $this->addItem($connected, $constIpFirewallAddressList, (['list' => 'MgNet_Morosos', 'address' => $clientIp, 'comment' => $clientName . '-' . $this->clientService->id]));
                                $this->clientService->service_in_address_list()->updateOrCreate(['deployed' => true]);
                            }
                        }
                    } else {
                        activity()->log('Fallo en la conexion con el Mikrotik id# ' . $this->router->id . ' al intentar colocar en address_list el Cliente #' . $this->clientService->client_id);
                        $routerRepository = new RouterRepository();
                        $routerRepository->sendNotifications($this->router->id);
                    }
                }
            }
        }
    }
}
