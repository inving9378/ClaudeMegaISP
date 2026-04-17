<?php

namespace App\Console\Commands\Scripts;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Jobs\SuspendServiceJob;
use App\Services\ClientMainInformationService;
use App\Services\InformationService;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class BlockedClientsActiveDontHavePaymentAfeterFourMonth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:blocked-clients-active-dont-have-payment-afeter-four-month';

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
        $informationService = new InformationService;
        $clientsToBlocked = $informationService->getClientsActiveQueNoPaganHaceMasdeTresMesesYQueNoTenganPlanAdministracion();
        foreach ($clientsToBlocked as $client) {
            $clientRepository = new ClientRepository();

            $services = ComunConstantsController::ALL_CLIENT_SERVICE;
            $clientWithServices = $clientRepository->getServicesForClient($client->id);
            foreach ($services as $service) {
                foreach ($clientWithServices->$service as $clientService) {
                    SuspendServiceJob::dispatch($clientService);
                }
            }

            $clientMainInformationService = new ClientMainInformationService($client->id);
            $clientMainInformationService->setStateBlocked();
            $clientId = $client->id;
            activity()->tap(function (Activity $activity) use ($clientId) {
                $activity->client_id =  $clientId;
            })->log('Cliente #' . $clientId . ' bloqueado desde el script BlockedClientsActiveDontHavePaymentAfeterFourMonth');
        }
    }
}
