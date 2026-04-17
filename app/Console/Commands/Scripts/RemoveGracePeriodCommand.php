<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientRepository;
use App\Models\Client;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class RemoveGracePeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remove-grace-period-command {clientId}';

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
        $client = Client::findOrFail($this->argument('clientId'));
        $clientRepository = new ClientRepository();
        $clientRepository->removePeriodoGracia($client);

        activity()->tap(function(Activity $activity) use ($client) {
            $activity->client_id = $client->id;
        })->log('Cliente #' . $client->id . ' removido su periodo gracia desde RemoveGracePeriodCommand (removePeriodoGracia)');
    }
}
