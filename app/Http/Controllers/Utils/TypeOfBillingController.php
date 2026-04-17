<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Models\ClientCustomService;
use App\Models\TypeBilling;
use App\Models\ClientInternetService;
use App\Models\ClientVozService;
use Carbon\Carbon;
use Illuminate\Database\Console\DumpCommand;
use Illuminate\Support\Facades\Log;

const PROCESS = [
    '1' => 'recurrent',
    '2' => 'daily',
    '3' => 'custom'
];

class TypeOfBillingController extends Controller
{
    protected $clientService;

    public function __construct($clientService)
    {
        $this->clientService = $clientService;
    }

    public function getNewBalanceAndPriceByTypeOfBilling()
    {
        if (isset(PROCESS[$this->clientService->client->client_main_information->type_of_billing_id])) {
            $function = PROCESS[$this->clientService->client->client_main_information->type_of_billing_id];
            return $this->$function();
        }
        return null;
    }

    public function daily()
    {
        $clientService = $this->clientService;
        $balance = $clientService->client->balance;

        $price = $clientService->price_service;

        if (!$this->clientService->serviceHasIva()) {
            $price = $this->clientService->getNewPriceByIva();
        }
        if ($this->ifServiceHasDiscount()) {
            $price = $this->getNewPriceByDiscount();
        }

        if ($this->clientServiceIsCustomYEsPagoUnico()) {
            $cost = $price;
        } else {
            $cost = round($price / Carbon::now()->daysInMonth, 2);
        }

        if ($this->ifHaveBalanceToPayService($clientService->client->balance->amount, $cost)) {
            return [
                'new_balance' => $balance->amount - $cost,
                'price' => $clientService->price_service,
                'cost' => $cost,
                'payment_in_time' => true
            ];
        }

        return null;
    }

    public function custom()
    {
        $clientService = $this->clientService;
        $balance = $clientService->client->balance;

        $price = $clientService->price_service;

        if (!$this->clientService->serviceHasIva()) {
            $price = $this->clientService->getNewPriceByIva();
        }

        if ($this->ifServiceHasDiscount()) {
            $price = $this->getNewPriceByDiscount();
        }

        $cost = $price;
        if ($this->ifHaveBalanceToPayService($balance->amount, $cost)) {
            return [
                'new_balance' => $balance->amount - $price,
                'price' => $clientService->price_service,
                'cost' => $cost,
                'payment_in_time' => true
            ];
        } else {
            $clientRepository = new ClientRepository();
            if ($clientRepository->promisePaymentByClient($clientService->client_id)) {
                return [
                    'new_balance' => $balance->amount - $price,
                    'price' => $clientService->price_service,
                    'cost' => $cost
                ];
            }
        }

        return null;
    }

    public function recurrent()
    {
        $clientService = $this->clientService;
        $balance = $clientService->client->balance;
        $price = $clientService->price_service;

        $balanceAmount = $balance->amount;

        if (!$this->clientService->serviceHasIva()) {
            $price = $this->clientService->getNewPriceByIva();
        }

        if ($this->ifServiceHasDiscount()) {
            $price = $this->getNewPriceByDiscount();
        }

        $cost = $price;
        if ($this->ifHaveBalanceToPayService($clientService->client->balance->amount, $price)) {
            return [
                'new_balance' => $balanceAmount - $price,
                'price' => $balanceAmount,
                'cost' => $cost,
                'payment_in_time' => true
            ];
        } else {
            return [
                'new_balance' => $balanceAmount - $price,
                'price' => $clientService->price,
                'cost' => $cost
            ];
        }
    }

    public function clientServiceIsCustomYEsPagoUnico()
    {
        return $this->clientService instanceof ClientCustomService
            && $this->clientService->payment_type == 'Pago unico';
    }

    public function ifServiceHasDiscount()
    {
        return $this->clientService->discount && $this->clientService->discount_percent &&
            !is_null($this->clientService->start_date_discount) &&
            (\Carbon\Carbon::now()->format('Y-m-d\TH:i') >= $this->clientService->start_date_discount &&
                \Carbon\Carbon::now()->format('Y-m-d\TH:i') <= $this->clientService->end_date_discount);
    }

    public function getNewPriceByDiscount()
    {
        return $this->clientService->price - round($this->clientService->price * $this->clientService->discount_percent / 100, 2);
    }

    public static function ifHaveBalanceToPayService($balance, $price)
    {
        $newBalance = $balance - $price;
        return ($newBalance >= 0);
    }
}
