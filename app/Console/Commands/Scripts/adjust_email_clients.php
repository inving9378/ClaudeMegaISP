<?php

namespace App\Console\Commands\Scripts;

use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Services\LogService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class adjust_email_clients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:adjust_email_clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cambia el email de los clientes';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        return Model::withoutEvents(function () {
            try {
                ClientMainInformation::whereIn('client_id', [
                    6793,
                    6794,
                    6797,
                    6798
                ])->update(['email' => 'jgarcia@prk.com.mx']);

                $log = new LogService();
                $clients = Client::whereIn('id', [
                    6793,
                    6794,
                    6797,
                    6798
                ]) ->get();

                foreach ($clients as $client) {
                    $log = new LogService();
                    $log->log($client, 'Se ha cambiado el email del cliente' . $client->id . ' "a jgarcia@prk.com.mx" desde el script adjust_email_clients');
                }

            } catch (\Throwable $th) {
                //throw $th;
            }
        });
    }
}
