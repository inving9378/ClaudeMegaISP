<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Services\BillingExpirationService;
use App\Modules\Core\Clientes\Services\BillingPaymentDateService;
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
