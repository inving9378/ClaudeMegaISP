<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\RouterRepository;
use App\Http\Traits\RouterConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateFilesToRectifyMikrotikCommand extends Command
{
    use RouterConnection;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-files-to-rectify-mikrotik';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un Archivo para subir para el mikrotik';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById(2);
        $connection = $this->getConnectionByRouter($router);
        if ($connection) {
            $scriptContent = $this->createOrDeleteIpInPPPSecret($connection);
            $scriptContent = $this->createOrDeleteInAddressList($connection, $scriptContent);
            $this->createFile($scriptContent);
            Log::info("Acrchivos de Rectificar Mikrotik agregado");
        }
    }

    public function createOrDeleteIpInPPPSecret($connection)
    {
        $ipsMikrotikSecrets = $connection ? $this->getAllPppSecretsIps($connection) : [];
        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $servicesWhereHasIp = $clientInternetServiceRepository->getServicesWhereHasIp();

        $arrayServiceIp = [];
        foreach ($servicesWhereHasIp as $service) {
            $arrayServiceIp[$service->id] = $service->network_ip->ip;
        }

        // Preparar listas para añadir o eliminar
        $arrayServicesToAddInMikrotik = array_diff($arrayServiceIp, $ipsMikrotikSecrets);
        $arrayServicesToDeleteMikrotik = array_diff($ipsMikrotikSecrets, $arrayServiceIp);

        // Crear el contenido del script para /ppp secret
        $scriptContent = "/ppp secret\n";
        foreach ($arrayServicesToAddInMikrotik as $serviceId => $ip) {
            $service = $servicesWhereHasIp->find($serviceId);
            if ($service) {
                $login = $service->getNameClient();
                $password = $service->password ?? 'Meganet-' . $service->id;
                $disabled = $service->client->clientGetStatus() ? 'yes' : 'no';

                $scriptContent .= sprintf(
                    'add name="%s" password="%s" service=any profile=default remote-address=%s disabled=%s comment="Meganet_%s"' . PHP_EOL,
                    $login,
                    $password,
                    $ip,
                    $disabled,
                    $service->id
                );
            }
        }

        foreach ($arrayServicesToDeleteMikrotik as $ip) {
            $scriptContent .= sprintf('remove [find remote-address=%s]' . PHP_EOL, $ip);
        }

        return $scriptContent;
    }

    public function createOrDeleteInAddressList($connection, $scriptContent)
    {
        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $servicesWhereClientNotActive = $clientInternetServiceRepository->getServicesWhereClientNotActive();

        $arrayServiceIp = [];
        foreach ($servicesWhereClientNotActive as $service) {
            $arrayServiceIp[$service->id] = $service->network_ip->ip;
        }

        $ipsMikrotikAddressList = $connection ? $this->getAllIpsInAddressList($connection) : [];

        // Comparar $arrayServiceIp y $ipsMikrotikAddressList
        $arrayServicesToAddInMikrotik = array_diff($arrayServiceIp, $ipsMikrotikAddressList);
        $arrayServicesToDeleteMikrotik = array_diff($ipsMikrotikAddressList, $arrayServiceIp);

        // Crear el contenido adicional para /ip firewall address-list
        $scriptContent .= "/ip firewall address-list\n";
        foreach ($arrayServicesToAddInMikrotik as $serviceId => $ip) {
            $service = $servicesWhereClientNotActive->find($serviceId);
            if ($service) {
                $clientName = $service->getNameClient();
                $comment = $clientName . '-' . $service->id;
                $scriptContent .= sprintf(
                    'add list="MgNet_Morosos" address=%s comment="%s"' . PHP_EOL,
                    $ip,
                    $comment
                );

                Log::info('Cliente que va para addess_list => ' . $service->client_id);
            }
        }

        foreach ($arrayServicesToDeleteMikrotik as $ip) {
            $scriptContent .= sprintf('remove [find list="MgNet_Morosos" address=%s]' . PHP_EOL, $ip);
        }

        return $scriptContent;
    }
    public function createFile($scriptContent)
    {
        // Define la ruta dentro de storage
        $directory = storage_path('script_client_mikrotik');

        // Crea la carpeta si no existe
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true); // Permisos y creación de carpetas recursiva
        }

        // Define la ruta completa del archivo
        $filePath = $directory . '/mikrotik_script.rsc';

        // Escribe el contenido en el archivo
        file_put_contents($filePath, $scriptContent);
        return $filePath;
    }


    public function uploadToMikrotik($localFilePath, $router)
    {
        $device_ip = $router->ip_host;
        $device_login = $router->mikrotik->login_api;
        $device_password = $router->mikrotik->password_api;
        if (config('app.env') !== "production") {
            $device_login = 'admin';
            $device_password = 'inving9378';
        }

        // Ruta remota en Mikrotik donde deseas almacenar el archivo
        $remoteFilePath = '/' . $localFilePath;

        $ftpConn = ftp_connect($device_ip, 2121);
        if (!$ftpConn) {
            throw new \Exception("No se pudo conectar al Mikrotik.");
        }

        // Autenticarse en el servidor FTP
        if (!ftp_login($ftpConn, $device_login, $device_password)) {
            ftp_close($ftpConn);
            throw new \Exception("No se pudo autenticar en el Mikrotik.");
        }

        // Habilitar el modo pasivo
        ftp_pasv($ftpConn, true);

        // Subir el archivo al Mikrotik
        if (!ftp_put($ftpConn, $remoteFilePath, $localFilePath, FTP_BINARY)) {
            ftp_close($ftpConn);
            throw new \Exception("No se pudo subir el archivo a Mikrotik.");
        }

        // Cerrar la conexión FTP
        ftp_close($ftpConn);

        return "Archivo subido correctamente a Mikrotik";
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


    public function _isDisable($disable)
    {
        $disable == 'Activado' || $disable == 'Nuevo' || $disable == 'Pendiente'
            ? $disable = 'yes'
            : $disable = 'no';
        return $disable;
    }
}
