<?php

namespace App\Console\Commands\Scripts;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Jobs\Client\BillingService\RectifyBalanceAndCreateTransaction;
use App\Models\Client;
use App\Http\Repository\ClientRepository;
use App\Services\InformationService;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class CreateServiceChargeInClientWithPositiveGracePeriod extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-service-charge-in-client-with-positive-grace-period';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = new InformationService();
        $clients = $service->getClientsActiveWithGracePeriodAndBalancePositive();
        foreach ($clients as $client) {
            $clientRepository = new ClientRepository();
            $clientWithServices = $clientRepository->getServicesForClient($client->id);
            $services = ComunConstantsController::ALL_CLIENT_SERVICE;
            foreach ($services as $service) {
                foreach ($clientWithServices->$service as $clientService) {
                    RectifyBalanceAndCreateTransaction::dispatch($clientService, 1);
                }
            }

            activity()->tap(function (Activity $activity) use ($client) {
                $activity->client_id =  $client->id;
            })->log('Cliente #' . $client->id . ' agregado cobro del servicio desde el comando CreateServiceChargeInClientWithPositiveGracePeriod');
        }
    }
}
