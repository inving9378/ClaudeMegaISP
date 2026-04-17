<?php

namespace App\Http\Controllers\Module\Setting\ServiceInAddressList;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\setting\service_in_address_list\ServiceInAddressListDatatableHelper;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Services\LogService;

class ServiceInAddressListController extends CrudModalController
{
    public function __construct(ServiceInAddressListDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\ServiceInAddressList';
        $this->data['url'] = 'meganet.module.setting.service_in_address_list';
        $this->data['module'] = 'ServiceInAddressList';
    }

    public function removeServiceToAddressList($id)
    {
        $serviceAddressList = $this->data['model']::findOrFail($id);
        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $clientInternetService = $clientInternetServiceRepository->getServiceFilterById($serviceAddressList->serviceable_id);
        MikrotikRemoveClientServiceFromAddressList::dispatchAfterResponse($clientInternetService);
        $client = (new ClientRepository)->getClientById($clientInternetService->client_id);
        $logService = new LogService();
        $logService->log($client, 'Su servicio ' . $clientInternetService->service_name . ' fue removido de address_list desde la tabla de service_in_address_list');
        $serviceAddressList->delete();
    }
}
