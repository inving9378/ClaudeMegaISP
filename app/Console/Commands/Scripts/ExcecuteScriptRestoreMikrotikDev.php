<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\RouterRepository;
use App\Http\Traits\RouterConnection;
use Illuminate\Console\Command;

class ExcecuteScriptRestoreMikrotikDev extends Command
{
    use RouterConnection;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:excecute-script-restore-mikrotik-dev';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Este comando es para rellenar el mikrotik de prueba';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById(2);
        $connection = $this->getConnectionByRouter($router);
        if ($connection) {
            $this->executeScript($connection);
        }
    }

    public function executeScript($connection)
    {
        // Ejecutar el archivo en el Mikrotik
        $request = new \PEAR2\Net\RouterOS\Request('/import');
        $request->setArgument('file-name', 'script_dev.rsc');

        try {
            $connection->sendSync($request);
        } catch (\Exception $e) {
            throw new \Exception("No se pudo ejecutar el archivo en el Mikrotik: " . $e->getMessage());
        }
    }
}
