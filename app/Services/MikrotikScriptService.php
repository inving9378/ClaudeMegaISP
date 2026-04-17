<?php

namespace App\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Traits\RouterConnection;
use App\Models\Router;
use Exception;
use Illuminate\Support\Facades\Log;
use PEAR2\Net\RouterOS\Client as RouterOSClient;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;

class MikrotikScriptService
{
    use RouterConnection;

    private const MEMORY_LIMIT = '8912M';
    private const TARGET_ROUTER_ID = 2;
    private const SCRIPT_NAME_SECRET = 'rectify_clients_script_secret';
    private const SCRIPT_NAME_ADDRESS_LIST = 'rectify_clients_script_address_list';
    private const BACKUP_DIR = 'script_client_mikrotik';
    private const MAX_SCRIPT_SIZE = 3000;

    protected Router $router;
    protected ?RouterOSClient $routerConnection;
    protected array $servicesToAddPPPSecret = [];
    protected array $servicesToRemovePPPSecret = [];
    protected array $servicesToAddAddressList = [];
    protected array $servicesToRemoveAddressList = [];

    protected $scriptContentSecret;
    protected $scriptContentAddressList;

    public function createScriptToRectifyClientsByRouterId(int $id)
    {
        if (!$this->initializeRouter($id)) {
            return null;
        }

        $this->setupEnvironment();
        $this->getConnection();
        $this->processAddressList();
        $this->processPPPSecrets();

        $this->createFileBackup();
        $this->uploadScriptToRouter($id);
    }


    private function getConnection()
    {
        $mikrotikService = new MikrotikService();
        $this->routerConnection = $mikrotikService->getConnection($this->router);
    }

    private function closeConnection()
    {
        $mikrotikService = new MikrotikService();
        $this->routerConnection = $mikrotikService->resetConnection();
    }



    private function initializeRouter(int $id): bool
    {
        $router = Router::find($id);

        if (!$router) {
            Log::warning("Router with ID {$id} not found");
            return false;
        }

        $this->router = $router;
        return true;
    }

    private function setupEnvironment(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', self::MEMORY_LIMIT);
    }

    private function processAddressList(): void
    {
        $repository = new ClientInternetServiceRepository();
        $inactiveServices = $repository->getServicesWhereClientNotActiveFilterByRouter($this->router);
        $serviceIps = $this->getServiceIpMap($inactiveServices);
        $mikrotikIps = $this->routerConnection ? $this->getAllIpsInAddressList($this->routerConnection) : [];

        $this->syncAddressList($serviceIps, $mikrotikIps, $inactiveServices);
    }

    private function getServiceIpMap($services): array
    {
        return $services->mapWithKeys(fn($service) => [
            $service->id => $service->network_ip_used_by->ip
        ])->all();
    }

    private function syncAddressList(array $serviceIps, array $mikrotikIps, $services): void
    {
        $this->addMissingAddresses($serviceIps, $mikrotikIps, $services);
        $this->removeExtraAddresses($serviceIps, $mikrotikIps, $services);
    }

    private function addMissingAddresses(array $serviceIps, array $mikrotikIps, $services): void
    {
        foreach (array_diff($serviceIps, $mikrotikIps) as $serviceId => $ip) {
            $this->processAddressAddition($serviceId, $ip, $services);
        }
    }

    private function processAddressAddition(int $serviceId, string $ip, $services): void
    {
        if ($service = $services->find($serviceId)) {
            $this->servicesToAddAddressList[] = $service;
        }
    }

    private function removeExtraAddresses(array $serviceIps, array $mikrotikIps, $services): void
    {
        $networkIpRepository = new NetworkIpRepository();
        $clientRepository = new ClientInternetServiceRepository();

        foreach (array_diff($mikrotikIps, $serviceIps) as $ip) {
            $this->processAddressRemoval(
                $ip,
                ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH,
                $networkIpRepository,
                $clientRepository,
                $services
            );
        }
    }

    private function processAddressRemoval(
        string $ip,
        string $addressList,
        NetworkIpRepository $networkIpRepo,
        ClientInternetServiceRepository $clientRepo,
        $services
    ): void {
        $idByIp = $this->getIdByIp($this->routerConnection, $addressList, $ip);

        if (!$idByIp) {
            return;
        }

        $serviceId = $networkIpRepo->getServiceIdByIp($ip);
        $service = $serviceId ? $clientRepo->getServiceFilterById($serviceId) : null;

        if ($service) {
            $this->servicesToRemoveAddressList[] = $service;
        }
    }

    private function processPPPSecrets(): void
    {
        $mikrotikIps = $this->routerConnection ? $this->getAllPppSecretsIps($this->routerConnection) : [];
        $repository = new ClientInternetServiceRepository();
        $services = $repository->getServicesWhereHasIp();
        $serviceIps = $this->getServiceIpMap($services);

        $this->syncPPPSecrets($serviceIps, $mikrotikIps, $services);
    }

    private function syncPPPSecrets(array $serviceIps, array $mikrotikIps, $services): void
    {
        $this->addMissingPPPSecrets($serviceIps, $mikrotikIps, $services);
        $this->removeExtraPPPSecrets($serviceIps, $mikrotikIps, $services);
    }

    private function addMissingPPPSecrets(array $serviceIps, array $mikrotikIps, $services): void
    {
        foreach (array_diff($serviceIps, $mikrotikIps) as $serviceId => $ip) {
            $this->processPPPAddition($serviceId, $ip, $services);
        }
    }

    private function processPPPAddition(int $serviceId, string $ip, $services): void
    {
        if ($service = $services->find($serviceId)) {
            $this->servicesToAddPPPSecret[] = $service;
        }
    }

    private function removeExtraPPPSecrets(array $serviceIps, array $mikrotikIps, $services): void
    {
        foreach (array_diff($mikrotikIps, $serviceIps) as $ip) {
            if ($service = $this->findServiceByIp($services, $ip)) {
                $this->servicesToRemovePPPSecret[] = $service;
            }
        }
    }

    private function findServiceByIp($services, string $ip)
    {
        return $services->firstWhere('network_ip_used_by.ip', $ip);
    }

    private function createFileBackup()
    {
        $this->generateScriptContent();
        $this->createScriptFileSecret();
        $this->createScriptFileAddressList();
    }

    private function generateScriptContent()
    {
        $this->scriptContentSecret = $this->generatePPPSecretsContent();
        $this->scriptContentAddressList = $this->generateAddressListContent();
    }

    private function generatePPPSecretsContent(): string
    {
        if (empty($this->servicesToAddPPPSecret)) {
            return '';
        }

        $content = "/ppp secret\n";

        foreach ($this->servicesToAddPPPSecret as $service) {
            $content .= sprintf(
                'add name="%s" password="%s" service=any profile=default remote-address=%s disabled=no comment="%s"' . PHP_EOL,
                $service->getNameClient(),
                $service->password,
                $service->network_ip_used_by->ip,
                'Meganet_' . $service->id
            );
        }

        return $content;
    }

    private function generateAddressListContent(): string
    {
        if (empty($this->servicesToAddAddressList)) {
            return '';
        }

        $content = "/ip firewall address-list\n";

        foreach ($this->servicesToAddAddressList as $service) {
            $content .= sprintf(
                'add list="MgNet_Morosos" address=%s disabled=no comment="%s"' . PHP_EOL,
                $service->network_ip_used_by->ip,
                'Meganet_' . $service->id
            );
        }

        return $content;
    }

    private function createScriptFileSecret(): string
    {
        if (empty($this->scriptContentSecret) || empty($this->router->id)) {
            throw new Exception("Content or router ID is missing");
        }

        //eliminar el ultimo espacio
        $content = rtrim($this->scriptContentSecret);

        $pathSecret = $this->getScriptFilePathSecret();

        if (!is_dir(dirname($pathSecret))) {
            mkdir(dirname($pathSecret), 0755, true);
        }

        if (file_put_contents($pathSecret, $content) === false) {
            throw new Exception("Failed to write script secret file");
        }

        return $pathSecret;
    }

    private function createScriptFileAddressList(): string
    {
        if (empty($this->scriptContentAddressList) || empty($this->router->id)) {
            throw new Exception("Content or router ID is missing");
        }

        //eliminar el ultimo espacio
        $content = rtrim($this->scriptContentAddressList);

        $pathAddressList = $this->getScriptFilePathAddressList();

        if (!is_dir(dirname($pathAddressList))) {
            mkdir(dirname($pathAddressList), 0755, true);
        }

        if (file_put_contents($pathAddressList, $content) === false) {
            throw new Exception("Failed to write script address list file");
        }

        return $pathAddressList;
    }

    private function getScriptFilePathSecret(): string
    {
        return storage_path(sprintf(
            '%s/%s/%d/script_secret.rsc',
            self::BACKUP_DIR,
            now()->format('Y-m-d'),
            $this->router->id
        ));
    }

    private function getScriptFilePathAddressList(): string
    {
        return storage_path(sprintf(
            '%s/%s/%d/script_address_list.rsc',
            self::BACKUP_DIR,
            now()->format('Y-m-d'),
            $this->router->id
        ));
    }


    ////////////////////////////////////

    public function uploadScriptToRouter($id)
    {
        if (!$this->initializeRouter($id)) {
            return null;
        }
        $this->getConnection();
        $this->uploadSecrets();
        $this->uploadAddressList();
    }

    private function uploadSecrets()
    {
        $filePath = $this->getScriptFilePathSecret();

        if (!file_exists($filePath)) {
            Log::error("Script file not found at path: {$filePath}");
            throw new Exception("Script file not found");
        }

        $content = file_get_contents($filePath);
        $lines = explode(PHP_EOL, $content);
        $chunks = array_chunk($lines, 100);

        $firstLine = $lines[0] ?? '';

        foreach ($chunks as $index => $chunk) {
            try {
                // Verificar y reestablecer conexión
                $this->getConnection();

                $scriptName = self::SCRIPT_NAME_SECRET . '_part' . ($index + 1);
                $scriptContent = implode(PHP_EOL, $chunk);

                if ($index > 0 && !empty($firstLine)) {
                    $scriptContent = "/ppp secret\n" . $scriptContent;
                }
                $this->uploadScriptToMikrotik($scriptName, $scriptContent);
                $this->runScriptOnMikrotik($scriptName);
                $this->removeScriptFromMikrotik($scriptName);

                Log::info("Successfully executed script part " . ($index + 1) . " of " . count($chunks));
            } catch (Exception $e) {
                Log::error("Error processing script part " . ($index + 1) . ": " . $e->getMessage());
                // Intenta reconectar antes de continuar
                $this->routerConnection = null;
                $this->getConnection();

                // Decide si quieres continuar o abortar
                throw $e;
            }
        }
    }
    private function uploadAddressList()
    {
        $filePath = $this->getScriptFilePathAddressList();

        if (!file_exists($filePath)) {
            Log::error("Script file not found at path: {$filePath}");
            throw new Exception("Script file not found");
        }

        $content = file_get_contents($filePath);
        $lines = explode(PHP_EOL, $content);
        $chunks = array_chunk($lines, 100);
        $firstLine = $lines[0] ?? '';
        foreach ($chunks as $index => $chunk) {
            try {
                // Verificar y reestablecer conexión
                $this->getConnection();
                $scriptName = self::SCRIPT_NAME_ADDRESS_LIST . '_part' . ($index + 1);
                $scriptContent = implode(PHP_EOL, $chunk);
                if ($index > 0 && !empty($firstLine)) {
                    $scriptContent = "/ip firewall address-list\n" . $scriptContent;
                }
                $this->uploadScriptToMikrotik($scriptName, $scriptContent);
                $this->runScriptOnMikrotik($scriptName);
                $this->removeScriptFromMikrotik($scriptName);

                Log::info("Successfully executed script part " . ($index + 1) . " of " . count($chunks));
            } catch (Exception $e) {
                Log::error("Error processing script part " . ($index + 1) . ": " . $e->getMessage());
                // Intenta reconectar antes de continuar
                $this->routerConnection = null;
                $this->getConnection();
                // Decide si quieres continuar o abortar
                throw $e;
            }
        }
    }

    private function uploadScriptToMikrotik(string $scriptName, string $scriptContent)
    {
        try {
            $setRequest = new Request('/system/script/add');
            $setRequest->setArgument('name', $scriptName)
                ->setArgument('source', $scriptContent);
            $response = $this->routerConnection->sendSync($setRequest);

            if ($response->getType() === Response::TYPE_ERROR) {
                throw new Exception("Failed to upload script: " . $response->getProperty('message'));
            }
        } catch (Exception $e) {
            Log::error("Error uploading script {$scriptName}: " . $e->getMessage());
            throw $e;
        }
    }

    private function runScriptOnMikrotik(string $scriptName)
    {
        try {
            // Primero obtener el ID del script usando su nombre
            $query = new Request('/system/script/print');
            $query->setArgument('.proplist', '.id');
            $query->setQuery(Query::where('name', $scriptName));
            $scriptList = $this->routerConnection->sendSync($query);

            if (count($scriptList) === 0) {
                throw new Exception("Script '{$scriptName}' not found on router");
            }

            $scriptId = $scriptList->getProperty('.id');

            // Ejecutar el script usando su ID
            $runRequest = new Request('/system/script/run');
            $runRequest->setArgument('number', $scriptId);
            $response = $this->routerConnection->sendSync($runRequest);

            if ($response->getType() === Response::TYPE_ERROR) {
                throw new Exception("Failed to run script: " . $response->getProperty('message'));
            }

            Log::info("Script {$scriptName} (ID: {$scriptId}) executed successfully");

            return $response; // Opcional: devolver la respuesta para análisis

        } catch (Exception $e) {
            Log::error("Error executing script {$scriptName}: " . $e->getMessage());
            throw $e;
        }
    }

    private function removeScriptFromMikrotik(string $scriptName)
    {
        try {
            // Primero necesitamos obtener el ID del script
            $runRequestQuery = new Request('/system/script/print');
            $runRequestQuery->setArgument('.proplist', '.id');
            $runRequestQuery->setQuery(Query::where('name', $scriptName));
            $scriptList = $this->routerConnection->sendSync($runRequestQuery);

            if (count($scriptList) === 0) {
                Log::warning("Script {$scriptName} not found for removal");
                return;
            }
            $scriptId = $scriptList->getProperty('.id');

            $runRequestDelete = new Request('/system/script/remove');
            $runRequestDelete->setArgument('numbers', $scriptId);
            $response = $this->routerConnection->sendSync($runRequestDelete);
            if ($response->getType() === Response::TYPE_ERROR) {
                throw new Exception("Failed to remove script: " . $response->getProperty('message'));
            }
            Log::info("Script {$scriptName} removed successfully");
        } catch (Exception $e) {
            Log::error("Error removing script {$scriptName}: " . $e->getMessage());
            throw $e;
        }
    }
}
