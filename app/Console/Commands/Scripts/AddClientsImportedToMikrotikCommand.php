<?php

namespace App\Console\Commands\Scripts;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Jobs\CreateClientWithServiceJob;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Models\Client;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class AddClientsImportedToMikrotikCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addclientsimportedtomikrotik:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Agrega al mikrotik los clientes que han sido importados y tengan servicios de Internet';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected $clientRepository;

    public function handle(ClientRepository $clientRepository)
    {
        set_time_limit(0);

        $clients = Client::with(['network_ip', 'client_main_information', 'internet_service'])
            ->whereHas('internet_service')
            ->whereHas('client_main_information')
            ->whereHas('network_ip')
            ->get();
        $modelInternet = 'App\Models\Internet';

        $mikrotikServicesPPsecret = [];
        $mikrotikServicesAddressList = [];
        foreach ($clients as $client) {
            $clientService = $client->internet_service;
            foreach ($clientService as $service) {
                $estado = $client->client_main_information->estado;
                if ($estado != ComunConstantsController::STATE_ACTIVE) {
                    $mikrotikServicesAddressList[] = $service;
                } else {
                    $mikrotikServicesPPsecret[] = $service;
                }
            }
        }

        foreach ($mikrotikServicesPPsecret as $service) {
            CreateClientWithServiceJob::dispatch($service, $modelInternet, true);
            MikrotikRemoveClientServiceFromAddressList::dispatch($service);
            activity()->tap(function (Activity $activity) use ($service) {
                $activity->client_id = $service->client_id;
            })->log('ClientInternetService # ' . $service->id . ' Servicio restaurado en Pp Secret por el sitema en el comando AddClientsImportedToMikrotikCommand Cliente #' . $service->client_id);
        }

        foreach ($mikrotikServicesAddressList as $service) {
            MikrotikCreateAddressList::dispatch($service, true);
            activity()->tap(function (Activity $activity) use ($service) {
                $activity->client_id = $service->client_id;
            })->log('ClientInternetService # ' . $service->id . ' Servicio restaurado en address_list por el sitema en el comando AddClientsImportedToMikrotikCommand Cliente #' . $service->client_id);
        }
    }
}
