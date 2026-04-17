<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Services\InformationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class UpdateINternetServicesToClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-i-nternet-services-to-clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los servicios que su paquete padre no existe';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $informationService = new InformationService();
        $clientsId = $informationService->getClientesConServiciosDeInternetQuePertenecenAunPaqueteYelPaqueteNoExiste();

        foreach ($clientsId as $id) {
            $clientINternetServiceRepository = new ClientInternetServiceRepository();
            $services = $clientINternetServiceRepository->getServiceFilterByClientId($id);
            foreach ($services as $service) {
                $service->client_bundle_service_id = null;
                $service->save();
                activity()->tap(function (Activity $activity) use ($service) {
                    $activity->client_id =  $service->client_id;
                })->log('Cliente #' . $service->client_id . ' se le actualizo su Servicio de Internet porque el paquete padre no existe y se dejo uno Internet puro');
            }

            $clientRepository = new ClientRepository();
            $fechaUltimoPago = $clientRepository->obtenerLaFechaDelUltimoPago($id);
            if($fechaUltimoPago < Carbon::now()->subMonths(4)->toDateString()){
                $clientMainInformationRepository = new ClientMainInformationRepository();
                $clientMainInformationRepository->setClientMainInformationByClientId($id);
                $clientMainInformationRepository->setStateBlocked();
            }
        }
    }
}
