<?php

namespace App\Observers;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\ClientUserRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Models\ClientInternetService;
use App\Jobs\DeletedClientWithServiceJob;
use App\Http\Traits\RouterConnection;
use App\Jobs\CreateClientWithServiceJob;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Services\ClientMainInformationService;
use App\Services\LogService;

class ClientInternetServiceObserver
{
    use RouterConnection;

    public function creating(ClientInternetService $clientInternetService)
    {
        $clientRepository = new ClientRepository();
        $client_services = $clientRepository->getClientServicesFilteredByServiceId($clientInternetService->client_id);
        if (!$client_services) {
            $service = new ClientMainInformationService($clientInternetService->client_id);
            $service->setStateBlocked();
        }
    }

    public function created(ClientInternetService $clientInternetService)
    {
        if ($clientInternetService->client_bundle_service_id == null) {
            SetIPToClientInternetServiceJob::dispatch($clientInternetService);
            $modelInternet = 'App\Models\Internet';
            CreateClientWithServiceJob::dispatch($clientInternetService, $modelInternet);
        }

        $this->verifyIsActiveOrNotAndRectifyMikrotik($clientInternetService);
        $clientUserRepository = new ClientUserRepository();
        $clientUserRepository->create($clientInternetService->client_name, $clientInternetService->router_id, $clientInternetService->id, $clientInternetService->client_id);
    }

    public function updating(ClientInternetService $clientInternetService)
    {
        $clientUserRepository = new ClientUserRepository();
        $clientUser = $clientUserRepository->getModelByServiceId($clientInternetService->id);
        if ($clientUser && $clientUser->user != $clientInternetService->client_name) {
            $clientUser->update([
                'user' => $clientInternetService->client_name
            ]);
            $this->updateUserNameInRouter($clientInternetService);
        }

        $this->verifyIsActiveOrNotAndRectifyMikrotik($clientInternetService);
    }

    public function updated(ClientInternetService $clientInternetService)
    {
        $previousPassword = $clientInternetService->getOriginal('password');
        if ($previousPassword != $clientInternetService->password) {
            $this->updatePasswordInRouter($clientInternetService);
        }
    }

    /**
     * Handle the ClientInternetService "deleted" event.
     *
     * @param \App\Models\ClientInternetService $clientInternetService
     * @return void
     */
    public function deleting(ClientInternetService $clientInternetService)
    {
        DeletedClientWithServiceJob::dispatch($clientInternetService);
        $this->liberaLaIpUsada($clientInternetService);
    }


    public function changedRouter($clientInternetService)
    {
        $routerIdOld = $clientInternetService->getOriginal('router_id');
        $routerIdNew = $clientInternetService->router_id;
        if ($routerIdOld != $routerIdNew) {
            return true;
        }
        return false;
    }

    public function changedAssignmentMethod($clientInternetService)
    {
        $assignmentMethodOld = $clientInternetService->getOriginal('ipv4_assignment');
        $assignmentMethodNew = $clientInternetService->ipv4_assignment;
        if ($assignmentMethodOld != $assignmentMethodNew) {
            return true;
        }
        return false;
    }

    public function liberaLaIpUsada($clientInternetService)
    {
        $networkIpRepository = new NetworkIpRepository();
        $networkIp = $networkIpRepository->getNetworkIpByClientInternetServiceId($clientInternetService->id);
        if ($networkIp) {
            $networkIpRepository->removeUsedIp($networkIp);
        }

        $clientUserRepository = new ClientUserRepository();
        $clientUser = $clientUserRepository->getModelByServiceId($clientInternetService->id);
        if ($clientUser) {
            $clientUser->delete();
        }
    }

    protected function verifyIsActiveOrNotAndRectifyMikrotik($clientInternetService)
    {
        //Verificar si el cliente esta activo o No para meterlo o sacarlo del address list
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientMainInformation = $clientMainInformationRepository->getClientMainInformationByClientId($clientInternetService->client_id);
        $client = (new ClientRepository)->getClientById($clientInternetService->client_id);
        $logService = new LogService();
        if ($clientMainInformation->estado !== ComunConstantsController::STATE_ACTIVE) {
            MikrotikCreateAddressList::dispatch($clientInternetService);
            $logService->log($client, 'Su servicio' . $clientInternetService->service_name . ' fue colocado en el address_list desde observer created ClientInternetServiceObserver');
        } else {
            MikrotikRemoveClientServiceFromAddressList::dispatch($clientInternetService);
            $logService->log($client, 'Su servicio' . $clientInternetService->service_name . ' fue removido del address_list desde observer created ClientInternetServiceObserver');
        }
    }
}
