<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Traits\RouterConnection;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Models\Balance;
use App\Models\ClientInvoice;
use App\Models\MethodOfPayment;
use App\Services\LogService;
use Carbon\Carbon;
use App\Models\TypeBilling;
use App\Models\ClientBundleService;
use App\Models\Client;
use App\Services\ClientService\BillingExpirationService;
use App\Services\FormatDateService;
use App\Services\IvaInformationService;
use App\Services\PromotionService;
use Spatie\Activitylog\Models\Activity;

class ClientRepository
{
    use RouterConnection;

    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Client::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function setClient($id)
    {
        $this->model->where('id', $id);
    }

    public function getClientById($id)
    {
        return $this->model->find($id);
    }

    public function getClientsHaveToPayToday()
    {
        return $this->model->tocaPagarHoyOYaPasoLaFechaDeCorte();
    }

    public function getClientsWithPromisePayment()
    {
        return $this->model->havePromisePayment()->get();
    }

    public function getTypeOfBillingByClientId($clientId)
    {
        $client = $this->model->where('id', $clientId)->with('billing_configuration')->first();
        if ($client) {
            return $client->billing_configuration->type_billing_id;
        }
        return null;
    }



    public function getClientsWithInternetServiceNotActive()
    {
        return $this->model->with('internet_service')
            ->whereHas('internet_service')
            ->whereHas('client_main_information', function ($query) {
                $query->notActive();
            })->whereHas('internet_service', function ($query) {
                $query->whereHas('service_in_address_list');
            })->get();
    }

    public function getClientsWithGracePeriodAndBalance()
    {
        return $this->model->with('client_grace_period', 'balance')->whereHas('client_grace_period')
            ->get();
    }


    public function getClientFilteredByPaymentableId($paymentableId)
    {
        return $this->model->findOrFail($paymentableId);
    }

    public function getBalanceForClient($id)
    {
        $balance = Balance::where('balanceable_id', $id)->first();
        return $balance->amount;
    }

    public function hasService($clientId, $service)
    {
        return $this->model->where('id', $clientId)->whereHas($service)->first();
    }

    public function getClientServicesFilteredByServiceId($client_id)
    {
        return $this->model->where('id', $client_id)->haveServices()->first();
    }

    public function canAddService($clientId, $service)
    {
        $client = Client::with('client_main_information')->find($clientId);

        if ($this->isRecurrente($client->client_main_information->type_of_billing_id)) {
            $clientBundleService = ClientBundleService::with('bundle.billings')
                ->where('client_id', $clientId)
                ->whereHas('bundle.billings', function ($query) {
                    $query->where('type', 'Pagos Recurrentes');
                })
                ->get();

            if ($service == 'bundle') {
                return true;
            }

            if ($clientBundleService->count() >= 1) {
                return true;
            }
        }

        if ($this->isPrepaid($client->client_main_information->type_of_billing_id)) {

            if ($service == 'bundle') {
                return false;
            }

            if (($service == 'internet') || ($service == 'voz') || ($service == 'custom')) {
                return true;
            }
        }
        return false;
    }

    public function getCostAllServiceActive($clientId)
    {
        $client = $this->getClientWithServiceActive($clientId);
        $priceClientBundleService = $client->bundle_service->sum('price_service');
        $priceClientInternetService =  $client->internet_service->sum('price_service');
        $priceClientVozService = $client->voz_service->sum('price_service');
        $priceClientCustomService = $client->custom_service->sum('price_service');
        return $priceClientInternetService + $priceClientVozService + $priceClientCustomService + $priceClientBundleService;
    }

    public function getCostAllService($clientId)
    {
        $client = $this->getServicesForClient($clientId);
        if (is_null($client)) {
            return 0;
        }
        $priceClientBundleService = $client->bundle_service->sum('price_service');
        $priceClientInternetService =  $client->internet_service->sum('price_service');
        $priceClientVozService = $client->voz_service->sum('price_service');
        $priceClientCustomService = $client->custom_service->sum('price_service');

        $price = $priceClientInternetService + $priceClientVozService + $priceClientCustomService + $priceClientBundleService;
        return $price;
    }

    public function getCostAllServiceSlope($clientId)
    {
        $client = $this->getServicePending($clientId);
        $priceClientBundleService = $client->bundle_service->sum('price_service');
        $priceClientInternetService =  $client->internet_service->sum('price_service');
        $priceClientVozService = $client->voz_service->sum('price_service');
        $priceClientCustomService = $client->custom_service->sum('price_service');
        return $priceClientInternetService + $priceClientVozService + $priceClientCustomService + $priceClientBundleService;
    }

    // TODO revisar y cambiar por el nuevo campo de fecha_corte en cliente
    public function getActiveServiceExpiration($clientId)
    {
        $client = $this->model->where('id', $clientId)->first();
        if ($client) {
            return (new FormatDateService($client->fecha_corte))->formatDateWithTime();
        }
        return false;
    }

    public function addDebitTransactionForPaymentService($planRelation, $clientService, $newBalanceAndPrice, $invoise = null, $transaction = null)
    {
        $paymentId = null;
        if ($transaction) {
            $paymentId = $transaction->payment_id;
        }
        return $clientService->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'debit' => $newBalanceAndPrice['cost'],
            'account_balance' => $newBalanceAndPrice['new_balance'],
            'description' => $clientService->description,
            'category' => 'Servicio',
            'cantidad' => '1',
            'client_id' => $clientService->client->id,
            'type' => 'debit',
            'price' => $newBalanceAndPrice['price'],
            'iva' => $clientService->$planRelation->tax_include ? 16 : 0, //TODO Ajuste esto aqui porque esta mal la logica ahora mismo al crear el Bundle
            'total' => $newBalanceAndPrice['new_balance'],
            'from_date' => $this->getStartDate(),
            'to_date' => $this->getEndDate($clientService),
            'comment' => null,
            'add_to_invoice' => false,
            'company_balance' => $newBalanceAndPrice['new_balance'],
            'movement' => '+ ' . $newBalanceAndPrice['cost'],
            'service_name' => $clientService->description,
            'invoice' => $invoise->id ?? '',
            'is_payment' => false,
            'payment_id' => $paymentId,
        ]);
    }

    public function addDebitTransactionAllServices($client, $debit, $balanceActual, $invoise = null)
    {
        return $client->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'debit' => $debit,
            'account_balance' => $balanceActual,
            'description' => 'Cobro de Servicio',
            'category' => 'Servicio',
            'cantidad' => '1',
            'client_id' => $client->id,
            'type' => 'debit',
            'price' => $debit,
            'iva' => 0,
            'total' => $debit,
            'from_date' => null,
            'to_date' => null,
            'comment' => null,
            'add_to_invoice' => false,
            'company_balance' => $balanceActual,
            'movement' => '+ ' . $debit,
            'service_name' => 'All',
            'invoice' => $invoise->id ?? null,
            'is_payment' => false,
            'payment_id' => null,
        ]);
    }

    public function addDebitTransactionForPaymentDefaulter($client, $defaulterCost, $newBalance, $invoise = null)
    {
        return $client->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'debit' => $defaulterCost,
            'account_balance' => $newBalance['new_balance'],
            'description' => 'Automatico',
            'category' => 'Servicio',
            'cantidad' => '1',
            'client_id' => $client->id,
            'type' => 'debit',
            'price' => $defaulterCost,
            'iva' => 0,
            'total' => $newBalance['new_balance'],
            'from_date' => null,
            'to_date' => null,
            'comment' => null,
            'add_to_invoice' => false,
            'company_balance' => $newBalance['new_balance'],
            'movement' => '+ ' . $defaulterCost,
            'service_name' => 'Automatico',
            'invoice' => $invoise->id,
            'is_payment' => false,
            'payment_id' => null,
        ]);
    }

    public function addDebitTransactionForPaymentAgreement($client, $defaulterCost, $newBalance, $invoise = null)
    {
        return $client->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'debit' => $defaulterCost,
            'account_balance' => $newBalance['new_balance'],
            'description' => 'Rectificación de debito por Acuerdo de Pago',
            'category' => 'Servicio',
            'cantidad' => '1',
            'client_id' => $client->id,
            'type' => 'debit',
            'price' => $defaulterCost,
            'iva' => 0,
            'total' => 0,
            'from_date' => null,
            'to_date' => null,
            'comment' => null,
            'add_to_invoice' => false,
            'company_balance' => $newBalance['new_balance'],
            'movement' => '+ ' . $defaulterCost,
            'service_name' => 'Acuerdo de Pago',
            'invoice' => $invoise->id,
            'is_payment' => false,
            'payment_id' => null,
        ]);
    }

    public function getClientWithServiceActive($client_id)
    {
        return Client::with([
            'bundle_service' => function ($query) {
                $query->activo();
            },
            'internet_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->activo();
            },
            'voz_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->activo();
            },
            'custom_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->activo()
                    ->typeOfPaymentIsNotUnique();
            }
        ])
            ->where('id', $client_id)
            ->first();
    }

    public function getServiceNotCharged($client_id)
    {
        return Client::with([
            'bundle_service' => function ($query) {
                $query->noEstaCobrado();
            },
            'internet_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->noEstaCobrado();
            },
            'voz_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->noEstaCobrado();
            },
            'custom_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->noEstaCobrado();
            }
        ])
            ->where('id', $client_id)
            ->first();
    }

    public function getServicePending($client_id)
    {
        return Client::with([
            'bundle_service' => function ($query) {
                $query->pending();
            },
            'internet_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->pending();
            },
            'voz_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->pending();
            },
            'custom_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->pending();
            }
        ])
            ->where('id', $client_id)
            ->first();
    }


    public function getServicesForClient($client_id)
    {
        return Client::with([
            'bundle_service',
            'internet_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete();
            },
            'voz_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete();
            },
            'custom_service' => function ($query) {
                $query->servicioNoPerteneceAUnPaquete()
                    ->typeOfPaymentIsNotUnique();
            }
        ])
            ->where('id', $client_id)
            ->first();
    }

    public function addInvoiceService($client_id, $isPayment)
    {
        $clientWithServices = $this->getClientWithServiceActive($client_id);

        // TODO revisar esta parte no me convence como se utiliza el $serviceExpiration porque deberia estarse haciendo un invoice al servicio en especifico.
        $serviceExpiration = \Carbon\Carbon::parse($this->getActiveServiceExpiration($client_id))->format('Y-m-d');

        $clientInvoice = $clientWithServices->client_invoices()->create([
            'number' => $this->setInvoiceNumber(),
            'total' => $this->getCostAllServiceActive($client_id),
            'estado' => 'Pagar (del saldo de la cuenta)',
            'last_update' => Carbon::now()->toDateString(),
            'pay_up' => $serviceExpiration ?? null,
            'use_of_transactions' => 1,
            'payment' => $isPayment,
            'is_sent' => false,
            'delete_transactions' => false,
            'added_by' => '0',
            'type' => ClientInvoice::TYPE_INVOICE_SERVICES,
            'payment_date' => Carbon::now()->toDateString()
        ]);

        $services = [
            'bundle_service' => $clientWithServices['bundle_service'],
            'internet_service' => $clientWithServices['internet_service'],
            'voz_service' => $clientWithServices['voz_service'],
            'custom_service' => $clientWithServices['custom_service'],
        ];

        foreach ($services as $service) {
            if ($service->count()) {
                foreach ($service as $value) {
                    $value->client_serviceables()->attach(['client_invoice_id' => $clientInvoice->id]);
                }
            }
        }
        return $clientInvoice;
    }

    public function updateInvoiceService($clientInvoice, $isPayment)
    {
        return $clientInvoice->update([
            'payment' => $isPayment,
        ]);
    }

    public function addInvoiceDefaulter($client, $isPayment, $amount)
    {
        return $client->client_invoices()
            ->create([
                'number' => $this->setInvoiceNumber(),
                'total' => $amount,
                'estado' => $isPayment ? 'Pagado (Recargo Alquiler de dispositivo)' : 'Pagar (Recargo Alquiler de dispositivo)',
                'last_update' => Carbon::now()->toDateString(),
                'pay_up' => Carbon::now()->toDateString(),
                'use_of_transactions' => 1,
                'payment' => $isPayment,
                'is_sent' => false,
                'delete_transactions' => false,
                'added_by' => '0',
                'type' => ClientInvoice::TYPE_INVOICE_SURCHARGE_DEFAULTER,
                'payment_date' => Carbon::now()->toDateString()
            ]);
    }

    public function addInvoiceAgreement($client, $amount)
    {
        return $client->client_invoices()
            ->create([
                'number' => $this->setInvoiceNumber(),
                'total' => $amount,
                'estado' => 'Pagado',
                'last_update' => Carbon::now()->toDateString(),
                'pay_up' => Carbon::now()->toDateString(),
                'use_of_transactions' => 1,
                'payment' => true,
                'is_sent' => false,
                'delete_transactions' => false,
                'added_by' => '0',
                'type' => ClientInvoice::TYPE_INVOICE_AGREEMENT,
                'payment_date' => Carbon::now()->toDateString()
            ]);
    }

    public function updateInvoiceToCancel($invoicesWithoutPay, $inviceNumber)
    {

        foreach ($invoicesWithoutPay as $invoiceWithoutPay) {
            $invoiceWithoutPay->update([
                'estado' => 'Cancelada',
                'last_update' => Carbon::now()->toDateString(),
                'use_of_transactions' => 0,
                'payment' => false,
                'added_by' => '0',
                'note' => $inviceNumber,
            ]);
        }
    }

    public function setInvoiceNumber()
    {
        $count = ClientInvoice::count();
        if ($count) {
            $id = ClientInvoice::latest('id')->first()->id;
            return Carbon::now()->format('ym') . '000' . $id + 1;
        }
        return Carbon::now()->format('ym') . '000' . '1';
    }


    public function rectifyBalance($clientService, $newBalanceAndPrice)
    {
        $client = $this->model->find($clientService->client->id);
        $client->load('balance');
        $client->balance->update(['amount' => $newBalanceAndPrice['new_balance']]);

        $log = new LogService();
        $log->log($client, 'Cliente #' . $client->id . ' tiene el nuevo balance ' . $newBalanceAndPrice['new_balance']);
    }

    public function getStartDate()
    {
        return Carbon::now()->startOfDay()->format('Y-m-d\TH:i');
    }

    public function getTypeOfBilling($client)
    {
        if ($client && !$client->client_main_information) {
            $client->load('client_main_information');
        }
        return (int)$client->client_main_information->type_of_billing_id;
    }

    public function getEndDate($clientService)
    {
        return $clientService->client->fecha_corte;
    }


    public function isRecurrente($type_of_billing_id)
    {
        return $type_of_billing_id == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT;
    }

    public function isPrepaid($type_of_billing_id)
    {
        return $type_of_billing_id == TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM
            || $type_of_billing_id == TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY;
    }

    public function getBillingInformationBlock($clientId)
    {

        $client = Client::with('client_main_information', 'balance', 'billing_configuration')->where('id', $clientId)->first();

        $costForMonth = $this->getCostAllService($clientId);
        $slopeCostForMonth = $this->getCostAllServiceSlope($clientId);
        $expirationDate = (new FormatDateService($client->fecha_corte))->formatDate();
        $fechaPago = (new FormatDateService($client->fecha_pago))->formatDate();
        $balance = $client->balance->amount;

        $comandConfigRepository = new CommandConfigRepository();
        $horaPlanificada = $comandConfigRepository->getHourlyToSuspendService();
        $horaPlanificada = $expirationDate ? Carbon::parse($expirationDate)->addDay()->format('d-m-Y')  . ' ' . $horaPlanificada : '-';

        $daysLeft = $expirationDate ? Carbon::parse($expirationDate)->addDay()->diffInDays(Carbon::now()) : 0;
        $costPerDaysService = number_format($this->getCostAllServiceActive($clientId) / Carbon::now()->daysInMonth, 6);
        $daysLeft != 0 ? $costPerDaysServiceLeft = number_format($costPerDaysService * $daysLeft, 2) : $costPerDaysServiceLeft = 0;

        $lastHistoryExpirationDate = $expirationDate ? $expirationDate : $this->obtenerUltimaFechaCorteDelClienteByActivityLog($client);

        $ultimoPago = $this->obtenerLaFechaDelUltimoPago($clientId);
        $montoDelUltimoPago = $this->obtenerMontoDelUltimoPago($clientId);
        $informationInstalationCost = $this->getInformationInstalationCost($client->id);
        $hasServicesWithUnpaidInstallationCosts = $informationInstalationCost['has_services_with_unpaid_installation_costs'];
        $amountInstalationCost = $informationInstalationCost['amount_instalation_cost'];
        $hasServicesWithInstallationCost = $informationInstalationCost['has_services_with_installation_cost'];

        $informationActivationCost = $this->getInformationActivationCost($client);
        $hasUnpaidActivationCost = $informationActivationCost['has_activation_cost_unpaid'];
        $amountActivationCost = $informationActivationCost['amount_activation_cost'];

        $promotions = $this->getPromotions($clientId);


        $array = [
            'cost_for_month' => !$costForMonth ? $slopeCostForMonth : $costForMonth,
            'cost_per_days_service' => $costPerDaysServiceLeft,
            'expiration_date' => $expirationDate,
            'expired' => $this->expirateAccount($client->fecha_corte),
            'balance' => $balance,
            'hora_planificada' => $horaPlanificada,
            'fecha_pago' => $fechaPago,
            'lastHistoryExpirationDate' => $lastHistoryExpirationDate,
            'ultimo_pago' => $ultimoPago,
            'amount_last_payment' => $montoDelUltimoPago,
            'has_services_with_unpaid_installation_costs' => $hasServicesWithUnpaidInstallationCosts,
            'amount_instalation_cost' => $amountInstalationCost,
            'has_services_with_installation_cost' => $hasServicesWithInstallationCost,
            'promotions' => $promotions,
            'has_unpaid_activation_cost' => $hasUnpaidActivationCost,
            'amount_activation_cost' => $amountActivationCost
        ];

        return $array;
    }


    public function getPromotions($clientId)
    {
        $promotionService = new PromotionService();
        $clientWithService = $this->getServicesForClient($clientId);
        $promotions = $promotionService->getServicesHasPromotionByClient($clientWithService);
        if (empty($promotions)) {
            $promotions = null;
        }
        return $promotions;
    }


    public function getInformationInstalationCost($clientId)
    {
        $has_services_with_unpaid_installation_costs = $this->hasServicesWithUnpaidInstallationCosts($clientId);
        $has_services_with_installation_cost = $this->hasServicesWithInstallationCost($clientId);
        $amount_instalation_cost = $this->getPriceInstalationCost($clientId);
        $array = [
            'has_services_with_unpaid_installation_costs' => $has_services_with_unpaid_installation_costs,
            'amount_instalation_cost' => $amount_instalation_cost,
            'has_services_with_installation_cost' => $has_services_with_installation_cost
        ];
        return $array;
    }

    public function getInformationActivationCost($client)
    {
        $has_activation_cost_unpaid = $client->client_main_information->is_payment_activation_cost;
        $amount_activation_cost = $client->client_main_information->activation_cost;
        $array = [
            'has_activation_cost_unpaid' => $has_activation_cost_unpaid == false,
            'amount_activation_cost' => $amount_activation_cost
        ];
        return $array;
    }
    public function getPriceInstalationCost($clientId)
    {
        $priceInstalationCost = 0;
        $clientWithService = $this->getServicesForClient($clientId);
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithService->$service as $clientService) {
                if ($clientService->instalation_cost_paid) continue;
                $priceInstalationCost += $clientService->instalation_cost;
            }
        }

        return $priceInstalationCost;
    }

    public function hasServicesWithInstallationCost($clientId)
    {
        $has_services_with_installation_cost = false;
        $clientWithService = $this->getServicesForClient($clientId);
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithService->$service as $clientService) {
                if ($clientService->has_active_instalation_cost) {
                    return true;
                }
            }
        }
        return $has_services_with_installation_cost;
    }


    public function hasServicesWithUnpaidInstallationCosts($clientId)
    {
        $has_services_with_unpaid_installation_costs = false;
        $clientWithService = $this->getServicesForClient($clientId);
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithService->$service as $clientService) {
                if ($clientService->has_active_instalation_cost && !$clientService->instalation_cost_paid) {
                    $has_services_with_unpaid_installation_costs = true;
                }
            }
        }
        return $has_services_with_unpaid_installation_costs;
    }

    public function obtenerLaFechaDelUltimoPago($clientId)
    {
        $paymentRepository = new PaymentRepository();
        $date = $paymentRepository->obtenerLaFechaDelUltimoPagoByClientId($clientId);
        $formatedDate = (new FormatDateService($date))->formatDate();

        return $formatedDate;
    }

    public function obtenerMontoDelUltimoPago($clientId)
    {
        $paymentRepository = new PaymentRepository();
        $amount = $paymentRepository->obtenerMontoDelUltimoPagoByClientId($clientId);
        return $amount;
    }


    public function obtenerLaFechaDelUltimoCobroDeLosServicios($client)
    {
        return (new TransactionRepository())->lastDebitTransactionByClientId($client->id);
    }

    public function expirateAccount($date)
    {
        if ($date == null) {
            return true;
        }
        return Carbon::now() > $date;
    }

    public function getClientDebitRectificationAgreement($request, $clientId)
    {
        $input = $request->all();
        $paymentMethodId = $input['payment_method_id'];
        $porcent = '0.' . $input['apply_group_of_months'];

        $client = Client::with('balance')->find($clientId);
        $ClientBalance = $client->balance->amount * -1;
        $newAmount = $ClientBalance - ($ClientBalance * $porcent);

        $invoiceWithoutPay = ClientInvoice::where('client_id', $clientId)
            ->where('payment', 0)
            ->where('estado', '!=', 'Cancelada')
            ->get();

        if ($invoiceWithoutPay) {
            $newBalance['new_balance'] = 0;
            $initDate = \Carbon\Carbon::parse($invoiceWithoutPay->first()->created_at);
            $initMonth = $initDate->format("F");

            $valuesToPayment = [
                'amount' => $newAmount,
                'payment_method_id' => $paymentMethodId,
                'payment_period' => $initMonth . ' - ' . Carbon::now()->format("F"),
                'comment' => 'Acuerdo de Pago'
            ];

            $newInvoiceAgreement = $this->addInvoiceAgreement($client, $newAmount);
            $this->addDebitTransactionForPaymentAgreement($client, $newAmount, $newBalance, $newInvoiceAgreement);
            $client->clientCreatePaymentAgreement($valuesToPayment);
            $this->updateInvoiceToCancel($invoiceWithoutPay, $newInvoiceAgreement->number);
            $client->balance()->update(['amount' => '0']);
        }
    }

    public function isClientBalanceSufficientRemoveClientFromAddressList($client)
    {
        if ($this->isClientBalanceSufficient($client)) {
            $internet_services = $client->internet_service()->get();
            if ($internet_services) {
                foreach ($internet_services as $clientService) {
                    MikrotikRemoveClientServiceFromAddressList::dispatchAfterResponse($clientService);
                    $logService = new LogService();
                    $logService->log($client, 'Su servicio ' . $clientService->service_name . ' fue removido de address_list desde el ClientRepository::isClientBalanceSufficientRemoveClientFromAddressList');
                }
            }
        }
    }
    public function isClientBalanceSufficient($client)
    {
        return $client->balance->amount > $this->getCostAllService($client->id);
    }

    public function getCuantasVecesSeLePuedenCobrarLosServiciosActivos($client)
    {
        $costAllServices = $this->getCostAllService($client->id);
        $client->load('balance');
        if ($costAllServices > 0 && $client->balance->amount >= $costAllServices) {
            return intval(floor($client->balance->amount / $costAllServices));
        }
        return null;
    }

    public function getCuantasVecesSeLePuedenCobrarLosServiciosActivosSegunAmount($client, $amount)
    {
        $costAllServices = $this->getCostAllService($client->id);
        if ($costAllServices > 0 && $amount >= $costAllServices) {
            return intval(floor($amount / $costAllServices));
        }
        return null;
    }

    public function getPaymentMethod($payment_method_id)
    {
        return MethodOfPayment::find($payment_method_id);
    }

    public function promisePaymentByClient($client_id)
    {
        return $this->model->promisePayment()->where('id', $client_id)->first();
    }

    public function setFechaCorte(?Client $client, ?string $getBillingExpirationByTypeOfBilling)
    {
        activity()->tap(function (Activity $activity) use ($client) {
            $activity->client_id = $client->id;
        })->log('Cliente #' . $client->id . ' Actualizada Fecha de corte anterior: ' . $client->fecha_corte . ' Actual : ' . $getBillingExpirationByTypeOfBilling);
        $client->update(['fecha_corte' => $getBillingExpirationByTypeOfBilling]);
    }

    public function setFechaPago(?Client $client, $fechaPago)
    {
        $client->update(['fecha_pago' => $fechaPago]);
    }

    public function getClientsToSuspendServices(mixed $date = null)
    {
        return $this->model->tocaSuspender($date)->get();
    }

    public function removeFechaCorteById($client_id)
    {
        $client = $this->model->where('id', $client_id)->first();
        if ($client) {
            activity()->log('Eliminada Fecha de corte del Cliente: ' . $client_id . ' Actualizada Fecha de corte anterior: ' . $client->fecha_corte);
            return $client->update(['fecha_corte' => null]);
        }
        return  activity()->log('Se Ha tratado de Eliminar la fecha de Cortes del Cliente: ' . $client_id . ' no existe un cliente con ese id');
    }

    public function getClientsToBillingServices(mixed $date = null)
    {
        return $this->model->tocaCobrar($date)->get();
    }

    public function addPeriodoGracia(mixed $client)
    {
        $daysToGracePeriod = $client->billing_configuration->grace_period;
        $client->fecha_fin_periodo_gracia = Carbon::now()->addDays($daysToGracePeriod);
        $client->save();

        $clientGracePeriodRepository = new ClientGracePeriodRepository();
        $input = [
            'client_id' => $client->id
        ];
        $clientGracePeriodRepository->create($input);
        activity()->tap(function (Activity $activity) use ($client) {
            $activity->client_id = $client->id;
        })->log('Cliente #' . $client->id . ' le agrego el periodo de gracia');
    }

    public function removePeriodoGracia(mixed $client, $setNewFechaCorte = false, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling = 1)
    {
        // TODO siempre que eliminas el periodo d gracia se debe recalcular la fecha de corte porque significa que el cliente pago.
        if ($setNewFechaCorte) {
            $service = new BillingExpirationService($client);
            $service->setNewFechaCorteForClient(null, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling, false, true);
        }

        if ($client->fecha_fin_periodo_gracia) {
            $client->update(['fecha_fin_periodo_gracia' => null, 'active_promise_payment' => false]);
        }
        $client->client_grace_period()->delete();
    }


    /* INFORMATION */
    public function getClientsNotActiveWhereDoesntHaveServiceInAddressList()
    {
        return $this->model->with('internet_service')
            ->whereHas('internet_service')
            ->whereHas('client_main_information', function ($query) {
                $query->notActive();
            })->whereHas('internet_service', function ($query) {
                $query->whereDoesntHave('service_in_address_list');
            })->get();
    }

    public function getClientsActiveWhereHasServiceInAddressList()
    {
        return $this->model->with('internet_service')
            ->whereHas('internet_service.service_in_address_list')
            ->active()
            ->get();
    }

    /* END INFORMATION */
    private function obtenerUltimaFechaCorteDelClienteByActivityLog($client)
    {
        //TODO Cuando Se ARREGLEN Bien La Fecha en que fue Suspendido el cliente Habilitar esto
        /*  if ($client->fecha_suspension) {
            $formatedDate = new FormatDateService($client->fecha_suspension);
            return $formatedDate->formatDate();
        } */
        $activities = $client->activities()->orderBy('id', 'desc')->get();

        foreach ($activities as $activity) {
            $data = json_decode($activity->properties, true);
            if (isset($data['attributes']['fecha_corte'])) {
                return (new FormatDateService($data['attributes']['fecha_corte']))->formatDate();
            } elseif (isset($data['old']['fecha_corte'])) {
                return (new FormatDateService($data['old']['fecha_corte']))->formatDate();
            }
        }
    }

    public function calculateAmounts(Client $client): array
    {
        $clientWithServices = $this->getServicesForClient($client->id);
        $client_services = [];
        $i = 0;
        $subTotal = 0;
        $ivaSum = 0;
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;

        foreach ($services as $service) {
            // Verificar si el cliente tiene este tipo de servicio
            if (isset($clientWithServices->$service)) {
                foreach ($clientWithServices->$service as $clientService) {
                    $informationIva = $this->getIvaInformation($clientService->getTax(), $clientService->price_service);
                    $iva = $informationIva['iva'];

                    $client_services[] = [
                        'number' => $i + 1,
                        'service_name' => $clientService->service_name,
                        'iva_porcent' => $clientService->getTax() ?? 0,
                        'iva' => $clientService->serviceHasIva() ? $iva : 0,
                        'monto' => $clientService->serviceHasIva() ? $informationIva['monto'] : $clientService->price_service,
                        'service_id' => $clientService->id,
                        'service_class' => get_class($clientService)
                    ];

                    $ivaSum += $clientService->serviceHasIva() ? $iva : 0;
                    $subTotal += $clientService->serviceHasIva() ? $informationIva['monto'] : $clientService->price_service;
                    $i++;
                }
            }
        }
        $total = $subTotal + $ivaSum;
        return [
            'subtotal' => round($subTotal, 2),
            'tax' => round($ivaSum, 2),
            'total' => round($total, 2),
            'services' => $client_services
        ];
    }

    public function getIvaInformation($tasa, $total)
    {
        return (new IvaInformationService($tasa, $total))->getIvaInformation();
    }
}
