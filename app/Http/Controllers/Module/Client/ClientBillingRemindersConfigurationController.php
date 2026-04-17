<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Http\Repository\ClientRepository;

class ClientBillingRemindersConfigurationController extends Controller
{

    private $clientRepository;

    public function __construct( ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    public function update(Request $request, $id)
    {
        $client = Client::find($id);
        return $this->saveSingleRelationWithoutModel('App\Models\RemindersConfiguration', 'reminder_configuration', 'client_id', 'id', $client, $request);
    }

    public function getReminderPaymentAmount($id)
    {
         $client = Client::find($id);
         isset($client->balance()->first()->amount) ? $balance = $client->balance()->first()->amount : $balance = 0;
         $price = $this->clientRepository->getCostAllServiceActive($id);

        if ($balance < 0) {
            return ($price  + ($balance * -1));
        };
     
        return $price;
        
    }
}
