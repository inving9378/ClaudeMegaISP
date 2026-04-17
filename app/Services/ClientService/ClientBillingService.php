<?php

namespace App\Services\ClientService;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Jobs\Client\BillingService\RectifyBalanceAndCreateTransaction;
use App\Jobs\ProcessCreateServiceJob;
use App\Models\Client;
use App\Models\TypeBilling;
use App\Repositories\BalanceRepository;
use App\Services\LogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class ClientBillingService
{
    const TYPE_BILLING_EXECUTED_PROCESS = 'process_command';

    protected $logService;
    public function __construct()
    {
        $this->logService = new LogService();
    }

    public function billing($client, $newBalance, $transaction = null)
    {
        $this->processPaymentForClientRecurrentWithGracePeriodActive($client, $newBalance, $transaction);
        $this->processPaymentForRestOfClient($client, $newBalance, $transaction);
    }

    public function processPaymentForClientRecurrentWithGracePeriodActive(Client $client, $newBalance, $transaction = null)
    {
        // check if is a recurrent user and it has grade period active
        if ($this->iSClientRecurrent($client) && $this->clientHasGracePeriodActive($client)) {
            if ($newBalance >= 0) {
                $this->eliminaLosServiciosDelAddressList($client);
                $this->cobrarYActivarCliente($client, true, $transaction);
            }
        }
    }

    public function processPaymentForRestOfClient(Client $client, $newBalance, $transaction = null)
    {
        if (!$this->clientHasGracePeriodActive($client)) {
            $clientRepository = new ClientRepository();
            $costAllServices = $clientRepository->getCostAllService($client->id);
            if ($newBalance >= $costAllServices) {
                $this->eliminaLosServiciosDelAddressList($client);
                $this->cobrarYActivarCliente($client, false, $transaction);
            }
        }
    }

    private function cobrarYActivarCliente($client, $forceCobrar = false, $transaction = null)
    {
        $this->billingServicesByClient($client, null, $forceCobrar, $transaction);
        $client->activarCliente();
    }

    public function billingServicesByClient(mixed $client, $typeBillingExecute = null, $forceCobrar = false, $transaction = null)
    {
        $clientRepository = new ClientRepository();
        $typeOfBilling = $clientRepository->getTypeOfBilling($client);

        // Reviso si el balance del cliente es suficiente para pagar todos los servicios activos del cliente
        $cuantasVecesSeLePuedeCobrar = $clientRepository->getCuantasVecesSeLePuedenCobrarLosServiciosActivos($client);

        if ($forceCobrar) {
            $this->logService->log($client, 'Cliente #' . $client->id . ' se le va a cobrar de manera forzada ' . $cuantasVecesSeLePuedeCobrar . ' veces y se elimna el periodo de gracia.');
            $this->billingForce($client, $cuantasVecesSeLePuedeCobrar, $transaction);
            return;
        }

        if ($cuantasVecesSeLePuedeCobrar) {
            // Cobro y agrego nueva fecha de pago
            $this->actionBilling($clientRepository, $client, $cuantasVecesSeLePuedeCobrar, $transaction);

            // Actualizo fecha de corte
            $service = new BillingExpirationService($client);
            $client->refresh();
            $service->setNewFechaCorteForClient(null, $cuantasVecesSeLePuedeCobrar);

            $this->logService->log($client, 'Cliente #' . $client->id . ' se le va a cobrar ' . $cuantasVecesSeLePuedeCobrar . ' veces y se elimna el periodo de gracia.');
            return;
        }

        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT && $typeBillingExecute == self::TYPE_BILLING_EXECUTED_PROCESS) {
            $this->actionBilling($clientRepository, $client, 1, $transaction);
            $this->logService->log($client, 'Cliente #' . $client->id . ' no tiene suficiente balance pero le cobro el servicio');
            return;
        }

        $this->logService->log($client, 'Cliente #' . $client->id . ' no tiene suficiente balance para cobrar los servicios');
    }

    private function billingForce($client, $cuantasVecesSeLePuedeCobrar, $transaction = null)
    {
        $cuantasVecesSeLePuedeCobrar = $cuantasVecesSeLePuedeCobrar ?: 0;
        $clientRepository = new ClientRepository();
        $clientRepository->removePeriodoGracia($client, true, $cuantasVecesSeLePuedeCobrar + 1);

        $this->logService->log($client, 'Cliente #' . $client->id . ' se elimina el periodo de gracia desde billingForce');

        if ($cuantasVecesSeLePuedeCobrar > 0) {
            $this->actionBilling($clientRepository, $client, $cuantasVecesSeLePuedeCobrar, $transaction);

            // Actualizo fecha de corte
            $service = new BillingExpirationService($client);
            $service->setNewFechaCorteForClient(null, $cuantasVecesSeLePuedeCobrar);

            $this->logService->log($client, 'Cliente #' . $client->id . ' se establece nueva fecha de corte');
        }
    }

    public function getClientToBillingServices($date = null)
    {
        return (new ClientRepository())->getClientsToBillingServices($date);
    }


    public function billingClientServices()
    {
        // Obtener clientes desde el repo
        $clientsToBilling = $this->getClientToBillingServices();
        // Procesar de a 200 para evitar saturar la memoria
        foreach ($clientsToBilling->chunk(200) as $chunk) {
            foreach ($chunk as $client) {
                DB::beginTransaction();
                try {
                    // Todo el billing completo del cliente
                    $this->billingServicesByClient(
                        $client,
                        self::TYPE_BILLING_EXECUTED_PROCESS
                    );
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    // Es MUY importante loguearlo para revisar qué pasó
                    Log::error("Billing failed for client {$client->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $logService = new LogService();
                    $logService->log($client, 'Fallo el cobro de servicios desde el comando  para el cliente  ' . $client->id);
                }
            }
            gc_collect_cycles();
        }
    }

    protected function actionBilling($clientRepository, $client, $cuantasVecesSeLePuedeCobrar = 1, $transaction = null)
    {
        // Cobro todos los servicios
        $clientWithServices = $clientRepository->getServicesForClient($client->id);
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                RectifyBalanceAndCreateTransaction::dispatch($clientService, $cuantasVecesSeLePuedeCobrar, $transaction);
            }
        }

        // Actualizo fecha de pago nueva
        $billingPaymentDateService = new BillingPaymentDateService();
        $newPaymentDate = $billingPaymentDateService->getNewFechaPagoByClient($client, $cuantasVecesSeLePuedeCobrar);
        $clientRepository->setFechaPago($client, $newPaymentDate);
    }

    public function iSClientRecurrent(Client $client)
    {
        $clientRepository = new ClientRepository();
        $typeOfBilling = $clientRepository->getTypeOfBilling($client);
        return $typeOfBilling === TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT;
    }

    private function clientHasGracePeriodActive(Client $client)
    {
        return $client->fecha_fin_periodo_gracia != null;
    }

    private function eliminaGracePeriod(Client $client)
    {
        $clientRepository = new ClientRepository();
        $clientRepository->removePeriodoGracia($client);
        activity()->tap(function (Activity $activity) use ($client) {
            $activity->client_id = $client->id;
        })->log('Cliente #' . $client->id . ' pago su deuda del periodo de gracia');
    }

    private function eliminaLosServiciosDelAddressList(Client $client)
    {
        $repository = new ClientRepository();
        $clientServices = $repository->getServicesForClient($client->id);
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientServices->$service as $clientService) {
                ProcessCreateServiceJob::dispatch($clientService);
            }
        }
    }
}
