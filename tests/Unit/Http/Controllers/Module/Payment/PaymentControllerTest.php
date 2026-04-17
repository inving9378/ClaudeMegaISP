<?php

namespace Tests\Unit\Http\Controllers\Module\Payment;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Traits\RouterConnection;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Tests\Unit\Helpers\PaymentTestHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Unit\Helpers\ClientTestHelper;
use Tests\Unit\Helpers\PackageClientCreateTestHelper;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase, ClientTestHelper, PackageClientCreateTestHelper, RouterConnection, PaymentTestHelper;

    /**
     * Test storing a new Client.
     *
     * @return void
     */
    public function testPaymentClientRecurrent()
    {
        //TODO Por Hacer cuando se termine sin pruebas en mokrotik
        /* $this->simulateUserAutenticaded();
        $client = $this->createClientRecurrent();

        $clientId = $client->id;
        /////////////
        //Crearle Un Paquete
        $this->createPackageByClient($clientId);
        //CREAR PAGO Que cubre el costo de los servicios
        $requestPayment = ClientRequest::paymentClient1CubreElCostoDeLosServicios();
        $helperMock = Mockery::mock(ClientPaymentDatatableHelper::class);
        $paymentController = new ClientPaymentController($helperMock, new ClientRepository());
        $payment = $paymentController->store($requestPayment, $clientId);
        $this->assertInstanceOf(Payment::class, $payment);
        //Verificar que se creo un registro en payments
        $this->assertEquals($clientId, $payment->paymentable_id, 'No se creo un registro en payments');
        //balance
        $balance = Balance::where('balanceable_id', $clientId)->first();
        $this->assertEquals($clientId, $balance->balanceable_id, 'No se creo un registro en balance');
        $amount = $balance->amount;
        $this->assertEquals(0, $amount, 'El monto no es correcto');

        //Debe haber sacado el servicio del address list
        $clientInternetService = ClientInternetService::where('client_id', $clientId)->first();
        $networkIpService = new NetworkIpService($clientInternetService);
        $ip = $networkIpService->getClientIp();
        $mikrotikService = new MikrotikService();
        $router = $clientInternetService->router;
        $connection = $mikrotikService->getConnection($router);
        $idByIp = $this->getIdByIp($connection, ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH, $ip);
        $this->assertNull($idByIp, 'No se elimino el servicio del firewal_address_lis en el mikrotik');

        //verificar las fechas de Pago y de Corte Como este cliente es Nuevo las fecha de pago ideal debe ser
        // el proximo mes a partir fecha actual el dia de billing_date
        $client->refresh();
        $billingConfiguration = BillingConfiguration::where('client_id', $clientId)->first();
        $billingDate = $billingConfiguration->billing_date;
        $billingExpiration = $billingConfiguration->billing_expiration;
        $now = Carbon::now();
        $fechaPago = $now->addMonthsWithoutOverflow(1)->day($billingDate)->endOfDay();
        $fechaCorte = $fechaPago->copy()->addDays($billingExpiration)->endOfDay();
        $fechaPago = $fechaPago->toDateTimeString();
        $fechaCorte = $fechaCorte->toDateTimeString();
        $this->assertEquals($fechaPago, $client->fecha_pago, 'La fecha de pago no es correcta');
        $this->assertEquals($fechaCorte, $client->fecha_corte, 'La fecha de pago no es correcta');

        //Una vez Terminado el Test se debe eliminar el servicio del mikrotik
        $idByIpSecret = $this->getIdByIpSecrets($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $ip);
        $this->removeById($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $idByIpSecret);
        $idByIpSecret = $this->getIdByIpSecrets($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $ip);
        $this->assertNull($idByIpSecret, 'No se elimino el servicio del ppp secret en el mikrotik'); */
    }


    public function testPagoClienteRecurrenteActivoConPeriodoDeGraciaSaldoNegativoFechaCorteMayorAHoy()
    {
        //TODO Por Hacer cuando se termine sin pruebas en mokrotik
        /*  $this->simulateUserAutenticaded();
        $client = $this->createClientRecurrent();
        $client = $this->initT(
            $client,
            "2025-01-03 23:59:59",
            "2025-02-02 23:59:59",
            "-520",
            ComunConstantsController::STATE_ACTIVE,
            true
        );

        $this->checkT();

        $clientId = $client->id;
        /////////////
        //Crearle Un Paquete
        $this->createPackageByClient($clientId);
        //CREAR PAGO Que cubre el costo de los servicios
        $requestPayment = ClientRequest::paymentClient1CubreElCostoDeLosServicios();
        $helperMock = Mockery::mock(ClientPaymentDatatableHelper::class);
        $paymentController = new ClientPaymentController($helperMock, new ClientRepository());
        $payment = $paymentController->store($requestPayment, $clientId);
        $this->assertInstanceOf(Payment::class, $payment);
        //Verificar que se creo un registro en payments
        $this->assertEquals($clientId, $payment->paymentable_id, 'No se creo un registro en payments');
        //balance
        $balance = Balance::where('balanceable_id', $clientId)->first();
        $this->assertEquals($clientId, $balance->balanceable_id, 'No se creo un registro en balance');
        $amount = $balance->amount;
        $this->assertEquals(0, $amount, 'El monto no es correcto');

        //Debe haber sacado el servicio del address list
        $clientInternetService = ClientInternetService::where('client_id', $clientId)->first();
        $networkIpService = new NetworkIpService($clientInternetService);
        $ip = $networkIpService->getClientIp();
        $mikrotikService = new MikrotikService();
        $router = $clientInternetService->router;
        $connection = $mikrotikService->getConnection($router);
        $idByIp = $this->getIdByIp($connection, ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH, $ip);
        $this->assertNull($idByIp, 'No se elimino el servicio del firewal_address_lis en el mikrotik');

        //verificar las fechas de Pago y de Corte Como este cliente es Nuevo las fecha de pago ideal debe ser
        // el proximo mes a partir fecha actual el dia de billing_date
        $this->checkResultT(
            "2025-02-02 23:59:59",
            "2025-02-03 23:59:59"
        );
        //Una vez Terminado el Test se debe eliminar el servicio del mikrotik
        $idByIpSecret = $this->getIdByIpSecrets($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $ip);
        $this->removeById($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $idByIpSecret);
        $idByIpSecret = $this->getIdByIpSecrets($connection, ComunConstantsController::PPP_SECRET_WHIT_SLASH, $ip);
        $this->assertNull($idByIpSecret, 'No se elimino el servicio del ppp secret en el mikrotik'); */
    }

    public function testPagoClienteRecurrenteNoConexionMikrotik()
    {
        $this->simulateUserAutenticaded();
        $arrayRecurrentPayments = $this->getArrayClientRecurrentOfPayments();
        $arrayCustomPayments = $this->getArrayClientCustomOfPayments();
        $this->executeTest('Recurrent', $arrayRecurrentPayments);
        $this->executeTest('Custom', $arrayCustomPayments);
    }

    private function executeTest($type, $arrayPayments)
    {
        foreach ($arrayPayments as $key => $payment) {
            //Cambiar fecha de creacion del cliente con Carbon::setTestNow si es necesario
            Carbon::setTestNow($payment['fecha_creacion_cliente']);
            if ($type == 'Recurrent') {
                $client = $this->createClientRecurrent();
                dump("Cliente Recurrente $key procesando...");
            }
            if ($type == 'Custom') {
                $client = $this->createClientCustom();
                dump("Cliente Custom $key procesando...");
            }

            $this->initT(
                $client,
                $payment['fecha_corte'],
                $payment['fecha_pago'],
                $payment['fecha_ultima_transaccion'],
                $payment['balance'],
                $payment['estado'],
                $payment['periodo_gracia'],
                $payment['billing_expiration'],
                $payment['amount']
            );

            $this->checkT();

            //Cambiar fecha de pago con Carbon::setTestNow
            Carbon::setTestNow($payment['fecha_realizacion_pago']);

            $this->createPaymentLogic();

            $this->checkResultT(
                $payment['fecha_pago_esperada'],
                $payment['fecha_corte_esperada'],
                $payment['balance_esperado']
            );
            Carbon::setTestNow();
        }
    }

    private function getArrayClientRecurrentOfPayments()
    {
        return [
            //Cliente Recurrente Activo pago que cubre el monto de todos los servicios
            [
                'fecha_creacion_cliente' => '2025-08-31 10:00:00',
                'billing_expiration' => 1,

                'amount' => 520,
                'balance' => '0',
                'balance_esperado' => 0,

                'fecha_pago' => '2025-09-30 23:59:59',
                'fecha_corte' => '2025-10-01 23:59:59',
                'fecha_ultima_transaccion' => '2024-09-01 23:59:59',

                'fecha_realizacion_pago' => '2025-01-03 10:05:00',

                'fecha_pago_esperada' => '2025-02-03 23:59:59',
                'fecha_corte_esperada' => '2025-02-04 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => false,
            ],
            //Cliente Recurrente Activo Pago que no cubra el costo de los servicios.
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 200,
                'balance' => '0',
                'balance_esperado' => 200,

                'fecha_pago' => '2025-01-03 23:59:59',
                'fecha_corte' => '2025-01-04 23:59:59',
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-03 10:05:00',

                'fecha_pago_esperada' => '2025-01-03 23:59:59',
                'fecha_corte_esperada' => '2025-01-04 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => false,
            ],
            //Cliente Recurrente Activo (Periodo de gracia): pago cubre el monto de los servicios
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 3,

                'amount' => 522,
                'balance' => '-520',
                'balance_esperado' => 2,

                'fecha_pago' => '2025-01-26 23:59:59',
                'fecha_corte' => '2024-12-29 23:59:59',
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',

                'fecha_realizacion_pago' => '2024-12-30 10:00:00',


                'fecha_pago_esperada' => '2025-01-26 23:59:59',
                'fecha_corte_esperada' => '2025-01-29 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => true,
            ],
            //Cliente Recurrente Activo (Periodo de gracia): pago NO cubre el monto de los servicios
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 3,

                'amount' => 300,
                'balance' => '-520',
                'balance_esperado' => '-220',

                'fecha_pago' => '2025-01-26 23:59:59',
                'fecha_corte' => '2024-12-29 23:59:59',
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',

                'fecha_realizacion_pago' => '2024-12-30 10:00:00',


                'fecha_pago_esperada' => '2025-01-26 23:59:59',
                'fecha_corte_esperada' => '2024-12-29 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => true,
            ],
            //Cliente Recurrente Bloqueado:Pago que cubra el costo de los servicios.
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 3,

                'amount' => 520,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-01-26 23:59:59',
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-10 10:00:00',


                'fecha_pago_esperada' => '2025-01-26 23:59:59',
                'fecha_corte_esperada' => '2025-01-29 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => true,
            ],
            //Cliente Recurrente Bloqueado:Pago que NO cubra el costo de los servicios.
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 3,

                'amount' => 300,
                'balance' => '-520',
                'balance_esperado' => '-220',

                'fecha_pago' => '2025-01-26 23:59:59',
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-10 10:00:00',


                'fecha_pago_esperada' => '2025-01-26 23:59:59',
                'fecha_corte_esperada' => null,

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => true,
            ],
            //PRUEBAS PEDIDAS POR YASMANIS
            //Cliente fecha de pago 26 , fecha corte 2dias despues prueba 1: pago el dia 27 1vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 2,

                'amount' => 520,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => '2025-01-28 23:59:59',
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-27 10:00:00',


                'fecha_pago_esperada' => '2025-02-26 23:59:59',
                'fecha_corte_esperada' => '2025-02-28 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => true,
            ],
            //Cliente fecha de pago 26 , fecha corte 2dias despues prueba 1: pago el dia 27 2vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 2,

                'amount' => 1040,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => '2025-01-28 23:59:59',
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-27 10:00:00',


                'fecha_pago_esperada' => '2025-03-26 23:59:59',
                'fecha_corte_esperada' => '2025-03-28 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => true,
            ],
            //Cliente fecha de pago 26 , fecha corte 2dias despues prueba 1: pago el dia 29 1vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 2,

                'amount' => 520,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-29 10:00:00',


                'fecha_pago_esperada' => '2025-02-26 23:59:59',
                'fecha_corte_esperada' => '2025-02-28 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => true,
            ],

            //Cliente fecha de pago 26 , fecha corte 2dias despues prueba 1: pago el dia 29 2vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 2,

                'amount' => 1040,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-29 10:00:00',


                'fecha_pago_esperada' => '2025-03-26 23:59:59',
                'fecha_corte_esperada' => '2025-03-28 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => true,
            ],
            //Cliente fecha de pago 26 , fecha corte 2dias despues prueba 1: pago el dia 5 del siguiente mes 1vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 2,

                'amount' => 520,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-02-05 10:00:00',

                'fecha_pago_esperada' => '2025-02-26 23:59:59',
                'fecha_corte_esperada' => '2025-02-28 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => true,
            ],

            //Cliente fecha de pago 26 , fecha corte 2dias despues prueba 1: pago el dia 5 del siguiente mes 2vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 2,

                'amount' => 1040,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-02-05 10:00:00',

                'fecha_pago_esperada' => '2025-03-26 23:59:59',
                'fecha_corte_esperada' => '2025-03-28 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => true,
            ],
            //Cliente fecha de pago 26 , fecha corte 10dias despues prueba 1: pago el dia 28 1vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 10,

                'amount' => 520,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => '2025-02-06 23:59:59',
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-28 10:00:00',

                'fecha_pago_esperada' => '2025-02-26 23:59:59',
                'fecha_corte_esperada' => '2025-03-08 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => true,
            ],
            //Cliente fecha de pago 26 , fecha corte 10dias despues prueba 1: pago el dia 28 2vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 10,

                'amount' => 1040,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => '2025-02-06 23:59:59',
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-01-28 10:00:00',

                'fecha_pago_esperada' => '2025-03-26 23:59:59',
                'fecha_corte_esperada' => '2025-04-05 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => true,
            ],
            //Cliente fecha de pago 26 , fecha corte 10dias despues prueba 1: pago el dia 7 1vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 10,

                'amount' => 520,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-02-07 10:00:00',

                'fecha_pago_esperada' => '2025-02-26 23:59:59',
                'fecha_corte_esperada' => '2025-03-08 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => true,
            ],
            //Cliente fecha de pago 26 , fecha corte 10dias despues prueba 1: pago el dia 7 2vez
            [
                'fecha_creacion_cliente' => '2024-11-26 10:00:00',
                'billing_expiration' => 10,

                'amount' => 1040,
                'balance' => '-520',
                'balance_esperado' => '0',

                'fecha_pago' => '2025-02-26 23:59:59',
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2025-01-26 23:59:59',

                'fecha_realizacion_pago' => '2025-02-07 10:00:00',

                'fecha_pago_esperada' => '2025-03-26 23:59:59',
                'fecha_corte_esperada' => '2025-04-05 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => true,
            ],
        ];
    }


    private function getArrayClientCustomOfPayments()
    {
        return [
            //Cliente Custom Activo pago que cubre el monto de todos los servicios
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 520,
                'balance' => '0',
                'balance_esperado' => 0,

                'fecha_pago' => '2025-01-03 23:59:59',
                'fecha_corte' => '2025-01-04 23:59:59',
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',
                'fecha_realizacion_pago' => '2025-01-03 10:05:00',

                'fecha_pago_esperada' => '2025-02-03 23:59:59',
                'fecha_corte_esperada' => '2025-02-04 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => false,
            ],
            //Cliente Custom Activo pago que NO cubre el monto de todos los servicios
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 200,
                'balance' => '0',
                'balance_esperado' => 200,

                'fecha_pago' => '2025-01-03 23:59:59',
                'fecha_corte' => '2025-01-04 23:59:59',
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',
                'fecha_realizacion_pago' => '2025-01-03 10:05:00',

                'fecha_pago_esperada' => '2025-01-03 23:59:59',
                'fecha_corte_esperada' => '2025-01-04 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => false,
            ],

            //Cliente Custom Bloqueado pago que cubre el monto de todos los servicios
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 520,
                'balance' => '0',
                'balance_esperado' => 0,

                'fecha_pago' => null,
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',
                'fecha_realizacion_pago' => '2025-01-03 10:05:00',

                'fecha_pago_esperada' => '2025-02-02 23:59:59',
                'fecha_corte_esperada' => '2025-02-03 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => false,
            ],
            //Cliente Custom Bloqueado pago que NO cubre el monto de todos los servicios
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 200,
                'balance' => '0',
                'balance_esperado' => 200,

                'fecha_pago' => null,
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',
                'fecha_realizacion_pago' => '2025-01-03 10:05:00',

                'fecha_pago_esperada' => null,
                'fecha_corte_esperada' => null,

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => false,
            ],

            //Cliente Custom Activo pago que cubre el monto de todos los servicios, pago 1 veces
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 520,
                'balance' => '0',
                'balance_esperado' => 0,

                'fecha_pago' => '2025-01-25 23:59:59',
                'fecha_corte' => '2025-01-26 23:59:59',
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',
                'fecha_realizacion_pago' => '2025-01-07 10:05:00',

                'fecha_pago_esperada' => '2025-02-25 23:59:59',
                'fecha_corte_esperada' => '2025-02-26 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => false,
            ],

            //Cliente Custom Activo pago que cubre el monto de todos los servicios, pago 2 veces
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 1040,
                'balance' => '0',
                'balance_esperado' => 0,

                'fecha_pago' => '2025-01-25 23:59:59',
                'fecha_corte' => '2025-01-26 23:59:59',
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',
                'fecha_realizacion_pago' => '2025-01-07 10:05:00',

                'fecha_pago_esperada' => '2025-03-25 23:59:59',
                'fecha_corte_esperada' => '2025-03-26 23:59:59',

                'estado' => ComunConstantsController::STATE_ACTIVE,
                'periodo_gracia' => false,
            ],

            //Cliente Custom Bloqueado pago que cubre el monto de todos los servicios, pago 1 veces
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 520,
                'balance' => '0',
                'balance_esperado' => 0,

                'fecha_pago' => null,
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',
                'fecha_realizacion_pago' => '2025-01-07 10:05:00',

                'fecha_pago_esperada' => '2025-02-06 23:59:59',
                'fecha_corte_esperada' => '2025-02-07 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => false,
            ],

            //Cliente Custom Activo pago que cubre el monto de todos los servicios, pago 2 veces
            [
                'fecha_creacion_cliente' => '2025-01-03 10:00:00',
                'billing_expiration' => 1,

                'amount' => 1040,
                'balance' => '0',
                'balance_esperado' => 0,

                'fecha_pago' => null,
                'fecha_corte' => null,
                'fecha_ultima_transaccion' => '2024-12-26 23:59:59',
                'fecha_realizacion_pago' => '2025-01-07 10:05:00',

                'fecha_pago_esperada' => '2025-03-06 23:59:59',
                'fecha_corte_esperada' => '2025-03-07 23:59:59',

                'estado' => ComunConstantsController::STATE_BLOCKED,
                'periodo_gracia' => false,
            ],
        ];
    }
}
