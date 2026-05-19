<?php

namespace App\Modules\Core\Clientes\Services;

use App\Modules\Core\Clientes\Repositories\ClientMainInformationRepository;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use App\Models\TypeBilling;
use App\Modules\Core\Clientes\Services\BillingExpirationService;
use App\Modules\Core\Clientes\Services\BillingPaymentDateService;
use Illuminate\Support\Facades\Log;

class ClientMainInformationService
{

    protected $client;
    /**
     * @param mixed $client_id
     */
    public function __construct($client_id)
    {
        $this->client = $client_id;
    }

    public function setStateBlocked()
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientMainInformationRepository->setClientMainInformationByClientId($this->client);
        $clientMainInformationRepository->setStateBlocked();
    }

    public function setStateActive()
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientMainInformationRepository->setClientMainInformationByClientId($this->client);
        $clientMainInformationRepository->setStateActive();
    }

    public function setStateInactive()
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientMainInformationRepository->setClientMainInformationByClientId($this->client);
        $clientMainInformationRepository->setStateInactive();
    }
}
