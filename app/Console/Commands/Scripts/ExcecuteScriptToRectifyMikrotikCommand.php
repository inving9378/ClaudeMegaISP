<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\RouterRepository;
use App\Http\Traits\RouterConnection;
use Exception;
use Illuminate\Console\Command;

class ExcecuteScriptToRectifyMikrotikCommand extends Command
{
    use RouterConnection;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:excecute-script-to-rectify-mikrotik';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'restaurar sistema y actualizar mikrotik de manera Rapida';

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
        $request->setArgument('file-name', 'mikrotik_script.rsc');

        try {
            $connection->sendSync($request);
            echo "Archivo ejecutado correctamente.";
        } catch (\Exception $e) {
            throw new \Exception("No se pudo ejecutar el archivo en el Mikrotik: " . $e->getMessage());
        }
    }
}
