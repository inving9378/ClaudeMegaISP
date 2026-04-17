<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Services\LogService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class FixedClientsWithInternetCreadoManual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fixed-clients-with-internet-creado-manual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Los clientes con planes de internet que su descripicion es Internet Creado Manual Pasan a Pertenecer a un Paquete si el Cliente es recurrente y si sin custom pasan al plan de internet 100mb 449';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clients = $this->obtenerClientesQueSuInternetHayaSidoCreadoManualYQueNOPertenezcaAlBundleQueTiene();
        foreach ($clients as $client) {
            $clientRepository = new ClientRepository();
            if ($clientRepository->isRecurrente($client->client_main_information->type_of_billing_id) && $this->hasBundle($client)) {
                $this->modifyRecurentUserToPlus($client);
                continue;
            }
            if ($clientRepository->isPrepaid($client->client_main_information->type_of_billing_id)) {
                $this->modifyPrepaidUserTo100Mb449($client);
                continue;
            }
        }
    }

    public function hasBundle($client)
    {
        return $client->bundle_service->count();
    }

    public function modifyRecurentUserToPlus($client)
    {
        $model = $client->internet_service();
        $vozService = $client->voz_service();
        $clientBundleService = $client->bundle_service();
        $client->custom_service()->delete();
        Model::withoutEvents(function () use ($model, $vozService, $clientBundleService) {
            $model->update([
                'internet_id' => 1,
                'client_bundle_service_id' => $clientBundleService->first()->id
            ]);

            $clientBundleService->update([
                'bundle_id' => 16
            ]);

            if ($vozService->count()) {
                $vozService->update([
                    'voz_id' => 1,
                ]);
            }
        });

        $logService = new LogService();
        $logService->log($client, 'Modificado User to Plan PLUS+ por Sistema');
    }

    public function modifyPrepaidUserTo100Mb449($client)
    {
        $model = $client->internet_service();
        Model::withoutEvents(function () use ($model) {
            $model->update([
                'internet_id' => 36,
            ]);
        });

        $logService = new LogService();
        $logService->log($client, 'Modificado User to Plan 100 Mb 449 por Sistema');
    }

    public function obtenerClientesQueSuInternetHayaSidoCreadoManualYQueNOPertenezcaAlBundleQueTiene()
    {
        return Client::with('internet_service', 'bundle_service', 'voz_service')
            ->whereHas('internet_service', function ($query) {
                $query->where('description', 'Internet creado manual');
            })->get();
    }
}
