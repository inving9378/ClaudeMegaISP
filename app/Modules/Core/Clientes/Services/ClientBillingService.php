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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class ClientBillingService
{
    const TYPE_BILLING_EXECUTED_PROCESS = 'process_command';

    protected $logService;

    /** Mensualidades de deuda que el pago en curso saldó (solo recurrentes; ver CorteRecurrenteCalculator). */
    private int $ciclosSaldadosPorPago = 0;
    private bool $corteAvanzadoPorPago = false;
    public function __construct()
    {
        $this->logService = new LogService();
    }

    public function billing($client, $newBalance, $transaction = null)
    {
        // FIX (1-oct-2026, hallazgo al verificar a fondo los 4 fixes de pagos recurrentes de
        // hoy — no causado por ninguno de ellos, ya pasaba antes): las dos ramas de abajo
        // corrían SIEMPRE las dos, en secuencia, sin ser mutuamente excluyentes. Para un
        // cliente recurrente con periodo de gracia activo que paga lo que debe,
        // processPaymentForClientRecurrentWithGracePeriodActive() cobra y limpia el periodo
        // de gracia; como processPaymentForRestOfClient() usa el MISMO $newBalance (el saldo
        // de ANTES del pago, no el ya actualizado) y el periodo de gracia ya quedó limpio, su
        // propio guard también se cumplía — y volvía a reconectar/facturar el MISMO pago una
        // segunda vez. Verificado con reproducción real (cliente #17, transacción revertida,
        // cola forzada a sync para ver el resultado final): UN pago generaba DOS facturas
        // idénticas en client_invoices (mismo monto, mismo segundo) — el saldo del cliente NO
        // se duplicaba (eso sí estaba bien), solo el documento de factura.
        // Fix: las dos ramas pasan a ser mutuamente excluyentes para UN mismo pago — si el
        // camino de periodo de gracia aplicó (el cliente calificaba para esa rama), no se
        // intenta también el camino normal para el mismo evento de pago.
        // REGLA DE CORTE DE RECURRENTES (2026-10-14): el corte avanza un mes por cada mensualidad
        // PAGADA, desde el corte anterior. Se calcula aquí, con el saldo de antes y de después del
        // pago, cuántas mensualidades de deuda saldó este pago (puede ser 0, parcial o varias).
        $this->ciclosSaldadosPorPago = 0;
        $this->corteAvanzadoPorPago = false;
        if ($this->iSClientRecurrent($client) && $transaction && $transaction->credit > 0) {
            $costo = (new ClientRepository())->getCostAllService($client->id);
            $this->ciclosSaldadosPorPago = CorteRecurrenteCalculator::ciclosSaldados(
                $newBalance - $transaction->credit,
                $newBalance,
                $costo
            );
        }

        try {
            $procesadoPorGracia = $this->processPaymentForClientRecurrentWithGracePeriodActive($client, $newBalance, $transaction);
            if (!$procesadoPorGracia) {
                $this->processPaymentForRestOfClient($client, $newBalance, $transaction);
            }

            // Pago que saldó mensualidades pero dejó deuda (ej. debía 3, pagó 2): ninguna de las
            // ramas de arriba cobra un ciclo nuevo, pero el corte SÍ debe subir por lo saldado.
            if ($this->ciclosSaldadosPorPago > 0 && !$this->corteAvanzadoPorPago) {
                $this->moverCorteTrasCobro($client, 0);
            }
        } finally {
            $this->ciclosSaldadosPorPago = 0;
            $this->corteAvanzadoPorPago = false;
        }
    }

    /**
     * Mueve fecha_corte tras un pago/cobro. Recurrentes: corte anterior + (ciclos cobrados ahora +
     * ciclos de deuda saldados por el pago). Otros tipos: comportamiento de siempre.
     */
    private function moverCorteTrasCobro(Client $client, int $ciclosCobrados): void
    {
        if ($this->iSClientRecurrent($client)) {
            $total = $ciclosCobrados + $this->ciclosSaldadosPorPago;
            if ($total > 0) {
                $client->refresh();
                (new BillingExpirationService($client))->avanzarCorteRecurrente($total);
                $this->corteAvanzadoPorPago = true;
                $this->logService->log($client, 'Cliente #' . $client->id . ' recurrente: fecha de corte avanza ' . $total . ' mes(es) (cobrados ' . $ciclosCobrados . ' + saldados ' . $this->ciclosSaldadosPorPago . ') desde el corte anterior');
            }
            return;
        }

        if ($ciclosCobrados > 0) {
            $client->refresh();
            (new BillingExpirationService($client))->setNewFechaCorteForClient(null, $ciclosCobrados);
        }
    }

    public function processPaymentForClientRecurrentWithGracePeriodActive(Client $client, $newBalance, $transaction = null): bool
    {
        // check if is a recurrent user and it has grade period active
        // Regla 2026-10-14: la gracia ya no existe para quien no cumplió su contrato, pero ese
        // cliente también puede estar en deuda; su pago debe pasar por la misma rama (cobrar,
        // quitar gracia, avanzar el corte por lo saldado y reactivar). Por eso la rama aplica si
        // tiene gracia O si antes de este pago su saldo era negativo.
        $teniaDeuda = $transaction && $transaction->credit > 0 && ($newBalance - $transaction->credit) < 0;
        if ($this->iSClientRecurrent($client) && ($this->clientHasGracePeriodActive($client) || $teniaDeuda)) {
            if ($newBalance >= 0) {
                // FIX (1-oct-2026): eliminaLosServiciosDelAddressList() corría en cuanto
                // $newBalance >= 0, ANTES de saber si el pago dejó al cliente sin deuda. Esa
                // llamada encola ProcessCreateServiceJob (DeployService::deployService() —
                // reconexión real a nivel red/MikroTik), así que un pago que NO alcanzaba a
                // saldar la deuda le devolvía el servicio al cliente aunque su `estado` quedara
                // Bloqueado: el campo decía una cosa, la red hacía otra. Ahora corre DESPUÉS de
                // cobrarYActivarCliente() y SOLO si de verdad quedó sin deuda — mismo booleano
                // que ya gatea activarCliente() (criterio: balance >= 0 tras el pago, no si se
                // cobró un ciclo nuevo — ver el comentario detallado en cobrarYActivarCliente()),
                // para que estado y conectividad queden consistentes.
                $quedoSinDeuda = $this->cobrarYActivarCliente($client, true, $transaction);
                if ($quedoSinDeuda) {
                    $this->eliminaLosServiciosDelAddressList($client);
                }
                // Este evento de pago ya fue atendido por esta rama (haya cobrado un ciclo
                // completo o no) — billing() no debe caer también a processPaymentForRestOfClient().
                return true;
            }
        }
        return false;
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
        $this->billingServicesByClient($client, null, $forceCobrar, $transaction);

        // FIX (1-oct-2026, primera vuelta, a petición de David tras verificar el cobro de
        // deudas): activarCliente() corría SIEMPRE, incluso con un pago que no cubría ni un
        // ciclo completo — un cliente Bloqueado se reactivaba con solo abonar algo, sin haber
        // cubierto su deuda. El primer intento de fix gateó la reactivación con "¿se cobró al
        // menos un ciclo completo en ESTA llamada?" (el booleano de billingServicesByClient()).
        //
        // BUG REAL ENCONTRADO (1-oct-2026, segunda vuelta — reportado por otra terminal con el
        // caso real del cliente #6722): ese criterio estaba mal. El cron ya había cargado el
        // ciclo adeudado en una corrida ANTERIOR (dejando el balance en negativo y asignando el
        // periodo de gracia vía ClientBalanceObserver). Cuando el cliente paga esa deuda ya
        // cargada — balance pasa de negativo a 0 o un poco positivo (+19 en el caso real) — NO
        // se cobra un ciclo NUEVO en esta llamada (el costo de un ciclo completo ya no cabe en
        // ese saldo pequeño), así que $seCobroAlMenosUnCiclo daba false y el cliente se quedaba
        // Bloqueado PARA SIEMPRE — pagó su deuda y el servicio nunca volvió solo. Verificado con
        // reproducción real (cliente #17 y #7716, dos costos de ciclo distintos, transacción
        // revertida): pagar exactamente la deuda, o un poco de más (como el #6722), dejaba al
        // cliente Bloqueado y encima sin periodo de gracia (removePeriodoGracia() sí corrió).
        //
        // Fix correcto: el criterio de reactivación es el BALANCE tras el pago, no si hubo un
        // cargo nuevo. Saldo >= 0 (deuda saldada o de más, haya o no ciclo nuevo cobrado) →
        // reactiva. Saldo sigue negativo (pago parcial real, deuda sin cubrir) → se mantiene
        // Bloqueado. Esto es consistente con que el sistema es prepago: balance negativo ES la
        // deuda; balance >= 0 significa que no hay deuda pendiente.
        $client->load('balance');
        $sinDeuda = $client->balance->amount >= 0;

        if ($sinDeuda) {
            $client->activarCliente();
        }

        // El valor de retorno lo reusa el llamador para gatear también
        // eliminaLosServiciosDelAddressList() — ver processPaymentForClientRecurrentWithGracePeriodActive.
        // Antes de hoy (código preexistente) esa reconexión corría siempre que $newBalance >= 0,
        // sin gate — este criterio restaura exactamente ese comportamiento para el caso de deuda
        // saldada, y sigue bloqueando la reconexión solo cuando el pago no alcanza a cubrir la deuda.
        return $sinDeuda;
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
            $this->moverCorteTrasCobro($client, $cuantasVecesSeLePuedeCobrar);

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
            //
            // CAMBIO (2026-10-14, reglas de gracia de recurrentes — ver GraciaRecurrenteCalculator):
            //  - DENTRO del contrato: se cobra mientras la deuda acumulada sea menor a los meses
            //    que le faltan del contrato (antes no había tope mientras no pagara).
            //  - CONTRATO CUMPLIDO: se cobra durante la gracia de 90 días (este primer cobro la
            //    inicia) y se deja de cobrar cuando vence; de ahí pasa a inactivo (comando aparte).
            $mesesRestantes = (int) ((new ClientService($client))->getDataPendingPayments()['mesesRestantes'] ?? 0);
            $costoCiclo = (float) $clientRepository->getCostAllService($client->id);
            $client->load('balance');
            $ciclosDeuda = CorteRecurrenteCalculator::ciclosPendientes((float) $client->balance->amount, $costoCiclo);
            $finGracia = $client->fecha_fin_periodo_gracia ? Carbon::parse($client->fecha_fin_periodo_gracia) : null;

            if (!GraciaRecurrenteCalculator::puedeCobrarSinSaldo($mesesRestantes, $ciclosDeuda, $finGracia, Carbon::now())) {
                $this->logService->log($client, 'Cliente #' . $client->id . ' no se le cobra automáticamente sin saldo: '
                    . ($mesesRestantes > 0
                        ? 'su deuda (' . $ciclosDeuda . ' mensualidades) ya alcanzó los ' . $mesesRestantes . ' meses que le faltan de contrato'
                        : 'su periodo de gracia venció'));
                return false;
            }

            if (GraciaRecurrenteCalculator::aplicaGracia($mesesRestantes) && !$finGracia) {
                $clientRepository->addPeriodoGracia($client);
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
            // CAMBIO (2026-10-14, regla de corte de recurrentes): aquí se llamaba a
            // setNewFechaCorteForClient(null, 1), que adelantaba fecha_corte junto con fecha_pago
            // por un cobro que el cliente NO ha pagado (así los clientes 7656/7663/7664 pasaron de
            // octubre a noviembre sin pagar). El corte es la fecha en que se corta el servicio si
            // no paga: este cobro solo acumula deuda y mueve fecha_pago; el corte avanza cuando el
            // cliente PAGA (moverCorteTrasCobro / CorteRecurrenteCalculator). Revierte el efecto
            // del fix del 2026-09-18 (#7408/#6861), que asumía "cobrado = al día".

            $this->logService->log($client, 'Cliente #' . $client->id . ' no tiene suficiente balance pero le cobro el servicio (acumula deuda; fecha_corte no se mueve)');
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
            $this->moverCorteTrasCobro($client, $cuantasVecesSeLePuedeCobrar);

            $this->logService->log($client, 'Cliente #' . $client->id . ' se establece nueva fecha de corte');

            return true;
        }

        // Pago que no alcanzó a cobrar un ciclo nuevo pero SÍ saldó mensualidades de deuda
        // (recurrentes): el corte sube por esas mensualidades. Sin deuda saldada no mueve nada.
        $this->moverCorteTrasCobro($client, 0);

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
    public function clientHasReachedContractCap(Client $client): bool
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
