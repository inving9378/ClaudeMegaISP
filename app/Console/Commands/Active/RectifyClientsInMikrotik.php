<?php

namespace App\Console\Commands\Active;

use App\Jobs\RectifyClientsInRouterJob;
use App\Models\Router;
use Illuminate\Console\Command;

class RectifyClientsInMikrotik extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rectify-clients-in-mikrotik {debug?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtiene todos los ips del mikrotik
                            --> Obtiene todos los servicios de internet y de ellos filtra los clientes que estan activos y no activos
                            --> Compara los ips
                            --> Si el cliente esta activo y su ip esta en el address list de mikrotik, lo elimina del address list
                            --> Si el cliente NO esta activo y su ip NO esta en el address list de mikrotik, lo agrega al address list
                            --> Tambien recorre el PPOE y lo hace lo mismo, si el cliente no tiene registro en el PPOE lo agrega
    ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $debug = (bool)$this->argument('debug');
        $routers = Router::all();
        foreach ($routers as $router) {
            RectifyClientsInRouterJob::dispatch($router, $debug);
        }
    }
}
