<?php

namespace App\Console\Commands\Scripts;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientRepository;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Jobs\SuspendServiceJob;
use App\Models\Mikrotik;
use App\Services\ClientService\SuspendService;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class SuspendServicesClientsNotActive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:suspend-clients-not-active';

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
        $clientsIds = [
            550,
            1002,
            1310,
            1370,
            1669,
            1688,
            1733,
            1758,
            1768,
            1777,
            1795,
            1828,
            1831,
            1836,
            1861,
            1888,
            1889,
            1894,
            1909,
            1921,
            1922,
            1930,
            1960,
            1964,
            2037,
            2046,
            2087,
            2115,
            2151,
            2157,
            2161,
            2168,
            2191,
            2240,
            2241,
            2249,
            2250,
            2276,
            2292,
            2313,
            2329,
            2341,
            2346,
            2352,
            2373,
            2375,
            2429,
            2482,
            2506,
            2529,
            2549,
            2564,
            2568,
            2577,
            2583,
            2588,
            2590,
            2604,
            2605,
            2613,
            2622,
            2632,
            2643,
            2653,
            2666,
            2669,
            2677,
            2678,
            2695,
            2724,
            2741,
            2754,
            2800,
            2806,
            2844,
            2860,
            2900,
            2902,
            2915,
            2919,
            2921,
            2951,
            2957,
            2963,
            3028,
            3060,
            3185,
            3667,
            3734,
            3772,
            3910,
            3939,
            4057,
            4943,
            5064,
            5073,
            5123,
            5411,
            5626,
            5684,
            5828,
            5968,
            5987,
            6426,
            6488,
            6442,
        ];

        foreach ($clientsIds as $id) {
            $ClientInternetServiceRepository = new ClientInternetServiceRepository();
            $service = $ClientInternetServiceRepository->getServiceFilterByClientId($id);
            foreach ($service as $clientService) {
                MikrotikCreateAddressList::dispatch($clientService);
            }
            activity()->tap(function (Activity $activity) use ($id) {
                $activity->client_id = $id;
            })->log('Cliente #' . $id . ' Suspendido el servicio de internet en mikrotik');
        }
    }
}
