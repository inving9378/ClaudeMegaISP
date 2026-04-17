<?php

namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\module\client\ClientUpdateBillingAddressRequest;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientBillingAddressController extends Controller
{
    public function update(ClientUpdateBillingAddressRequest $request, $id)
    {
        $client = Client::find($id);
        return  $this->saveSingleRelationWithoutModel('App\Models\BillingAddress', 'billing_address','client_id','id', $client, $request);

    }
}
