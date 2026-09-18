<?php

namespace App\Modules\Core\Clientes\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use App\Jobs\Client\BillingService\RectifyBalanceAndCreateTransaction;
use App\Jobs\ProcessCreateServiceJob;
use App\Modules\Core\Clientes\Models\Client;
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
            // FIX (2026-09-18, decisión de Irving): un cliente RECURRENT sin saldo
            // suficiente solo debe seguir acumulando adeudo automático mientras siga
            // DENTRO de la duración de su contrato (ej. contrató 12 meses, pagó 2 → los
            // 10 restantes SÍ se acumulan; el mes 13 en adelante NO — prepago nunca
            // acumula, eso ya era así). Antes esta rama cobraba indefinidamente sin
            // tope: 93 clientes reales en prod llevaban hasta 26 meses de sobre-cobro
            // automático estando ya Bloqueados. Reusa clientHasReachedContractCap(),
            // que a su vez reusa el cálculo YA EXISTENTE y usado en el documento de
            // contrato (ClientService::getDataPendingPayments()) — cuenta PAGOS REALES
            // hechos vs. duración contratada, no cuántas veces el cron ya cobró de más.
            if ($this->clientHasReachedContractCap($client)) {
                $this->logService->log($client, 'Cliente #' . $client->id . ' ya cubrió (con pagos reales) la duración completa de su contrato — no se le cobra más automáticamente sin saldo.');
                return;
            }

            $this->actionBilling($clientRepository, $client, 1, $transaction);

            // FIX (2026-09-18, encontrado en prod al reparar los 30 clientes de la
            // regresión de V1.35): esta rama avanzaba fecha_pago vía actionBilling()
            // pero NUNCA llamaba a setNewFechaCorteForClient() — a diferencia de la
            // rama hermana de arriba ($cuantasVecesSeLePuedeCobrar), que sí hace
            // ambas cosas. Resultado real: un cliente RECURRENT sin saldo suficiente
            // que el cron igual cobra (billing_service_command:process, rama "no
            // tiene suficiente balance pero le cobro el servicio") veía fecha_pago
            // avanzar mes a mes mientras fecha_corte quedaba CONGELADA en el valor
            // que tenía desde que salió del período de gracia — con fecha_corte vieja
            // y fecha_pago muy adelantada, el cron de suspensión lo bloqueaba
            // injustamente pese a estar "al día" según su fecha_pago real (casos
            // reales en prod: #7408, #6861).
            $service = new BillingExpirationService($client);
            $client->refresh();
            $service->setNewFechaCorteForClient(null, 1);

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

    /**
     * ¿Ya cubrió (con pagos REALES) la duración completa de su contrato? Reusa
     * ClientService::getDataPendingPayments() — el mismo cálculo ya usado para el
     * documento de contrato (contract_months de duration_contracts vs. pagos reales
     * en `transactions` tipo Pago) — en vez de duplicar la fórmula. `mesesRestantes`
     * ya viene topado en 0 por ese método si el cliente pagó igual o más meses de los
     * que contrató. Sin `duration_contract` asignado (0 meses) → conservador: se trata
     * como ya cubierto (no se le acumula nada más sin ese dato).
     */
    private function clientHasReachedContractCap(Client $client): bool
    {
        $clientService = new ClientService($client);
        $data = $clientService->getDataPendingPayments();

        return ($data['mesesRestantes'] ?? 0) <= 0;
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
