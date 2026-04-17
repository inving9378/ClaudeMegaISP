<?php

namespace App\Console\Commands\Scripts;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Client;
use App\Models\ClientMainInformation;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class BloquedClientWithOlderPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:bloqued-client-with-older-payment';

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
        $clients = Client::with(['payments' => function ($query) {
            $query->orderBy('date', 'desc');
        }])->whereHas('client_main_information', function ($query) {
            $query->stateActive();
        })
            ->whereHas('internet_service')
            ->whereHas('payments')
            ->whereNull('fecha_corte')
            ->whereNull('fecha_pago')
            ->whereNull('fecha_fin_periodo_gracia')
            ->where('active_promise_payment', 0)
            ->orderBy('id', 'desc')
            ->get();

        $allClients = 0;
        foreach ($clients as $client) {
            $lastPayment = $client->payments->first();
            if ($lastPayment->date < '2024-09-09') {
                $allClients++;
                ClientMainInformation::where('client_id', $client->id)->update(['estado' => ComunConstantsController::STATE_BLOCKED]);

                activity()->tap(function(Activity $activity) use ($client) {
                    $activity->client_id = $client->id;
                })->log('Client # '. $client->id .' bloqued from BloquedClientWithOlderPayment');
            }
        }

        dump('Clients bloqued ' . $allClients);
    }
}
