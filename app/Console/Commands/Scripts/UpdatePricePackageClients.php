<?php

namespace App\Console\Commands\Scripts;

use App\Models\Client;
use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use App\Models\ClientVozService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class UpdatePricePackageClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-price-package-clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'script para actualizar los precios de los servicios segun el plan';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clientBundleServices = ClientBundleService::with('bundle')->whereHas('bundle')->get();


        foreach ($clientBundleServices as $service) {
            $price = $service->bundle->price;
            if ($service->price != $price) {
                $service->price = $price;
                $service->save();
                Log::info('paquete ' . $service->id . ' actualizado Cliente => '. $service->client_id);
                activity()->tap(function (Activity $activity) use ($service) {
                    $activity->client_id = $service->client_id;
                })->log('ClientBundleService # ' . $service->id . ' Servicio actualizado el Precio para Cliente #' . $service->client_id);
            }
        }
        $clientInternetServices = ClientInternetService::with('internet')->whereHas('internet')->whereNull('client_bundle_service_id')->get();


        foreach ($clientInternetServices as $service) {
            $price = $service->internet->price;
            if ($service->price != $price) {
                $service->price = $price;
                $service->save();
                Log::info('internte ' . $service->id . ' actualizado Cliente => '. $service->client_id);
                activity()->tap(function (Activity $activity) use ($service) {
                    $activity->client_id = $service->client_id;
                })->log('ClientInternetService # ' . $service->id . ' Servicio actualizado el Precio para Cliente #' . $service->client_id);
            }
        }

        $clientCustomServices = ClientCustomService::with('custom')->whereHas('custom')->whereNull('client_bundle_service_id')->get();

        foreach ($clientCustomServices as $service) {
            $price = $service->custom->price;
            if ($service->price != $price) {
                $service->price = $price;
                $service->save();
                Log::info('custom ' . $service->id . ' actualizado Cliente => '. $service->client_id);
                activity()->tap(function (Activity $activity) use ($service) {
                    $activity->client_id = $service->client_id;
                })->log('ClientCustomService # ' . $service->id . ' Servicio actualizado el Precio para Cliente #' . $service->client_id);
            }
        }

        $clientVozServices = ClientVozService::with('voise')->whereHas('voise')->whereNull('client_bundle_service_id')->get();

        foreach ($clientVozServices as $service) {
            $price = $service->voise->price;
            if ($service->price != $price) {
                $service->price = $price;
                $service->save();
                Log::info('voz ' . $service->id . ' actualizado Cliente => '. $service->client_id);
                activity()->tap(function (Activity $activity) use ($service) {
                    $activity->client_id = $service->client_id;
                })->log('ClientVozService # ' . $service->id . ' Servicio actualizado el Precio para Cliente #' . $service->client_id);
            }
        }
    }
}
