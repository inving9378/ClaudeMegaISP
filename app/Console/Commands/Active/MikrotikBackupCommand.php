<?php

namespace App\Console\Commands\Active;

use App\Http\Traits\RouterConnection;
use App\Models\Router;
use Illuminate\Console\Command;

class MikrotikBackupCommand extends Command
{
    use RouterConnection;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotikBackup:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hace una copia de seguridad de Mikrotik diariamente';

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
        $routers = Router::all();
        foreach ($routers as $router) {
            $connection = $this->getConnectionByRouter($router);
            if ($connection) {
                $scriptContent = $this->getAllPPPsercrets($connection);
                $scriptContent = $this->getAllAddressList($connection, $scriptContent);
                $this->createFile($scriptContent, $router);
            }
        }
    }

    public function getAllPPPsercrets($connection)
    {
        $allPPPsecrets = $connection ? $this->getAllEntriesInPPPSecret($connection) : [];
        $scriptContent = "/ppp secret\n";
        foreach ($allPPPsecrets as $key => $value) {
            // Validar que los campos necesarios no estén vacíos
            if (!empty($value['name']) && !empty($value['password']) && !empty($value['remote-address'])) {
                $scriptContent .= sprintf(
                    'add name="%s" password="%s" service=any profile=default remote-address=%s disabled=%s comment="%s"' . PHP_EOL,
                    $value['name'],
                    $value['password'],
                    $value['remote-address'],
                    $this->_isDisable($value['disabled']),
                    $value['comment']
                );
            }
        }
        return $scriptContent;
    }

    public function getAllAddressList($connection, $scriptContent)
    {
        $allAddressList = $connection ? $this->getAllEntriesInAddressList($connection) : [];
        $scriptContent .= "/ip firewall address-list\n";
        foreach ($allAddressList as $key => $value) {
            // Validar que los campos necesarios no estén vacíos
            if (!empty($value['address'])) {
                $scriptContent .= sprintf(
                    'add list="MgNet_Morosos" address=%s disabled=%s comment="%s"' . PHP_EOL,
                    $value['address'],
                    $this->_isDisable($value['disabled']),
                    $value['comment']
                );
            }
        }
        return $scriptContent;
    }


    public function createFile($scriptContent, $router)
    {
        // Define la ruta dentro de storage
        $directory = storage_path('script_client_mikrotik/' . $router->id);

        // Crea la carpeta si no existe
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true); // Permisos y creación de carpetas recursiva
        }

        // Define la ruta completa del archivo
        $filePath = $directory . '/script_dev.rsc';

        // Escribe el contenido en el archivo
        file_put_contents($filePath, $scriptContent);
        return $filePath;
    }

    public
    function _isDisable($disable)
    {
        $disable == 'false'
            ? $disable = 'no'
            : $disable = 'yes';
        return $disable;
    }
}
