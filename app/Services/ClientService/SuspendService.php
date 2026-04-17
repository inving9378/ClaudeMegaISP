<?php

namespace App\Services\ClientService;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Jobs\SuspendServiceJob;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\TypeBilling;
use App\Services\ClientMainInformationService;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class SuspendService
{
    public function suspendServiceByClient(Client $client)
    {
        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getServicesForClient($client->id);

        // remueve todos los servicios
        $services = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($services as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                SuspendServiceJob::dispatch($clientService);
            }
        }

        // bloquea el cliente
        $clientMainInformationService = new ClientMainInformationService($client->id);
        $clientMainInformationService->setStateBlocked();

        $clientRepository = new ClientRepository();
        $typeOfBilling = $clientRepository->getTypeOfBilling($client);
        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT && $client->fecha_fin_periodo_gracia == null) {
            $clientRepository->addPeriodoGracia($client);
            activity()->tap(function (Activity $activity) use ($client) {
                $activity->client_id = $client->id;
            })->log('Cliente #' . $client->id . ' se le agrega periodo de gracia');
        }
        activity()->tap(function (Activity $activity) use ($client) {
            $activity->client_id = $client->id;
        })->log('Cliente #' . $client->id . ' bloqueado desde el SuspendService');
    }

    public function getClientToSuspendServices($date = null)
    {
        return (new ClientRepository())->getClientsToSuspendServices($date);
    }

    public function suspendClientsServices()
    {
        $clientsToSuspend = $this->getClientToSuspendServices();
        foreach ($clientsToSuspend as $client) {
            $this->suspendServiceByClient($client);
        }
    }

    public function ifClientChangeToBlockedRemoveDateCorte(ClientMainInformation $clientMainInformation): void
    {
        $previousEstado = $clientMainInformation->getOriginal('estado');
        $clientMainInformation->load('client');
        if (
            $previousEstado !== ComunConstantsController::STATE_BLOCKED &&
            $clientMainInformation->estado === ComunConstantsController::STATE_BLOCKED
        ) {
            $repository = new ClientRepository();
            $repository->removeFechaCorteById($clientMainInformation->client->id);
            $clientId = $clientMainInformation->client->id;
            $this->updateFechaSuspension($clientMainInformation->client);

            $clientRepository = new ClientRepository();
            $clientWithServices = $clientRepository->getServicesForClient($clientMainInformation->client_id);

            // remueve todos los servicios
            $services = ComunConstantsController::ALL_CLIENT_SERVICE;
            foreach ($services as $service) {
                foreach ($clientWithServices->$service as $clientService) {
                    SuspendServiceJob::dispatch($clientService);
                }
            }

            activity()->tap(function (Activity $activity) use ($clientId) {
                $activity->client_id =  $clientId;
            })->log('Cliente #' . $clientId . ' bloqueado desde el SuspendService');
        }
    }

    public function ifClientChangeToInactiveRemoveDateCorteAndDatePayment(ClientMainInformation $clientMainInformation): void
    {
        $previousEstado = $clientMainInformation->getOriginal('estado');
        $clientMainInformation->load('client');
        if (
            $clientMainInformation->estado === ComunConstantsController::STATE_INACTIVE
        ) {

            $clientId = $clientMainInformation->client->id;

            $clientRepository = new ClientRepository();
            $clientWithServices = $clientRepository->getServicesForClient($clientMainInformation->client_id);

            // remueve todos los servicios
            $services = ComunConstantsController::ALL_CLIENT_SERVICE;
            foreach ($services as $service) {
                foreach ($clientWithServices->$service as $clientService) {
                    SuspendServiceJob::dispatch($clientService);
                }
            }
            $client = $clientMainInformation->client;
            $client->fecha_pago = null;
            $client->fecha_corte = null;
            $client->save();
            activity()->tap(function (Activity $activity) use ($clientId) {
                $activity->client_id =  $clientId;
            })->log('Cliente #' . $clientId . ' pasado a inactivo por lo que se deja de Facturar');
        }
    }



    public function updateFechaSuspension($client)
    {
        $client->fecha_suspension = Carbon::now()->toDateTimeString();
        $client->save();
    }
}
