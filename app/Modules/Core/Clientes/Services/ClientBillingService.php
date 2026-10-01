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
                // FIX (1-oct-2026): eliminaLosServiciosDelAddressList() corría en cuanto
                // $newBalance >= 0, ANTES de saber si el pago cubrió algo — es decir, SIEMPRE,
                // incluso con un pago parcial que no cubre ni un ciclo. Esa llamada encola
                // ProcessCreateServiceJob (DeployService::deployService() — reconexión real a
                // nivel red/MikroTik), así que un pago parcial le devolvía el servicio al
                // cliente aunque el fix anterior (cobrarYActivarCliente) ya dejara su `estado`
                // en Bloqueado: el campo decía una cosa, la red hacía otra. Ahora corre DESPUÉS
                // de cobrarYActivarCliente() y SOLO si de verdad se cobró al menos un ciclo —
                // mismo booleano que ya gatea activarCliente(), para que estado y conectividad
                // queden consistentes.
                $seCobroAlMenosUnCiclo = $this->cobrarYActivarCliente($client, true, $transaction);
                if ($seCobroAlMenosUnCiclo) {
                    $this->eliminaLosServiciosDelAddressList($client);
                }
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

    private function cobrarYActivarCliente($client, $forceCobrar = false, $transaction = null): bool
    {
        // FIX (1-oct-2026, a petición de David tras verificar el cobro de deudas): antes
        // activarCliente() corría SIEMPRE, incluso cuando billingServicesByClient() no
        // cobró ni un ciclo completo (pago parcial que no cubre la deuda — forceCobrar=true
        // con N=0). Resultado real: un cliente Bloqueado con una deuda vieja se reactivaba
        // con solo abonar algo, sin haber cubierto lo que debía. Ahora billingServicesByClient()
        // devuelve si de verdad se cobró al menos un ciclo, y SOLO entonces se reactiva.
        // No se tocó removePeriodoGracia() (sigue limpiando el periodo de gracia aun con pago
        // parcial, igual que antes) — si el cliente vuelve a quedar en negativo, el observer
        // ClientBalanceObserver le asigna un periodo de gracia nuevo automáticamente, así que
        // no se queda en un estado muerto sin esa ventana.
        // El valor de retorno (1-oct-2026, segunda vuelta) lo reusa el llamador para gatear
        // también eliminaLosServiciosDelAddressList() — ver processPaymentForClientRecurrentWithGracePeriodActive.
        $seCobroAlMenosUnCiclo = $this->billingServicesByClient($client, null, $forceCobrar, $transaction);
        if ($seCobroAlMenosUnCiclo) {
            $client->activarCliente();
        }
        return $seCobroAlMenosUnCiclo;
    }

    public function billingServicesByClient(mixed $client, $typeBillingExecute = null, $forceCobrar = false, $transaction = null): bool
    {
        $clientRepository = new ClientRepository();
        $typeOfBilling = $clientRepository->getTypeOfBilling($client);

        // Reviso si el balance del cliente es suficiente para pagar todos los servicios activos del cliente
        $cuantasVecesSeLePuedeCobrar = $clientRepository->getCuantasVecesSeLePuedenCobrarLosServiciosActivos($client);

        if ($forceCobrar) {
            $this->logService->log($client, 'Cliente #' . $client->id . ' se le va a cobrar de manera forzada ' . $cuantasVecesSeLePuedeCobrar . ' veces y se elimna el periodo de gracia.');
            return $this->billingForce($client, $cuantasVecesSeLePuedeCobrar, $transaction);
        }

        if ($cuantasVecesSeLePuedeCobrar) {
            // Cobro y agrego nueva fecha de pago
            $this->actionBilling($clientRepository, $client, $cuantasVecesSeLePuedeCobrar, $transaction);

            // Actualizo fecha de corte
            $service = new BillingExpirationService($client);
            $client->refresh();
            $service->setNewFechaCorteForClient(null, $cuantasVecesSeLePuedeCobrar);

            $this->logService->log($client, 'Cliente #' . $client->id . ' se le va a cobrar ' . $cuantasVecesSeLePuedeCobrar . ' veces y se elimna el periodo de gracia.');
            return true;
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
                return false;
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
            return true;
        }

        $this->logService->log($client, 'Cliente #' . $client->id . ' no tiene suficiente balance para cobrar los servicios');
        return false;
    }

    private function billingForce($client, $cuantasVecesSeLePuedeCobrar, $transaction = null): bool
    {
        $cuantasVecesSeLePuedeCobrar = $cuantasVecesSeLePuedeCobrar ?: 0;
        $clientRepository = new ClientRepository();

        // BUG REAL DE PRODUCCIÓN (cliente #6722, reportado por David 30-sep-2026):
        // esta línea llamaba a removePeriodoGracia($client, true, N+1) — el `true`
        // hace que removePeriodoGracia() RECALCULE fecha_corte por su cuenta.
        // Cuando $cuantasVecesSeLePuedeCobrar (N) es 0 — un pago que NO alcanza a
        // cubrir ni un ciclo completo, como el del #6722 (traía la deuda a +19
        // contra un costo de 420) — ESTA era la ÚNICA escritura de fecha_corte en
        // todo el método (el bloque de abajo que la recalcula de verdad solo corre
        // si N>0), así que el cliente se reactivaba Y su corte avanzaba un ciclo
        // completo de regalo, sin haber cubierto nada. Eso es lo que se reportó
        // como "lo trató como prepago de sep-oct en vez de cobrar la deuda de
        // ago-sep". (Cuando N>0 esta escritura resultaba inofensiva en la práctica:
        // el bloque de abajo recalcula fecha_corte desde fecha_pago+billing_expiration
        // —ver BillingExpirationService::getFechaCorteForBillingPrepaidRecurrent(),
        // rama $fechaCorteAnterior— y sobrescribe lo que haya, así que no hay
        // "doble avance" real para ese caso; verificado con reproducción.)
        // Fix: removePeriodoGracia() solo limpia el periodo de gracia aquí, sin
        // tocar fecha_corte. El ÚNICO lugar que la mueve queda el bloque de abajo,
        // y SOLO si $cuantasVecesSeLePuedeCobrar > 0 — un pago que no cubre ni un
        // ciclo completo deja fecha_corte intacta, no se le regala plazo.
        // Verificado con una reproducción real (cliente #17, transacción revertida):
        // pago que no cubre un ciclo → fecha_corte ya NO se mueve (antes sí).
        $clientRepository->removePeriodoGracia($client);

        $this->logService->log($client, 'Cliente #' . $client->id . ' se elimina el periodo de gracia desde billingForce');

        if ($cuantasVecesSeLePuedeCobrar > 0) {
            $this->actionBilling($clientRepository, $client, $cuantasVecesSeLePuedeCobrar, $transaction);

            // Actualizo fecha de corte
            $service = new BillingExpirationService($client);
            $service->setNewFechaCorteForClient(null, $cuantasVecesSeLePuedeCobrar);

            $this->logService->log($client, 'Cliente #' . $client->id . ' se establece nueva fecha de corte');

            return true;
        }

        // N=0: pago que no alcanzó a cubrir ni un ciclo completo — no se cobró nada,
        // cobrarYActivarCliente() NO debe reactivar al cliente con esto.
        return false;
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
        // BUG REAL (hallazgo relacionado al #6722, 1-oct-2026): esta línea hacía
        // RectifyBalanceAndCreateTransaction::dispatch(...) — SOLO encola el job
        // (QUEUE_CONNECTION=database), no espera a que corra. Justo abajo,
        // setFechaPago() se ejecutaba de inmediato, en el mismo request, sin
        // esperar al worker. Verificado con reproducción real (transacción
        // revertida, cola real sin correr queue:work): fecha_pago YA avanzaba
        // mientras el cargo seguía pendiente en la tabla `jobs`, sin crear
        // ninguna fila nueva en `transactions`. Si el worker tarda, se cae o el
        // job falla, fecha_pago queda adelantado para siempre sin el cargo
        // correspondiente — el cliente "pagó" una fecha que nunca se le cobró.
        // Fix: dispatchSync() en vez de dispatch() — corre el job EN ESTE MISMO
        // proceso (mismo patrón oficial de Laravel para forzar un job en cola a
        // ejecutarse inline), así setFechaPago() de abajo solo se alcanza
        // después de que el cargo de CADA servicio ya se creó de verdad. Si
        // algún servicio falla, la excepción interrumpe el método aquí mismo y
        // fecha_pago NUNCA se mueve — correcto: mejor reintentar la cobranza
        // completa que avanzar fechas con cargos a medias. El job en sí no hace
        // nada pesado (2 escrituras a BD, sin llamadas externas) y ya corre
        // dentro de contextos de background (PaymentClientJob / cron), nunca
        // directo en un request HTTP — no hay riesgo de bloquear al usuario.
        $clientWithServices = $clientRepository->getServicesForClient($client->id);
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                RectifyBalanceAndCreateTransaction::dispatchSync($clientService, $cuantasVecesSeLePuedeCobrar, $transaction);
            }
        }

        // Actualizo fecha de pago nueva — solo se llega aquí si el bucle de
        // arriba ya terminó de verdad (ver comentario arriba).
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
