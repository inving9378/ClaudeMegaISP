<?php

namespace App\Console\Commands\Disabled\Old;

use App\Models\Client;
use App\Services\ClientMainInformationService;
use Illuminate\Console\Command;

class MakeTheClientInactiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maketheclientinactive:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Todos los clientes que esten bloqueados, que hayan sido desplegados y que hayan cobrado su primera vez que sean igual o sebrepasenn su tiempo de gracia pasaran a inactivos';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $clients = Client::with('client_grace_period')
            ->leftJoin('client_grace_periods', 'clients.id', '=', 'client_grace_periods.client_id')
            ->leftJoin('billing_configurations', 'clients.id', '=', 'billing_configurations.client_id')
            ->where(function ($query) {
                $query->tieneServicioDeIntenetPagoYDesplegado()
                    ->oTieneBundleServicePagoYDesplegado();
            })
            ->hasGracePeriod()
            ->peridoDeGraciaVencido()
            ->get();

        foreach ($clients as $client) {
            $service = new ClientMainInformationService($client->client_id);
            $service->setStateInactive();

            $client->client_grace_period()->delete();
        }
    }
}
