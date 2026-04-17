<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientCustomServiceRepository;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientVozServiceRepository;
use App\Models\Client;
use App\Models\ClientBundleService;
use App\Models\ClientMainInformation;
use App\Models\Payment;
use App\Models\TypeBilling;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        DB::statement('SET FOREIGN_KEY_CHECKS=0'); // Deshabilita restricciones de clave externa

        $clientsActive = Client::with('client_main_information.type_billing', 'billing_configuration')
            ->whereHas('client_main_information', function ($query) {
                $query->where('estado', ComunConstantsController::STATE_ACTIVE);
            })->get();
        foreach ($clientsActive as $client) {
            $clientId = $client->id;

            $internetServiceRepository = new ClientInternetServiceRepository();
            $serviciosDeInternet = $internetServiceRepository->getServiceFilterByClientId($clientId);
            foreach ($serviciosDeInternet as $service) {
                $this->updateService($service, $client);
            }

            $vosServiceRepository = new ClientVozServiceRepository();
            $serviciosDeVoz = $vosServiceRepository->getServiceFilterByClientId($clientId);
            foreach ($serviciosDeVoz as $service) {
                $this->updateService($service, $client);
            }

            $customServiceRepository = new ClientCustomServiceRepository();
            $serviciosCustom = $customServiceRepository->getServiceFilterByClientId($clientId);
            foreach ($serviciosCustom as $service) {
                $this->updateService($service, $client);
            }

            $bundleServiceRepository = new ClientBundleServiceRepository();
            $serviciosBundles = $bundleServiceRepository->getServicesFilterByClientId($clientId);
            foreach ($serviciosBundles as $service) {
                $this->updateBundleService($service, $client);
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1'); // Habilita restricciones de clave externa
        }
    }

    public function updateBundleService($service, $client)
    {
        $bundleService = ClientBundleService::find($service->id);
        $endDate = $this->getEndDate($bundleService, $client);
        $bundleService->update([
            'deployed' => ComunConstantsController::IS_NUMERICAL_TRUE,
            'charged' => ComunConstantsController::IS_NUMERICAL_TRUE,
            'contract_end_date' => $endDate
        ]);
    }

    public function updateService($service, $client)
    {
        $endDate = $this->getEndDate($service, $client);
        $service->update([
            'deployed' => ComunConstantsController::IS_NUMERICAL_TRUE,
            'charged' => ComunConstantsController::IS_NUMERICAL_TRUE,
            'finish_date' => $endDate
        ]);
    }

    public function getEndDate($clientService, $client)
    {
        $billingExpiration = new \App\Services\ClientService\BillingExpirationService($client);
        return $billingExpiration->getBillingExpirationByTypeOfBillingOnlyForMigrationUseCuandoNingunClienteTieneUnaFechaDeCorte();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
