<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientBundleServiceRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteClientBundleServiceWithDuplicateInternetService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-client-bundle-service-with-duplicate-internet-service';

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
        $clientBundleServiceRepository = new ClientBundleServiceRepository();

        $clientBundleServicesWhereInternetDoesntHaveIp = $clientBundleServiceRepository->getBundlesWhereInternetDoesntHaveIp();
        $clients = [];

        foreach($clientBundleServicesWhereInternetDoesntHaveIp as $bundle){
            $clients[] = $bundle->client_id;

            activity()->log('eliminando client_bundle_services '. $bundle->id . ' desde DeleteClientBundleServiceWithDuplicateInternetService');
            DB::table('client_bundle_services')->where('id', $bundle->id)->delete();

            activity()->log('eliminando client_internet_services '. $bundle->service_internet->pluck('id')->implode(', ') . ' desde DeleteClientBundleServiceWithDuplicateInternetService');
            DB::table('client_internet_services')->where('client_bundle_service_id', $bundle->id)->delete();

            DB::table('client_voz_services')->where('client_bundle_service_id', $bundle->id)->delete();
            DB::table('client_custom_services')->where('client_bundle_service_id', $bundle->id)->delete();
        }
        dd($clients);
    }
}
