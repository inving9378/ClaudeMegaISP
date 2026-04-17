<?php

namespace App\Console\Commands\Scripts;

use App\Models\ClientMainInformation;
use App\Services\FormatDateService;
use App\Services\LogService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Stmt\TryCatch;

class AjusteClientActivationDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ajuste-client-activation-date';

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
        $clientMainInformation = ClientMainInformation::all();
        Model::withoutEvents(function () use ($clientMainInformation) {
            foreach ($clientMainInformation as $c) {
                $logService = new LogService();
                $client = $c->client;


                $activationDate = $c->activation_date;
                if ($activationDate != null) {
                    $formatedDate = (new FormatDateService($activationDate))->formatDate();

                    try {
                        $dateCarbon = Carbon::parse($formatedDate)->format('Y-m-d');
                        $this->info('Cliente que se le cambia la fecha de ' . $activationDate . ' => ' . $dateCarbon);
                        $c->activation_date = $dateCarbon;
                        $c->save();
                        $logService->log($client,'Cliente ' . $client->id. ' se le cambia el formato de fecha de activacion de '. $activationDate . ' a '. $dateCarbon . ' desde el comando app:ajuste-client-activation-date');
                    } catch (\Throwable $th) {
                        //TODO SE va a quedar el usuario 4981 que es irvin con esta fecha (27/09/2023, 06/02/2024, 03/05/2023)
                        $this->info($activationDate);
                    }
                }
            }

        });

    }
}
