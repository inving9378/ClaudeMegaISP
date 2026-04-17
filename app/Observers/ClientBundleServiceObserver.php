<?php

namespace App\Observers;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Models\ClientBundleService;
use App\Http\Traits\RouterConnection;
use App\Jobs\CreateClientWithServiceJob;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use App\Services\ClientMainInformationService;
use App\Services\LogService;
use Illuminate\Support\Facades\Log;

class ClientBundleServiceObserver
{
    use RouterConnection;

    public function creating(ClientBundleService $clientBundleService)
    {
        $clientRepository = new ClientRepository();
        $client_services = $clientRepository->getClientServicesFilteredByServiceId($clientBundleService->client_id);
        if (!$client_services) {
            $service = new ClientMainInformationService($clientBundleService->client_id);
            $service->setStateBlocked();
        }
    }

    public function updating(ClientBundleService $clientBundleService)
    {
        $internets = $clientBundleService->service_internet;
        $customs = $clientBundleService->service_custom;
        $this->updateDeployedServices($internets, $customs);
    }

    private function updateDeployedServices($internets, $customs)
    {
        $services = $internets->concat($customs);

        foreach ($services as $service) {
            $this->verifyIsActiveOrNotAndRectifyMikrotik($service);
        }
    }

    public function customHaveInternet($custom)
    {
        return !is_null($custom->internet_id);
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
            $logService->log($client, 'Su servicio' . $clientInternetService->service_name . ' fue colocado en el address_list desde observer updated ClientInternetServiceObserver');
        } else {
            MikrotikRemoveClientServiceFromAddressList::dispatch($clientInternetService);
            $logService->log($client, 'Su servicio' . $clientInternetService->service_name . ' fue removido del address_list desde observer updated ClientInternetServiceObserver');
        }
        $modelInternet = 'App\Models\Internet';
        CreateClientWithServiceJob::dispatch($clientInternetService, $modelInternet);
    }
}
