<?php

namespace Tests\Feature;

use Tests\TestCase;

// Asegúrate de usar esta clase base

class ClientTest extends TestCase
{
    // TODO borrar
//    public function test_example()
//    {
//        $this->assertTrue(true);
//    }
//
//    public function test_database_connection()
//    {
//        // Intentar obtener el nombre de la base de datos
//        $databaseName = DB::connection()->getDatabaseName();
//
//        // Verificar que la conexión no sea nula
//        $this->assertNotNull($databaseName, 'No se pudo conectar a la base de datos.');
//
//        // Verificar que la base de datos sea la esperada
//        $this->assertEquals('meganet', $databaseName, 'La base de datos no es la esperada.');
//
//        // Intentar ejecutar una consulta simple para verificar que la conexión funcione
//        $result = $this->getClientTest();
//        $this->assertNotEmpty($result, 'La conexión a la base de datos no pudo ejecutar una consulta.');
//    }
//
//    public function test_activate_client()
//    {
//        $client = $this->getClientTest();
//        $clientMainInformationRepository =  new ClientMainInformationRepository();
//        $clientMainInformationRepository->setClientMainInformationByClientId($client->id);
//        $clientMainInformationRepository->setStateActive();
//        //colocar resultado esperado
//        //1 Se activa el cliente
//        $client->refresh();
//        $this->assertEquals(ComunConstantsController::STATE_ACTIVE, $client->client_main_information->estado, 'El estado debe ser ' . ComunConstantsController::STATE_ACTIVE);
//
//
//        //3 se activan los servicios
//        //4 se envia correo a cliente que su cuenta ha sido activada
//    }
//
//    public function test_blocked_client()
//    {
//        $client = $this->getClientTest();
//        $clientMainInformationRepository =  new ClientMainInformationRepository();
//        $clientMainInformationRepository->setClientMainInformationByClientId($client->id);
//        $clientMainInformationRepository->setStateBlocked();
//        //colocar resultado esperado
//        //1 el cliente se Bloquea
//        $client->refresh();
//        $this->assertEquals(ComunConstantsController::STATE_BLOCKED, $client->client_main_information->estado, 'El estado debe ser ' . ComunConstantsController::STATE_BLOCKED);
//
//    }
//
//
//    public function test_make_payment_updates_dates_and_activates_services_in_client_recurrent_active_where_amount_payment_is_equal_to_cost()
//    {
//
//        $client = $this->getClientTest();
//        $this->assertTrue(true);
//        return;
//        $clientRepository = new ClientRepository();
//        $costAllServices = $clientRepository->getCostAllService($client->id);
//        $receiptRepository = new ReceiptRepository();
//        $receipt = $receiptRepository->getReceipt();
//        //mes actual - mes siguiente
//        $payment_period = Carbon::now()->format('Y-m-d') . ' - ' . Carbon::now()->addMonth()->format('Y-m-d');
//
//        $arrayPago = [
//            "payment_method_id" => 1,
//            "amount" => $costAllServices,
//            "receipt" => $receipt,
//            "payment_period" => $payment_period,
//            "date_payment" => Carbon::now()->toDateString(),
//            "send_receipt_after_payment" => true,
//        ];
//
//        $objetoRequestDePago = new \Illuminate\Http\Request($arrayPago);
//        $this->assertTrue(true);
//    }
//
//
//
//    public function test_make_payment_fails_if_amount_is_insufficient()
//    {
//        $client = $this->getClientTest();
//        // Simular un pago insuficiente
//        $serviceCost = 300;
//        $paymentAmount = 200; // Pago menor al costo
//        $result = $client->makePaymentTest($paymentAmount, $serviceCost);
//
//        // Verificar que el pago falló
//        $this->assertFalse($result);
//
//        // Verificar que las fechas no se actualicen
//        $this->assertEquals(
//            Carbon::today()->toDateString(),
//            $client->fecha_pago
//        );
//        $this->assertEquals(
//            Carbon::today()->addDays(5)->toDateString(),
//            $client->fecha_corte
//        );
//
//        // Verificar que el balance no cambió
//        $this->assertEquals(0, $client->balance);
//    }
//
//
//    public function test_suspend_services()
//    {
//        $client = $this->getClientTest();
//        $suspendService = new SuspendService();
//        $suspendService->suspendServiceByClient($client);
//
//        //colocar resultado esperado
//
//
//
//    }
//
//    public function test_billing_services()
//    {
//        $client = $this->getClientTest();
//        $clientBillingService = new ClientBillingService();
//        $clientBillingService->billingServicesByClient($client, ClientBillingService::TYPE_BILLING_EXECUTED_PROCESS);
//        //colocar resultado esperado
//
//    }
//
//    public function test_inactive_client()
//    {
//        $client = $this->getClientTest();
//        $clientMainInformationRepository =  new ClientMainInformationRepository();
//        $clientMainInformationRepository->setClientMainInformationByClientId($client->id);
//        $clientMainInformationRepository->setStateInactive();
//        //colocar resultado esperado
//        //1 el cliente se inactiva
//        //2 se le borra la fecha de corte y de pago si es prepago personalizado
//        //3 se suspenden los servicios //verificar que se agregue al address_list de mikrotik
//        //4 en el futuro se envia correo a cliente que su cuenta ha sido inactivada./Falta implementar
//    }
//
//
//
//    public function crearClienteFicticio()
//    {
//        // Crear un cliente con datos iniciales
//        $client = Client::factory()->create([
//            'fecha_pago' => Carbon::today()->toDateString(),
//            'fecha_corte' => Carbon::today()->addDays(5)->toDateString(),
//        ]);
//        //clientMainInformation
//        $clientMainInformation = ClientMainInformation::factory()->create([]);
//        //clientAditionalInformation
//        $clientAdditionalInformation = ClientAdditionalInformation::factory()->create([]);
//
//        //balance
//        $balance = Balance::factory()->create([]);
//
//        //billingConfiguration
//        $billingConfiguration = BillingConfiguration::factory()->create([]);
//    }
//
//
//
//
//
//    public function getClientTest()
//    {
//        $client = Client::find(1437);
//        if ($client) {
//            return $client;
//        }
//        dd('fallo no esiste el cliente');
//    }
}
