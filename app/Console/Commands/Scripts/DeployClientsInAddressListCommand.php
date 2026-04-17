<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientRepository;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class DeployClientsInAddressListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rectify_address_list:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rectifica en el address list';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        $clientRepository = new ClientRepository();

        $clientsBlockedWithServiceInternet = $clientRepository->getClientsWithInternetServiceNotActive();
        foreach ($clientsBlockedWithServiceInternet as $clients) {
            foreach ($clients->internet_service as $service) {
                MikrotikCreateAddressList::dispatchAfterResponse($service);
                activity()->tap(function (Activity $activity) use ($service) {
                    $activity->client_id = $service->client_id;
                })->log('ClientInternetService # ' . $service->id . ' Servicio colocado en el address_list desde el comando DeployClientsInAddressListCommand Cliente #' . $service->client_id);
            }
        }
    }
}
