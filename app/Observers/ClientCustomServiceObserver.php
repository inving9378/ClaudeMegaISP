<?php

namespace App\Observers;

use App\Http\Repository\ClientRepository;
use App\Http\Repository\ClientUserRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Traits\RouterConnection;
use App\Jobs\CreateClientWithServiceJob;
use App\Jobs\DeletedClientWithServiceJob;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Models\ClientCustomService;
use App\Services\LogService;

class ClientCustomServiceObserver
{
    use RouterConnection;

    public function created(ClientCustomService $clientCustomService)
    {
        $clientUserRepository = new ClientUserRepository();
        if ($clientCustomService->internet_id) {
            $clientUserRepository->create($clientCustomService->user, $clientCustomService->router_id, $clientCustomService->id, $clientCustomService->client_id);
        }

        if ($clientCustomService->client_bundle_service_id == null) {
            if ($clientCustomService->internet_id) {
                SetIPToClientInternetServiceJob::dispatch($clientCustomService);
                MikrotikCreateAddressList::dispatch($clientCustomService);
                $client = (new ClientRepository)->getClientById($clientCustomService->client_id);
                $logService = new LogService();
                $logService->log($client, 'Su servicio' . $clientCustomService->service_name . ' Custom fue colocado en address_list desde el ClientCustomServiceObserver::created ');
            }
        }
    }

    public function updating(ClientCustomService $clientCustomService)
    {
        if ($clientCustomService->client_bundle_service_id == null) {
            $clientUserRepository = new ClientUserRepository();
            if ($clientCustomService->internet_id) {
                $clientUser = $clientUserRepository->getModelByServiceId($clientCustomService->id);
                if ($clientUser->user != $clientCustomService->user) {
                    $clientUser->update([
                        'user' => $clientCustomService->user
                    ]);
                }
            }

            $modelInternet = 'App\Models\Internet';
            if ($clientCustomService->isDirty('deployed') && $clientCustomService->deployed) {
                if ($this->customHaveInternet($clientCustomService)) {
                    CreateClientWithServiceJob::dispatchAfterResponse($clientCustomService, $modelInternet);
                    MikrotikRemoveClientServiceFromAddressList::dispatchAfterResponse($clientCustomService);
                    $client = (new ClientRepository)->getClientById($clientCustomService->client_id);
                    $logService = new LogService();
                    $logService->log($client, 'Su servicio .' . $clientCustomService->service_name . ' fue removido del address_list desde el ClientCustomServiceObserver::updating ');
                }
            }
            if ($clientCustomService->isDirty('deployed') && $clientCustomService->deployed == false) {
                if ($this->customHaveInternet($clientCustomService)) {
                    MikrotikCreateAddressList::dispatch($clientCustomService);
                    $client = (new ClientRepository)->getClientById($clientCustomService->client_id);
                    $logService = new LogService();
                    $logService->log($client, 'Su servicio' . $clientCustomService->service_name . ' Custom fue colocado en address_list desde el ClientCustomServiceObserver::updating ');
                }
            }
        }
    }

    public function deleting(ClientCustomService $clientCustomService)
    {
        if ($clientCustomService->internet_id) {
            DeletedClientWithServiceJob::dispatch($clientCustomService);
            $this->liberaLaIpUsada($clientCustomService);
        }
    }

    public function liberaLaIpUsada($clientCustomService)
    {
        $networkIpRepository = new NetworkIpRepository();
        $networkIp = $networkIpRepository->getNetworkIpByClientInternetServiceId($clientCustomService->id);
        if ($networkIp) {
            $networkIpRepository->removeUsedIp($networkIp);
        }
    }

    public function customHaveInternet($custom)
    {
        return $custom->internet_id != null;
    }
}
