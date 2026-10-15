<?php

namespace App\Observers;

use App\Http\Repository\ClientRepository;
use App\Models\Balance;
use App\Models\ClientMainInformation;
use App\Models\ClientUser;
use App\Models\TypeBilling;
use Illuminate\Support\Facades\Log;

class ClientBalanceObserver
{
    public function updating(Balance $balance)
    {
        $client = $balance->client;
        $originalAmount = $balance->getOriginal('amount');

        if ($this->ifChangeAmountAndClientIsRecurrentAndActualAmountIsLessThanZero($balance, $client, $originalAmount)) {
            $repository = new ClientRepository();
            $repository->addPeriodoGracia($balance->client);
        }
    }


    public function ifChangeAmountAndClientIsRecurrentAndActualAmountIsLessThanZero($balance, $client, $originalAmount){
        $client->load('client_main_information');
        return $balance->isDirty('amount') && $client->client_main_information->type_of_billing_id == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT && $originalAmount >= 0 && $balance->amount < 0;
    }

}
