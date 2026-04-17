<?php

namespace App\Console\Commands\Disabled;

use App\Console\Commands\Disabled\excel\CImport;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Jobs\SuspendServiceJob;
use App\Models\Client;
use App\Services\ClientMainInformationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ChangeClientStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:change-client-status';

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
        $elements = (new CImport)->toArray(__DIR__ . '/excel/clients_to_change_status.xlsx');
        foreach ($elements[0] as $key => $element) {
            if ($key === 0) continue;

            $id = $element[0];
            $status = $element[1];

            $client = Client::find($id);
            if ($client) {
                if ($client->client_main_information->estado === ComunConstantsController::STATE_ACTIVE && $status === 'blocked') {
                    $this->creaAjusteYBloquea($client);
                }
                if ($client->client_main_information->estado === ComunConstantsController::STATE_BLOCKED && $status === 'active') {
                    $this->creaAjusteYActiva($client);
                }
            }
        }
    }

    public function creaAjusteYBloquea($client)
    {
        Log::info('creaTransaccionDeAjusteYBloquea ' . $client->id);
        $clientMainInformationService = new ClientMainInformationService($client->id);
        $clientMainInformationService->setStateBlocked();

        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getClientWithServiceActive($client->id);

        //Suspende Servicios
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                try {
                    SuspendServiceJob::dispatch($clientService);
                } catch (\Exception $exception) {
                }
            }
        }
    }

    public function creaAjusteYActiva($client)
    {
        Log::info('creaTransaccionDeAjusteYActiva ' . $client->id);
        $clientMainInformationService = new ClientMainInformationService($client->id);
        $clientMainInformationService->setStateActive();

        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getServicesForClient($client->id);

        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                $clientService->update([
                    'estado' => ComunConstantsController::STATE_ACTIVE,
                    'charged' => true,
                    'deployed' => true
                ]);
            }
        }
    }
}
