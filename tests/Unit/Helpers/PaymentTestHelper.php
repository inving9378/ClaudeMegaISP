<?php

namespace Tests\Unit\Helpers;

use App\Http\Controllers\Module\Client\ClientPaymentController;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Controllers\Utils\TypeOfBillingController;
use App\Http\HelpersModule\module\client\ClientPaymentDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Models\Balance;
use App\Models\Helper\Client as ClientRequest;
use App\Models\Payment;
use Carbon\Carbon;


const COSTO_TODOS_SERVICIOS = 520;

trait PaymentTestHelper
{
    private $fechaCorte;
    private $fechaPago;
    private $fechaUltimaTransaccion;
    private $balance;
    private $estado;
    private $periodoDeGracia;
    private $client;
    private $billingExpiration;
    private $amount;

    protected function initT($client, $fechaCorte, $fechaPago, $fechaUltimaTransaccion, $balance, $estado, $periodoDeGracia, $billingExpiration, $amount)
    {
        $this->fechaCorte = $fechaCorte;
        $this->fechaPago = $fechaPago;
        $this->fechaUltimaTransaccion = $fechaUltimaTransaccion;
        $this->balance = $balance;
        $this->amount = $amount;
        $this->estado = $estado;
        $this->periodoDeGracia = $periodoDeGracia;
        $this->billingExpiration = $billingExpiration;
        $this->client = $client;
        $this->addConditionsT();

        return $this->client;
    }

    protected function checkT()
    {
        $this->assertEquals($this->fechaCorte, $this->client->fecha_corte);
        $this->assertEquals($this->fechaPago, $this->client->fecha_pago);
        $this->assertEquals($this->balance, $this->client->balance->amount);
        $this->assertEquals($this->estado, $this->client->client_main_information->estado);
        if ($this->periodoDeGracia) {
            $this->assertNotNull($this->client->fecha_fin_periodo_gracia);
        }
        if ((new ClientRepository())->isRecurrente($this->client->client_main_information->type_of_billing_id)) {
            $this->assertEquals(Carbon::now()->day, $this->client->billing_configuration->billing_date);
        }
    }

    protected function createPaymentLogic()
    {
        $clientId = $this->client->id;
        /////////////
        //Crearle Un Paquete
        $service = $this->createPackageByClientNotConectionMikrotik($clientId);
        //Crea debit transaction segun el amount actual del cliente y la fecha de pago.
        if ($this->fechaUltimaTransaccion) {
            $carbonBeforeSetCarbonForLastTransaction = Carbon::now();
            Carbon::setTestNow($this->fechaUltimaTransaccion);
            $this->addTransactionTypeService($this->client, $service);
            Carbon::setTestNow($carbonBeforeSetCarbonForLastTransaction);
        }
        //CREAR PAGO
        $requestPayment = ClientRequest::paymentClient1($this->amount);
        $helperMock = \Mockery::mock(ClientPaymentDatatableHelper::class);
        $paymentController = new ClientPaymentController($helperMock, new ClientRepository());
        $payment = $paymentController->store($requestPayment, $clientId);
        $this->assertInstanceOf(Payment::class, $payment);
        //Verificar que se creo un registro en payments
        $this->assertEquals($clientId, $payment->paymentable_id, 'No se creo un registro en payments');
        $this->client->refresh();
    }

    protected function checkResultT($fechaPago, $fechaCorte, $balanceEsperado)
    {
        //balance
        $balance = Balance::where('balanceable_id', $this->client->id)->first();
        $this->assertEquals($this->client->id, $balance->balanceable_id, 'No se creo un registro en balance');
        $balanceActual = $balance->amount;
        $this->assertEquals($balanceEsperado, $balanceActual, 'El monto no es correcto');
        $this->assertLessThan(COSTO_TODOS_SERVICIOS, $balanceActual, 'El monto no es correcto');
        $this->assertEquals($fechaPago, $this->client->fecha_pago, 'La fecha de pago no es correcta');
        $this->assertEquals($fechaCorte, $this->client->fecha_corte, 'La fecha de corte no es correcta');
    }

    protected function addConditionsT()
    {
        // Relaciones
        $billingConfiguration = $this->client->billing_configuration;
        $clientBalance = $this->client->balance; // Renombrado para evitar conflicto
        $clientMainInformation = $this->client->client_main_information;

        // Fecha de Corte
        $this->client->fecha_corte = $this->fechaCorte;

        // Fecha de Pago
        $this->client->fecha_pago = $this->fechaPago;
        $billingConfiguration->billing_date = Carbon::parse($this->fechaPago)->day;


        $billingConfiguration->billing_expiration = (int)$this->billingExpiration;


        // Periodo de Gracia
        if ($this->periodoDeGracia) {
            $this->client->fecha_fin_periodo_gracia = Carbon::parse($this->fechaCorte)->addDays($billingConfiguration->grace_period)->toDateTimeString();
        }

        // Actualizar el Balance
        $clientBalance->amount = $this->balance;
        $clientBalance->save();

        // Actualizar el Estado
        $clientMainInformation->estado = $this->estado;
        $clientMainInformation->save();

        // Guardar cambios en el cliente
        $billingConfiguration->save();
        $this->client->save();

        return $this->client->refresh();
    }
}
