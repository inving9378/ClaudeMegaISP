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
        // FR-05 (#539): «Envío prefactura» del cliente: vacío = usa el valor general; si no, 0-60 días.
        if ($request->has('envio_prefactura_dias')) {
            $dias = trim((string) $request->input('envio_prefactura_dias'));
            $request->merge(['envio_prefactura_dias' => $dias === '' ? null : $dias]);
            $request->validate(
                ['envio_prefactura_dias' => 'nullable|integer|min:0|max:' . \App\Services\Finance\Invoice\ProformaAnticipada::MAX_DIAS],
                [],
                ['envio_prefactura_dias' => 'Envío prefactura']
            );
        }
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
