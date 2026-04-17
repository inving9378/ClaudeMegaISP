<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientInternetServiceRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteClientInternetServicesWHereDoestHaveIp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-client-internet-services-w-here-doest-have-ip';

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
        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $clients = [];
        $clientInternetServicesWhereDoesntHaveIp = $clientInternetServiceRepository->getServicesInternetsWhereDoesntHaveIpAndNotBundleService();

        foreach ($clientInternetServicesWhereDoesntHaveIp as $clientInternetService) {
            $clients[] = $clientInternetService->client_id;
            activity()->log('eliminando client_internet_services '. $clientInternetService->id . ' desde DeleteClientInternetServicesWHereDoestHaveIp');
            DB::table('client_internet_services')->where('id', $clientInternetService->id)->delete();
        }

        dd($clients);
    }
}
