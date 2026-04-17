<?php

namespace App\Console\Commands\Disabled\Old;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Jobs\Client\Invoice\ClientInvoiceJob;
use App\Services\ClientMainInformationService;
use App\Services\ClientService\BillingExpirationService;
use App\Services\GetNewBalanceByTypeOfBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AddInvoiceAndRectifyBalanceForClientsWithGracePeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add_invoice_and_rectify_balance_for_clients_with_grace_period:process';
    private $clientRepository;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Agrega factura y Rectifica el balance a clientes con periodo de gracia activo';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
        $this->obtenerLosClientesConPeriodoDeGraciaActivo();
    }

    public function obtenerLosClientesConPeriodoDeGraciaActivo()
    {
        $clients = $this->clientRepository->getClientsWithGracePeriodAndBalance();
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;

        foreach ($clients as $client) {
            $clientServices = $this->clientRepository->getServiceNotCharged($client->id);

            foreach ($services as $service) {
                foreach ($clientServices->$service as $clientService) {
                    $this->rectifyBalanceAndCreateDebitTransaction($clientService, $client);
                    ClientInvoiceJob::dispatch($clientService, false);
                }
            }

            if ($this->gracePeriodExpiredNow($client)) {
                $service = new ClientMainInformationService($client->id);
                $service->setStateInactive();
            }
        }
    }

    public function gracePeriodExpiredNow($client)
    {
        $daysOfGracePeriod = $client->billing_configuration->grace_period;
        $fechaFin = $client->client_grace_period->created_at;
        $fechaActual = Carbon::now();
        $fechaFinPeriodoGracia = $fechaFin->addDays($daysOfGracePeriod)->startOfDay();
        $fechaActual = $fechaActual->startOfDay();
        return ($fechaActual->gte($fechaFinPeriodoGracia));
    }

    public function rectifyBalanceAndCreateDebitTransaction($service, $client)
    {
        $util = new GetNewBalanceByTypeOfBillingService($service, $client);
        $newBalanceAndPrice = $util->getNewBalanceAndPriceByTypeOfBilling();
        $planRelation = $service->getPlanRelation();
        $this->clientRepository->rectifyBalance($service, $newBalanceAndPrice);
        $this->clientRepository->addDebitTransactionForPaymentService($planRelation, $service, $newBalanceAndPrice);
    }
}
