<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Http\Request;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use Illuminate\Support\Facades\Log;

class ClientBillingConfigurationController extends Controller
{
    private $clientRepository;

    public function __construct(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    public function update(Request $request, $id)
    {
        $client = Client::find($id);
        return $this->saveSingleRelationWithoutModel('App\Modules\Core\Configuracion\Models\BillingConfiguration', 'billing_configuration','client_id','id', $client, $request);
    }

    public function getBillingInformationBlock($clientId)
    {
        return $this->clientRepository->getBillingInformationBlock($clientId);
    }

    public function getClientDebitRectificationAgreement(Request $request, $clientId)
    {
        return $this->clientRepository->getClientDebitRectificationAgreement($request, $clientId );
    }

    public function getPaymentMethod($paymentMethod_id)
    {
        return $this->clientRepository->getPaymentMethod( $paymentMethod_id );
    }

    public function getTypeOfBillingByClientId($clientId)
    {
        return $this->clientRepository->getTypeOfBillingByClientId($clientId);
    }



}
