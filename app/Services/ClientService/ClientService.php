<?php

namespace App\Services\ClientService;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\ReceiptRepository;
use App\Models\Payment;
use App\Services\Finance\GeneralAccounting\GeneralAccountingService;
use App\Services\InvoiceService;
use App\Services\IvaInformationService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClientService
{
    protected $model;
    public function __construct($model)
    {
        $this->model = $model;
    }

    public function getNumberAccountComunUser()
    {
        $number = ComunConstantsController::NUMBER_ACCOUNT_COMUN_USERS; // '0100000000'
        $id = $this->model->id; // ID del cliente, por ejemplo: 1437

        // Asegurar que el ID sea un número y no exceda la longitud esperada
        $formattedId = str_pad($id, strlen($number) - 2, '0', STR_PAD_LEFT);

        // Reemplazar los últimos dígitos del número base con el ID del cliente
        $number = substr_replace($number, $formattedId, -strlen($formattedId));

        return $number;
    }

    public function getPaymentPeriod($amount)
    {
        $clientRepository = new ClientRepository();
        $this->model->load('balance');
        $balanceAmount = $this->model->balance->amount;
        if ($balanceAmount >= 0) {
            $amount += $balanceAmount;
            return $this->handlePositiveAmount($this->model, $amount, $clientRepository);
        }
        return $this->handleNonPositiveAmount($this->model, $clientRepository, $amount);
    }


    /**
     * Maneja el caso cuando el monto es positivo.
     */
    private function handlePositiveAmount($client, $amount, $clientRepository)
    {
        $cuantasVecesSePuedeCobrar = $clientRepository->getCuantasVecesSeLePuedenCobrarLosServiciosActivosSegunAmount($client, $amount);
        $cuantasVecesSePuedeCobrar = $cuantasVecesSePuedeCobrar > 0 ? $cuantasVecesSePuedeCobrar : 1;
        $fechaInicio = Carbon::parse($client->fecha_pago);
        return $this->getPeriodoDePagoByDate($fechaInicio, $cuantasVecesSePuedeCobrar);
    }

    /**
     * Maneja el caso cuando el monto no es positivo.
     */
    private function handleNonPositiveAmount($client, $clientRepository, $amount)
    {
        $cuantasVecesSePuedeCobrar = $clientRepository->getCuantasVecesSeLePuedenCobrarLosServiciosActivosSegunAmount($client, $amount);
        $cuantasVecesSePuedeCobrar = $cuantasVecesSePuedeCobrar > 0 ? $cuantasVecesSePuedeCobrar : 1;
        $fechaInicio = Carbon::parse($client->fecha_pago)->subMonths($cuantasVecesSePuedeCobrar);
        return $this->getPeriodoDePagoByDate($fechaInicio, $cuantasVecesSePuedeCobrar);
    }

    public function getPeriodoDePagoByDate($fechaInicio, $cuantasVecesSePuedeCobrar)
    {
        $fechaPago = Carbon::parse($fechaInicio);
        $montNameFechaPago = $fechaPago->monthName;
        $nextMonth =  $fechaPago->addMonthWithoutOverflow($cuantasVecesSePuedeCobrar)->monthName;
        return $montNameFechaPago . ' - ' . $nextMonth;
    }


    public function getDataServicesForClient()
    {
        $clientWithServices = (new ClientRepository())->getServicesForClient($this->model->id);
        $client_services = [];

        $i = 0;
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                $informationIva = $this->getIvaInformation($clientService->getTax(), $clientService->price_service);
                $iva = $informationIva['iva'];
                $client_services[] = [
                    'service_id' => $clientService->id,
                    'relation' => $service,
                    'number' => $i + 1,
                    'service_name' => $clientService->service_name,
                    'iva_porcent' => $clientService->getTax(),
                    'iva' => $clientService->serviceHasIva() ? $iva : 0,
                    'monto' => $clientService->serviceHasIva() ? $informationIva['monto'] : $clientService->price_service,
                ];
            }
        }

        return $client_services;
    }


    public function getIvaInformation($tasa, $total)
    {
        return (new IvaInformationService($tasa, $total))->getIvaInformation();
    }

    public function paidActivationCost()
    {
        Model::withoutEvents(function () {
            $newPayment = new Payment();
            $newPayment->number = (new ReceiptRepository())->getReceipt();
            $newPayment->payment_method_id = 1;
            $newPayment->date = Carbon::now()->toDateTimeString();
            $newPayment->amount = $this->model->client_main_information->activation_cost ?? 0;
            $newPayment->payment_period = 0;
            $newPayment->comment = 'Pago de Costo de Activación';
            $newPayment->receipt = 0;
            $newPayment->send_receipt_after_payment = 0;
            $newPayment->add_by = auth()->user()->id ?? 0;
            $newPayment->paymentable_id = $this->model->id;
            $newPayment->paymentable_type = 'App\Models\Client';
            $newPayment->enabled_payment_promise = 0;
            $newPayment->save();
            $invoiceService = new InvoiceService();
            $invoiceService->addInvoiceCostActivation($this->model, $newPayment->id);

            $transactionService = new TransactionService();
            $transaction = $transactionService->addTransactionCostActivation($this->model, $newPayment->id);
            $this->model->client_main_information->is_payment_activation_cost = true;
            $this->model->client_main_information->save();

            $generalAccountingService = new GeneralAccountingService();
            $generalAccountingService->setNewGeneralAccountingIncomeBeforeActivationCostPayment($newPayment, $this->model, $transaction);
        });
    }


    public function getDataPendingPayments()
    {
        /* Activacion de Servicio
            si lo pago o no
            numero de meses de su contrato
            adeudo = costo de sus sevicios * cantidad de meses del contrato - (pagos realizados tipo servicio)
        */
        $clientRepository = new ClientRepository();
        $activationCost = $this->model->client_main_information->activation_cost ?? 0;
        $isPaymentActivationCost = $this->model->client_main_information->is_payment_activation_cost;
        $contractMonths = $this->model->client_main_information->contract_months ?? 0;
        $contractMonthsDuration = $this->model->client_main_information->duration_contract ? $this->model->client_main_information->duration_contract->duration : 0;
        $costAllServices = $clientRepository->getCostAllService($this->model->id);

        // Validar que el costo de los servicios no sea 0
        if ($costAllServices == 0) {
            // Si el costo de los servicios es 0, no hay pagos realizados y el adeudo es 0
            $numeroDePagos = 0;
            $mesesRestantes = $contractMonthsDuration;
            $adeudoMeses = 0;
        } else {
            $total_payments = DB::table('transactions')
                ->where('client_id', $this->model->id)
                ->where('is_payment', true)
                ->where('category', 'Pago')
                ->sum('credit');

            // Calcular el número de pagos realizados
            $numeroDePagos = floor($total_payments / $costAllServices);

            $mesesRestantes = $contractMonthsDuration - $numeroDePagos;

            $adeudoMeses = $mesesRestantes * $costAllServices;

            if ($mesesRestantes < 0) {
                $mesesRestantes = 0;
                $adeudoMeses = 0;
            }
        }

        $data = [
            'activationCost' => $activationCost,
            'isPaymentActivationCost' => $isPaymentActivationCost,
            'contractMonths' => $contractMonths,
            'contractMonthsDuration' => $contractMonthsDuration,
            'costAllServices' => $costAllServices,
            'totalPayments' => $total_payments ?? 0, // Si no hay pagos, se asigna 0
            'numeroDePagos' => $numeroDePagos,
            'mesesRestantes' => $mesesRestantes,
            'adeudoMeses' => $adeudoMeses
        ];

        return $data;
    }


    public function getCostAllServices()
    {
        $clientRepository = new ClientRepository();
        return $clientRepository->getCostAllService($this->model->id);
    }
}
