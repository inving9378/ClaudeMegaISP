<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Services\ClientService\BillingExpirationService;
use App\Services\ClientService\BillingPaymentDateService;
use Carbon\Carbon;

class ClientHelperController extends Controller
{
    protected $client;
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function stepNeededWhenNewClientIsCreated()
    {
        $this->client->clientCreateBalance();
        $this->client->clientCreateReminderConfiguration();
        $this->client->clientCreateBillingConfiguration();
        $repository = new ClientRepository();
        $repository->setFechaCorte($this->client, Carbon::now()->endOfDay()->toDateTimeString());
        $repository = new ClientRepository();
        $repository->setFechaPago($this->client, Carbon::now()->subDay()->endOfDay()->toDateTimeString());
    }
}
