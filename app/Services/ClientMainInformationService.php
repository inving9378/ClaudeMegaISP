<?php

namespace App\Services;

use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Models\TypeBilling;
use App\Services\ClientService\BillingExpirationService;
use App\Services\ClientService\BillingPaymentDateService;
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
