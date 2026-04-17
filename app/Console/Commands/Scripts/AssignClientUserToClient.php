<?php

namespace App\Console\Commands\Scripts;

use App\Models\ClientInternetService;
use App\Models\ClientUser;
use Illuminate\Console\Command;

class AssignClientUserToClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-client-user-to-client';

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
        $clientUsers = ClientUser::all();
        foreach ($clientUsers as $clientUser) {
            $serviceInternet = ClientInternetService::where('id', $clientUser->service_id)->first();
            if ($serviceInternet) {
                $clientUser->client_id = $serviceInternet->client_id;
                activity()->log('ClientUser ' . $clientUser->client_id . ' Actualizado a ' . $serviceInternet->client_id . 'desde el AssignClientUserToClient command');
                $clientUser->save();
            } else {
                $clientUser->delete();
            }
        }
    }
}
