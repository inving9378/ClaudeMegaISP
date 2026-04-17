<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientCustomServiceRepository;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientVozServiceRepository;
use App\Http\Repository\PaymentRepository;
use App\Models\Client;
use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use App\Models\ClientMainInformation;
use App\Models\ClientVozService;
use App\Models\Payment;
use App\Models\TypeBilling;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $clientsActive = ClientMainInformation::where('estado', ComunConstantsController::STATE_ACTIVE);
        foreach ($clientsActive as $client) {
            $clientId = $client->client_id;

            $internetServiceRepository = new ClientInternetServiceRepository();
            $serviciosDeInternet = $internetServiceRepository->getServiceFilterByClientId($clientId);
            foreach ($serviciosDeInternet as $service) {
                $this->updateService($service);
            }

            $vosServiceRepository = new ClientVozServiceRepository();
            $serviciosDeVoz = $vosServiceRepository->getServiceFilterByClientId($clientId);
            foreach ($serviciosDeVoz as $service) {
                $this->updateService($service);
            }

            $customServiceRepository = new ClientCustomServiceRepository();
            $serviciosCustom = $customServiceRepository->getServiceFilterByClientId($clientId);
            foreach ($serviciosCustom as $service) {
                $this->updateService($service);
            }

            $bundleServiceRepository = new ClientBundleServiceRepository();
            $serviciosBundles = $bundleServiceRepository->getServicesFilterByClientId($clientId);
            foreach ($serviciosBundles as $service) {
                $this->updateService($service);
            }
        }
    }

    public function updateService($service)
    {
        if ($service->client_bundle_service_id != null) {
            $bundleService = ClientBundleService::find($service->client_bundle_service_id);
            $endDate = $this->getEndDate($bundleService);
            $bundleService->update([
                'deployed' => ComunConstantsController::IS_NUMERICAL_TRUE,
                'charged' => ComunConstantsController::IS_NUMERICAL_TRUE,
                'contract_end_date' => $endDate
            ]);
        } else {
            $endDate = $this->getEndDate($service);
            $service->update([
                'deployed' => ComunConstantsController::IS_NUMERICAL_TRUE,
                'charged' => ComunConstantsController::IS_NUMERICAL_TRUE,
                'finish_date' => $endDate
            ]);
        }
    }



    public function getEndDate($clientService)
    {
        $typeOfBilling = $clientService->client->client_main_information->type_of_billing_id;

        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT) {
            $billingConfiguration = $clientService->client->billing_configuration()->first();
            $billingDate = $billingConfiguration->billing_date;
            $billingExpiration = $billingConfiguration->billing_expiration;
            $nextMonth = Carbon::now()->addMonthNoOverflow()->startOfMonth();
            $desiredDate = $nextMonth->day($billingDate)->addDays($billingExpiration);
            return $desiredDate->toDateTimeString();
        }

        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM) {
            $lastPaymentDate = $this->obtenerLaFechaDelUltimoPago($clientService->client_id);
            if ($lastPaymentDate) {
                $desiredDate = Carbon::parse($lastPaymentDate)->addMonth()->subDay()->endOfDay();
                return $desiredDate->format('Y-m-d H:i:s');
            } else {
                // Manejar el caso en el que no hay pagos previos
                return Carbon::now()->addMonthNoOverflow()->subDay()->endOfDay()->format('Y-m-d H:i:s');
            }
        }

        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY) {
            return Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        }

        // Manejar otros tipos de facturación si es necesario
        return null;
    }

    public function obtenerLaFechaDelUltimoPago($clientId)
    {
        $ultimoPago = Payment::where('paymentable_id', $clientId)
            ->orderBy('date', 'desc')
            ->first();

        if ($ultimoPago) {
            // Devolver la fecha del último pago
            return $ultimoPago->date;
        } else {
            // Devolver null si no se encontraron pagos
            return null;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
