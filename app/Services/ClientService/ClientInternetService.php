<?php

namespace App\Services\ClientService;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Services\LogService;

class ClientInternetService implements ClientServiceInterface
{
    protected $model;
    public function __construct($model)
    {
        $this->model = $model;
    }

    public function deploy()
    {
        $repository =  $this->model->getRepository();
        $repository = new $repository();
        $repository->setDeployedTrueAndActiveService($this->model);
    }


    public function verifyIsClientActiveOrNotAndRectifyMikrotik()
    {
        //Verificar si el cliente esta activo o No para meterlo o sacarlo del address list
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientMainInformation = $clientMainInformationRepository->getClientMainInformationByClientId($this->model->client_id);
        $client = (new ClientRepository)->getClientById($this->model->client_id);
        $logService = new LogService();
        if ($clientMainInformation->estado !== ComunConstantsController::STATE_ACTIVE) {
            MikrotikCreateAddressList::dispatch($this->model);
            $logService->log($client, 'Su servicio' . $this->model->service_name . ' fue colocado en el address_list desde observer created ClientServiceService');
        } else {
            MikrotikRemoveClientServiceFromAddressList::dispatch($this->model);
            $logService->log($client, 'Su servicio' . $this->model->service_name . ' fue removido del address_list desde observer created ClientServiceService');
        }
    }
}
