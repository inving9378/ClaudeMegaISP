<?php

namespace App\Services;

use App\Models\ClientCustomService;
use Carbon\Carbon;

const PROCESS = [
    '1' => 'recurrent',
    '2' => 'daily',
    '3' => 'custom'
];

class GetNewBalanceByTypeOfBillingService
{
    protected $clientService;
    protected $client;

    public function __construct($clientService, $client)
    {
        $this->client = $client;
        $this->clientService = $clientService;
    }

    public function getNewBalanceAndPriceByTypeOfBilling()
    {
        if (isset(PROCESS[$this->client->client_main_information->type_of_billing_id])) {
            $function = PROCESS[$this->client->client_main_information->type_of_billing_id];            
            return $this->$function();
        }
        return null;
    }

    public function daily()
    {
        $clientService = $this->clientService;
        $balance = $this->client->balance;

        $price = $clientService->price;

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

        return [
            'new_balance' => $balance->amount - $cost,
            'price' => $clientService->price,
            'cost' => $cost,
            'payment_in_time' => true
        ];
    }

    public function custom()
    {
        //TODO Revisar aqui esta funcion
        $clientService = $this->clientService;
        $balance =  $this->client->balance;

        $price = $clientService->price;

        if (!$this->clientService->serviceHasIva()) {
            $price = $this->clientService->getNewPriceByIva();
        }

        if ($this->ifServiceHasDiscount()) {
            $price = $this->getNewPriceByDiscount();
        }

        $cost = $price;
        return [
            'new_balance' => $balance->amount - $price,
            'price' => $clientService->price,
            'cost' => $cost,
        ];
    }

    public function recurrent()
    {
        $clientService = $this->clientService;
        $balance =  $this->client->balance;
        $price = $clientService->price;      

        if (!$this->clientService->serviceHasIva()) {
            $price = $this->clientService->getNewPriceByIva();
        }

        if ($this->ifServiceHasDiscount()) {
            $price = $this->getNewPriceByDiscount();            
        }

        $cost = $price;

        return [
            'new_balance' => $balance->amount - $price,
            'price' => $clientService->price,
            'cost' => $cost,
        ];
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
}
