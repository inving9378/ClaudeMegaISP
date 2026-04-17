<?php

namespace App\Services\ClientService;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Controllers\Utils\ReceiptController;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientPaymentPromiseRepository;
use App\Http\Repository\ClientRepository;
use App\Jobs\Client\ClientServiceChargedJob;
use App\Models\Client;
use App\Models\Payment;
use App\MyLibrary\Utility;
use App\Services\ClientMainInformationService;
use App\Services\DeployService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PromisePaymentClientService
{
    protected $client;
    protected $input;

    public function __construct(Client $client = null, $input)
    {
        $this->client = $client;
        $this->input = $input;
    }


    public function billingOrCreatePromisePayment()
    {
        // Eliminar periodo de gracia
        $clientPaymentPromiseRepository = new ClientPaymentPromiseRepository();
        if ($this->client->active_promise_payment) {
            $this->billingAndUpdatePromisePayment();
        } else {
            $newPromisePayment = [
                'client_id' => $this->client->id,
                'first_court_date' => $this->input['first_court_date'],
                'first_amount' =>  $this->input['first_amount'],
                'second_court_date' => $this->input['second_court_date'],
                'second_amount' =>  $this->input['second_amount'],
                'third_court_date' => $this->input['third_court_date'],
                'third_amount' =>  $this->input['third_amount'],
            ];

            $this->client->update([
                'active_promise_payment' => true
            ]);

            $clientPaymentPromiseRepository->create($newPromisePayment);
            //actualiza fecha de Pago
            $clientRepository = new ClientRepository();
            $clientRepository->setFechaPago($this->client, $this->input['first_court_date']);
            $this->deployServiceAndActiveClient();

            $newBalance = $this->rectifyBalance(true);
            $payment = $this->clientCreatePayment();
            $this->addCreditTransaction($payment, $newBalance);

            return $payment;
        }
    }

    public function billingAndUpdatePromisePayment()
    {
        $clientPaymentPromiseRepository = new ClientPaymentPromiseRepository();
        $promiseAndCourtDateAndAmountPaymentNotPaid = $clientPaymentPromiseRepository->getPromiseAndCourtDateAndAmountPaymentNotPaid($this->client->id);
        if ($this->elPagoCubrioElMontoDeLaPromesa($promiseAndCourtDateAndAmountPaymentNotPaid['amount'])) {
            $promiseAndCourtDateAndAmountPaymentNotPaid['promise']->update([
                $promiseAndCourtDateAndAmountPaymentNotPaid['field'] => true
            ]);
            //actualiza nueva fecha de Pago y de Corte
            $clientRepository = new ClientRepository();
            $clientRepository->setFechaPago($this->client, $promiseAndCourtDateAndAmountPaymentNotPaid['nextDate']);

            $billingExpirationService = new BillingExpirationService($this->client);
            $billingExpirationService->setNewFechaCorteForClient($promiseAndCourtDateAndAmountPaymentNotPaid['nextDate']);
            if ($this->client->client_main_information->estado != ComunConstantsController::STATE_ACTIVE) {
                $this->deployServiceAndActiveClient();
            }

            //Rectifica Balance
            $newBalance = $this->rectifyBalance();
            $payment = $this->clientCreatePayment();
            $this->addTransaction($payment, $newBalance);

            //si es la ultima promesa de Pago elimina promesas de pago para ese cliente

            if ($this->esLaUltimaPromesaDePagoYelSaldoEsPositivo($promiseAndCourtDateAndAmountPaymentNotPaid['field'])) {
                $promiseAndCourtDateAndAmountPaymentNotPaid['promise']->delete();
                $this->client->update([
                    'active_promise_payment' => false
                ]);
                activity()->log('El Cliente #' . $this->client->id . ' ha cumplido sus promesas de Pago');

                //calcula la nueva fecha de pago y de corte
                $billingExpirationService = new BillingExpirationService($this->client);
                $billingExpirationService->setNewFechaCorteForClient($promiseAndCourtDateAndAmountPaymentNotPaid['nextDate']);
                if ($this->client->client_main_information->estado != ComunConstantsController::STATE_ACTIVE) {
                    $this->deployServiceAndActiveClient();
                }
            } else {
                $clientMainInformationService = new ClientMainInformationService($this->client->id);
                $clientMainInformationService->setStateBlocked();
                $this->unDeployServices();
            }
        } else {
            $newBalance = $this->rectifyBalance(true);
            $payment = $this->clientCreatePayment();
            $this->addCreditTransaction($payment, $newBalance);

            if (!$this->esLaUltimaPromesaDePagoYelSaldoEsPositivo($promiseAndCourtDateAndAmountPaymentNotPaid['field'])) {
                $clientMainInformationService = new ClientMainInformationService($this->client->id);
                $clientMainInformationService->setStateBlocked();
                $this->unDeployServices();
            }
        }
    }

    public function elPagoCubrioElMontoDeLaPromesa($amountPromise)
    {
        return $this->input['amount'] >= $amountPromise;
    }

    public function esLaUltimaPromesaDePagoYelSaldoEsPositivo($field)
    {
        $this->client->load('balance');
        return $field == 'third_amount_is_pay' && $this->client->balance->amount >= 0;
    }



    public function deployServiceAndActiveClient()
    {
        //despliega los servicios y activo el cliente
        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getServicesForClient($this->client->id);
        //Servicios Activa y despliega servicios y ponlo en pa
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                $deployService = new DeployService($clientService);
                $deployService->deployService();
            }
        }
    }

    public function unDeployServices()
    {
        //despliega los servicios y activo el cliente
        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getServicesForClient($this->client->id);
        //Servicios Activa y despliega servicios y ponlo en pa
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                $clientService->update([
                    'deploy' => false,
                    'charged' => false
                ]);
            }
        }
    }

    public function rectifyBalance($firstPayment = false)
    {
        $balance = $this->client->balance;
        $amount = $balance->amount;
        if ($amount < 0 || $firstPayment) {
            $saldoFinal = $amount + $this->input['amount'];
        } else {
            $saldoFinal = $amount - $this->input['amount'];
        }
        $balance->update([
            'amount' => $saldoFinal
        ]);
        activity()->log('Saldo actualizado para el cliente ' . $this->client->id . ' tiene el nuevo balance ' . $saldoFinal);
        return $saldoFinal;
    }

    public function clientCreatePayment()
    {
        $input = $this->input;
        $input['number'] = $this->setPaymentNumber();
        $input['date'] = $this->input['date_payment'] ?? Carbon::now()->toDateTimeString();
        $this->client->receipt()->create(['receipt' => $input['receipt']]);
        $input = Utility::modifyValueForCheckbox($input, 'ClientPayment');
        $input['add_by'] = Auth::user()->id ?? '1';
        $input['receipt'] = ReceiptController::getStaticReceiptForClient();

        $newPayment = new Payment;
        $newPayment->number = $input['number'];
        $newPayment->payment_method_id = $input['payment_method_id'];
        $newPayment->date = $input['date'];
        $newPayment->amount = $input['amount'];
        $newPayment->payment_period = $input['payment_period'];
        $newPayment->comment = isset($input['comment']) ? $input['comment'] : "";
        $newPayment->receipt = $input['receipt'];
        $newPayment->send_receipt_after_payment = $input['send_receipt_after_payment'];
        $newPayment->add_by = $input['add_by'];
        $newPayment->paymentable_id = $this->client->id;
        $newPayment->paymentable_type = 'App\Models\Client';
        $newPayment->enabled_payment_promise = $input['enabled_payment_promise'];
        $newPayment->first_court_date = isset($input['first_court_date']) ? $input['first_court_date'] : null;
        $newPayment->first_amount = isset($input['first_amount']) ? $input['first_amount'] : null;
        $newPayment->second_court_date = isset($input['second_court_date']) ? $input['second_court_date'] : null;
        $newPayment->second_amount = isset($input['second_amount']) ? $input['second_amount'] : null;
        $newPayment->third_court_date = isset($input['third_court_date']) ? $input['third_court_date'] : null;
        $newPayment->third_amount = isset($input['third_amount']) ? $input['third_amount'] : null;;
        $newPayment->save();

        return $newPayment;
    }


    public function setPaymentNumber()
    {
        $count = Payment::count();
        if ($count) {
            return Carbon::now()->format('ym') . '000' . $count + 1;
        }
        return Carbon::now()->format('ym') . '000' . '1';
    }


    public function addTransaction($payment, $amountBalance,)
    {
        $this->client->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'debit' => $payment->amount,
            'account_balance' => $amountBalance,
            'description' => $payment->getPaymentMethod(),
            'category' => 'Promesa de Pago',
            'cantidad' => '1',
            'client_id' => $this->client->id,
            'type' => 'debit',
            'price' => $payment->amount,
            'iva' => 0,
            'total' => $payment->amount,
            'from_date' => null,
            'to_date' => null,
            'comment' => $payment->comment,
            'period' => $payment->payment_period,
            'add_to_invoice' => true,
            'company_balance' => $amountBalance,
            'movement' => '+ ' . $payment->amount,
            'service_name' => null,
            'invoice' => null,
            'transactionable_id' => $this->client->id,
            'transactionable_type' => 'App\Models\Client',
            'is_payment' => true,
            'payment_id' => $payment->id,
        ]);
    }


    public function addCreditTransaction($payment, $amountBalance,)
    {
        $this->client->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'credit' => $payment->amount,
            'account_balance' => $amountBalance,
            'description' => $payment->getPaymentMethod(),
            'category' => 'Promesa de Pago',
            'cantidad' => '1',
            'client_id' => $this->client->id,
            'type' => 'credit',
            'price' => $payment->amount,
            'iva' => 0,
            'total' => $payment->amount,
            'from_date' => null,
            'to_date' => null,
            'comment' => $payment->comment,
            'period' => $payment->payment_period,
            'add_to_invoice' => true,
            'company_balance' => $amountBalance,
            'movement' => '+ ' . $payment->amount,
            'service_name' => null,
            'invoice' => null,
            'transactionable_id' => $this->client->id,
            'transactionable_type' => 'App\Models\Client',
            'is_payment' => true,
            'payment_id' => $payment->id,
        ]);
    }
}
