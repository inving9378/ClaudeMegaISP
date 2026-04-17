<?php

namespace App\Console\Commands\Active;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Traits\RouterConnection;
use App\Models\Router;
use App\Services\MikrotikService;
use Exception;
use Illuminate\Console\Command;
use PEAR2\Net\RouterOS\Client as RouterOSClient;
use Illuminate\Support\Facades\Log;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;

class MikrotikSyncCommand extends Command
{
    use RouterConnection;

    protected $signature = 'app:mikrotik-sync-command';
    protected $description = 'Synchronize clients between application and Mikrotik (PPP Secrets and Address Lists)';

    private const MEMORY_LIMIT = '8912M';
    private const SCRIPT_NAME_SECRET = 'rectify_clients_script_secret';
    private const SCRIPT_NAME_ADDRESS_LIST = 'rectify_clients_script_address_list';
    private const BACKUP_DIR = 'script_client_mikrotik';
    private const LOG_FILE = 'logs/mikrotik-sync.log';
    private const ROUTER_TIMEOUT = 30; // Timeout en segundos para operaciones con el router

    protected $router;
    protected ?RouterOSClient $routerConnection;
    protected array $servicesToAddPPPSecret = [];
    protected array $servicesToRemovePPPSecret = [];
    protected array $servicesToAddAddressList = [];
    protected array $servicesToRemoveAddressList = [];
    protected array $executionSummary = [];
    protected bool $hasErrors = false;
    protected $scriptContentSecret;
    protected $scriptContentAddressList;
    protected $logFileHandle;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Inicializar logging
        $this->initLogging();

        try {
            $this->scriptContentSecret = null;
            $this->scriptContentAddressList = null;

            $this->hasErrors = false;
            $this->executionSummary = [];

            $this->servicesToAddPPPSecret = [];
            $this->servicesToRemovePPPSecret = [];
            $this->servicesToAddAddressList = [];
            $this->servicesToRemoveAddressList = [];

            $this->scriptContentSecret = null;
            $this->scriptContentAddressList = null;
            $this->routerConnection = null;
            $this->setupEnvironment();

            $routerId = 2;

            if (!$this->initializeRouter($routerId)) {
                $this->logError("Router with ID {$routerId} not found");
                $this->error("Router with ID {$routerId} not found");
                return 1;
            }

            $this->logInfo("Starting synchronization for router: {$this->router->name} (ID: {$this->router->id})");

            $this->getConnection();

            // Procesar Address List
            $this->processAddressList();

            // Procesar PPP Secrets
            $this->processPPPSecrets();

            // Crear backups de los scripts
            $this->createFileBackup();

            // Subir y ejecutar scripts en el router
            $this->uploadScriptToRouter($this->router->id);

            // Mostrar resumen
            $this->displayExecutionSummary();

            return $this->hasErrors ? 1 : 0;
        } catch (Exception $e) {
            $errorMsg = "Critical error: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            return 1;
        } finally {
            $this->closeConnection();
            $this->cleanupTemporaryScripts();
            $this->closeLogging();
        }
    }

    /**
     * Inicializar sistema de logging
     */
    private function initLogging(): void
    {
        try {
            $logPath = storage_path(self::LOG_FILE);
            $logDir = dirname($logPath);

            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $this->logFileHandle = fopen($logPath, 'a');
            $this->logInfo("=== Starting Mikrotik Sync Command ===");
        } catch (Exception $e) {
            // Fallback al log por defecto de Laravel si no podemos crear nuestro archivo
            Log::error("Failed to initialize custom logging: " . $e->getMessage());
        }
    }

    /**
     * Escribir mensaje informativo en el log
     */
    private function logInfo(string $message): void
    {
        $timestamp = now()->toDateTimeString();
        $logMessage = "[{$timestamp}] INFO: {$message}\n";

        if ($this->logFileHandle) {
            fwrite($this->logFileHandle, $logMessage);
        }

        Log::info($message);
    }

    /**
     * Escribir error en el log
     */
    private function logError(string $message): void
    {
        $timestamp = now()->toDateTimeString();
        $logMessage = "[{$timestamp}] ERROR: {$message}\n";

        if ($this->logFileHandle) {
            fwrite($this->logFileHandle, $logMessage);
        }

        Log::error($message);
        $this->hasErrors = true;
    }

    /**
     * Cerrar recursos de logging
     */
    private function closeLogging(): void
    {
        if ($this->logFileHandle) {
            fclose($this->logFileHandle);
            $this->logFileHandle = null;
        }
    }

    private function getConnection()
    {
        try {
            if (!$this->routerConnection) {
                $mikrotikService = new MikrotikService();
                $this->routerConnection = $mikrotikService->getConnection($this->router);

                $this->logInfo("Connected to Mikrotik router: {$this->router->name}");
                $this->info("Connected to Mikrotik router: {$this->router->name}");
            }
            return $this->routerConnection;
        } catch (Exception $e) {
            $errorMsg = "Connection failed: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            throw $e;
        }
    }

    private function closeConnection()
    {
        try {
            if ($this->routerConnection) {
                $mikrotikService = new MikrotikService();
                $mikrotikService->resetConnection();
                $this->routerConnection = null;
                $this->logInfo("Connection closed");
                $this->info("Connection closed");
            }
        } catch (Exception $e) {
            $errorMsg = "Error closing connection: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
        }
    }

    /**
     * Limpiar scripts temporales en el router en caso de que algún proceso falle
     */
    private function cleanupTemporaryScripts(): void
    {
        try {
            if (!$this->routerConnection) {
                $this->getConnection();
            }

            if ($this->routerConnection) {
                // Limpiar scripts de PPP Secrets
                $this->cleanupScriptsByPattern(self::SCRIPT_NAME_SECRET);

                // Limpiar scripts de Address List
                $this->cleanupScriptsByPattern(self::SCRIPT_NAME_ADDRESS_LIST);

                $this->logInfo("Temporary scripts cleanup completed");
            }
        } catch (Exception $e) {
            $errorMsg = "Error during scripts cleanup: " . $e->getMessage();
            $this->logError($errorMsg);
            // No relanzamos la excepción para no enmascarar errores principales
        }
    }

    /**
     * Eliminar scripts del router que coincidan con un patrón de nombre
     */
    private function cleanupScriptsByPattern(string $pattern): void
    {
        try {
            $query = new Request('/system/script/print');
            $query->setQuery(Query::where('name', $pattern));
            $scripts = $this->routerConnection->sendSync($query);

            foreach ($scripts as $script) {
                $scriptId = $script->getProperty('.id');
                $scriptName = $script->getProperty('name');

                $deleteRequest = new Request('/system/script/remove');
                $deleteRequest->setArgument('numbers', $scriptId);
                $response = $this->routerConnection->sendSync($deleteRequest);

                if ($response->getType() !== Response::TYPE_ERROR) {
                    $this->logInfo("Cleaned up temporary script: {$scriptName}");
                }
            }
        } catch (Exception $e) {
            $this->logError("Failed to cleanup scripts with pattern {$pattern}: " . $e->getMessage());
        }
    }

    private function initializeRouter(int $id): bool
    {
        $router = Router::find($id);

        if (!$router) {
            $this->logError("Router with ID {$id} not found");
            return false;
        }

        $this->router = $router;
        return true;
    }

    private function setupEnvironment(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', self::MEMORY_LIMIT);
        $this->executionSummary = [
            'ppp_secrets_added' => 0,
            'ppp_secrets_removed' => 0,
            'address_list_added' => 0,
            'address_list_removed' => 0,
            'errors' => []
        ];
    }

    private function processAddressList(): void
    {
        try {
            $this->logInfo("Processing Address List synchronization...");
            $this->info("Processing Address List synchronization...");

            $repository = new ClientInternetServiceRepository();
            $inactiveServices = $repository->getServicesWhereClientNotActiveFilterByRouter($this->router);
            $serviceIps = $this->getServiceIpMap($inactiveServices);

            $this->getConnection();
            $mikrotikIps = $this->routerConnection ? $this->getAllIpsInAddressList($this->routerConnection) : [];

            $this->syncAddressList($serviceIps, $mikrotikIps, $inactiveServices);

            $infoMsg = sprintf(
                "Address List changes: %d to add, %d to remove",
                count($this->servicesToAddAddressList),
                count($this->servicesToRemoveAddressList)
            );

            $this->logInfo($infoMsg);
            $this->info($infoMsg);
        } catch (Exception $e) {
            $errorMsg = "Error processing Address List: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            $this->executionSummary['errors'][] = "Address List: " . $e->getMessage();
            $this->hasErrors = true;
        }
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
            $this->executionSummary['address_list_added']++;
        }
    }

    private function removeExtraAddresses(array $serviceIps, array $mikrotikIps, $services): void
    {
        $networkIpRepository = new NetworkIpRepository();
        $clientRepository = new ClientInternetServiceRepository();

        foreach (array_diff($mikrotikIps, $serviceIps) as $serviceId => $ip) {
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
        try {
            $idByIp = $this->getIdByIp($this->routerConnection, $addressList, $ip);

            if (!$idByIp) {
                return;
            }

            $serviceId = $networkIpRepo->getServiceIdByIp($ip);
            $service = $serviceId ? $clientRepo->getServiceFilterById($serviceId) : null;

            if ($service) {
                $this->servicesToRemoveAddressList[] = $service;
                $this->executionSummary['address_list_removed']++;
            }
        } catch (Exception $e) {
            $errorMsg = "Error processing address removal for IP {$ip}: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            $this->executionSummary['errors'][] = "Address removal for IP {$ip}: " . $e->getMessage();
            $this->hasErrors = true;
        }
    }

    private function processPPPSecrets(): void
    {
        try {
            $this->logInfo("Processing PPP Secrets synchronization...");
            $this->info("Processing PPP Secrets synchronization...");

            $this->getConnection();
            $mikrotikIps = $this->routerConnection ? $this->getAllPppSecretsIps($this->routerConnection) : [];

            $repository = new ClientInternetServiceRepository();
            $services = $repository->getServicesWhereHasIp();
            $serviceIps = $this->getServiceIpMap($services);

            $this->syncPPPSecrets($serviceIps, $mikrotikIps, $services);

            $infoMsg = sprintf(
                "PPP Secrets changes: %d to add, %d to remove",
                count($this->servicesToAddPPPSecret),
                count($this->servicesToRemovePPPSecret)
            );

            $this->logInfo($infoMsg);
            $this->info($infoMsg);
        } catch (Exception $e) {
            $errorMsg = "Error processing PPP Secrets: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            $this->executionSummary['errors'][] = "PPP Secrets: " . $e->getMessage();
            $this->hasErrors = true;
        }
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
            $this->executionSummary['ppp_secrets_added']++;
        }
    }

    private function removeExtraPPPSecrets(array $serviceIps, array $mikrotikIps, $services): void
    {
        foreach (array_diff($mikrotikIps, $serviceIps) as $ip) {
            if ($service = $this->findServiceByIp($services, $ip)) {
                $this->servicesToRemovePPPSecret[] = $service;
                $this->executionSummary['ppp_secrets_removed']++;
            }
        }
    }

    private function findServiceByIp($services, string $ip)
    {
        return $services->firstWhere('network_ip_used_by.ip', $ip);
    }

    private function createFileBackup()
    {
        try {
            $this->logInfo("Generating script files...");
            $this->info("Generating script files...");

            $this->generateScriptContent();
            $this->createScriptFileSecret();
            $this->createScriptFileAddressList();

            $this->logInfo("Script files generated successfully");
            $this->info("Script files generated successfully");
        } catch (Exception $e) {
            $errorMsg = "Error creating script files: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            $this->executionSummary['errors'][] = "Script files: " . $e->getMessage();
            $this->hasErrors = true;
            throw $e;
        }
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
            // Eliminar el archivo si existe para que no ejecute este script
            $pathSecret = $this->getScriptFilePathSecret();
            if (file_exists($pathSecret)) {
                unlink($pathSecret);
            }
            return '';
        }

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
            // Eliminar el archivo si existe para que no ejecute este script
            $pathSecret = $this->getScriptFilePathAddressList();
            if (file_exists($pathSecret)) {
                unlink($pathSecret);
            }
            return '';
        }

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

    public function uploadScriptToRouter($id)
    {
        $this->logInfo("Uploading and executing scripts on router...");
        $this->info("Uploading and executing scripts on router...");

        try {
            if (!$this->initializeRouter($id)) {
                throw new Exception("Router initialization failed");
            }

            $this->getConnection();

            // Procesar PPP Secrets
            if (!empty($this->scriptContentSecret)) {
                $this->uploadSecrets();
            } else {
                $this->logInfo("No PPP Secrets changes to upload");
                $this->info("No PPP Secrets changes to upload");
            }

            // Procesar Address List
            if (!empty($this->scriptContentAddressList)) {
                $this->uploadAddressList();
            } else {
                $this->logInfo("No Address List changes to upload");
                $this->info("No Address List changes to upload");
            }
        } catch (Exception $e) {
            $errorMsg = "Error uploading scripts: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            $this->executionSummary['errors'][] = "Script upload: " . $e->getMessage();
            $this->hasErrors = true;
        }
    }

    private function uploadSecrets()
    {
        $filePath = $this->getScriptFilePathSecret();

        if (!file_exists($filePath)) {
            $errorMsg = "Script file not found at path: {$filePath}";
            $this->logError($errorMsg);
            $this->error($errorMsg);
            throw new Exception("Script file not found");
        }

        $content = file_get_contents($filePath);
        $lines = explode(PHP_EOL, $content);
        $chunks = array_chunk($lines, 100);

        $firstLine = $lines[0] ?? '';

        foreach ($chunks as $index => $chunk) {
            try {
                $this->getConnection(); // Reconnect if needed

                $scriptName = self::SCRIPT_NAME_SECRET . '_part' . ($index + 1);
                $scriptContent = implode(PHP_EOL, $chunk);

                if ($index > 0 && !empty($firstLine)) {
                    $scriptContent = "/ppp secret\n" . $scriptContent;
                }

                $logMsg = "Uploading PPP Secrets part " . ($index + 1) . " of " . count($chunks);
                $this->logInfo($logMsg);
                $this->info($logMsg);

                $this->uploadScriptToMikrotik($scriptName, $scriptContent);
                $this->runScriptOnMikrotik($scriptName);
                $this->removeScriptFromMikrotik($scriptName);

                $successMsg = "Successfully executed PPP Secrets part " . ($index + 1);
                $this->logInfo($successMsg);
                $this->info($successMsg);
            } catch (Exception $e) {
                $errorMsg = "Error processing PPP Secrets part " . ($index + 1) . ": " . $e->getMessage();
                $this->logError($errorMsg);
                $this->error($errorMsg);
                $this->executionSummary['errors'][] = "PPP Secrets part " . ($index + 1) . ": " . $e->getMessage();
                $this->hasErrors = true;

                // Intenta reconectar antes de continuar
                $this->routerConnection = null;
                sleep(2); // Pequeña pausa antes de reintentar
                continue; // Continúa con el siguiente chunk
            }
        }
    }

    private function uploadAddressList()
    {
        $filePath = $this->getScriptFilePathAddressList();

        if (!file_exists($filePath)) {
            $errorMsg = "Script file not found at path: {$filePath}";
            $this->logError($errorMsg);
            $this->error($errorMsg);
            throw new Exception("Script file not found");
        }

        $content = file_get_contents($filePath);
        $lines = explode(PHP_EOL, $content);
        $chunks = array_chunk($lines, 100);
        $firstLine = $lines[0] ?? '';

        foreach ($chunks as $index => $chunk) {
            try {
                $this->getConnection(); // Reconnect if needed

                $scriptName = self::SCRIPT_NAME_ADDRESS_LIST . '_part' . ($index + 1);
                $scriptContent = implode(PHP_EOL, $chunk);

                if ($index > 0 && !empty($firstLine)) {
                    $scriptContent = "/ip firewall address-list\n" . $scriptContent;
                }

                $logMsg = "Uploading Address List part " . ($index + 1) . " of " . count($chunks);
                $this->logInfo($logMsg);
                $this->info($logMsg);

                $this->uploadScriptToMikrotik($scriptName, $scriptContent);
                $this->runScriptOnMikrotik($scriptName);
                $this->removeScriptFromMikrotik($scriptName);

                $successMsg = "Successfully executed Address List part " . ($index + 1);
                $this->logInfo($successMsg);
                $this->info($successMsg);
            } catch (Exception $e) {
                $errorMsg = "Error processing Address List part " . ($index + 1) . ": " . $e->getMessage();
                $this->logError($errorMsg);
                $this->error($errorMsg);
                $this->executionSummary['errors'][] = "Address List part " . ($index + 1) . ": " . $e->getMessage();
                $this->hasErrors = true;

                // Intenta reconectar antes de continuar
                $this->routerConnection = null;
                sleep(2); // Pequeña pausa antes de reintentar
                continue; // Continúa con el siguiente chunk
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

            $logMsg = "Script {$scriptName} uploaded successfully";
            $this->logInfo($logMsg);
            $this->info($logMsg);
        } catch (Exception $e) {
            $errorMsg = "Error uploading script {$scriptName}: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            throw $e;
        }
    }

    private function runScriptOnMikrotik(string $scriptName)
    {
        try {
            // Obtener el ID del script
            $query = new Request('/system/script/print');
            $query->setArgument('.proplist', '.id');
            $query->setQuery(Query::where('name', $scriptName));
            $scriptList = $this->routerConnection->sendSync($query);

            if (count($scriptList) === 0) {
                throw new Exception("Script '{$scriptName}' not found on router");
            }

            $scriptId = $scriptList->getProperty('.id');

            // Ejecutar el script
            $runRequest = new Request('/system/script/run');
            $runRequest->setArgument('number', $scriptId);
            $response = $this->routerConnection->sendSync($runRequest);

            if ($response->getType() === Response::TYPE_ERROR) {
                throw new Exception("Failed to run script: " . $response->getProperty('message'));
            }

            $logMsg = "Script {$scriptName} (ID: {$scriptId}) executed successfully";
            $this->logInfo($logMsg);
            $this->info($logMsg);
        } catch (Exception $e) {
            $errorMsg = "Error executing script {$scriptName}: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            throw $e;
        }
    }

    private function removeScriptFromMikrotik(string $scriptName)
    {
        try {
            // Obtener el ID del script
            $runRequestQuery = new Request('/system/script/print');
            $runRequestQuery->setArgument('.proplist', '.id');
            $runRequestQuery->setQuery(Query::where('name', $scriptName));
            $scriptList = $this->routerConnection->sendSync($runRequestQuery);

            if (count($scriptList) === 0) {
                $logMsg = "Script {$scriptName} not found for removal";
                $this->logInfo($logMsg);
                $this->info($logMsg);
                return;
            }

            $scriptId = $scriptList->getProperty('.id');

            $runRequestDelete = new Request('/system/script/remove');
            $runRequestDelete->setArgument('numbers', $scriptId);
            $response = $this->routerConnection->sendSync($runRequestDelete);

            if ($response->getType() === Response::TYPE_ERROR) {
                throw new Exception("Failed to remove script: " . $response->getProperty('message'));
            }

            $logMsg = "Script {$scriptName} removed successfully";
            $this->logInfo($logMsg);
            $this->info($logMsg);
        } catch (Exception $e) {
            $errorMsg = "Error removing script {$scriptName}: " . $e->getMessage();
            $this->logError($errorMsg);
            $this->error($errorMsg);
            throw $e;
        }
    }

    private function displayExecutionSummary()
    {
        $summary = "\n=== Execution Summary ===";
        $summary .= "\nPPP Secrets added: " . $this->executionSummary['ppp_secrets_added'];
        $summary .= "\nPPP Secrets removed: " . $this->executionSummary['ppp_secrets_removed'];
        $summary .= "\nAddress List added: " . $this->executionSummary['address_list_added'];
        $summary .= "\nAddress List removed: " . $this->executionSummary['address_list_removed'];

        if (!empty($this->executionSummary['errors'])) {
            $summary .= "\n\nErrors encountered:";
            foreach ($this->executionSummary['errors'] as $error) {
                $summary .= "\n- " . $error;
            }
            $summary .= "\n\nSynchronization completed with errors";

            $this->logError($summary);
            $this->error($summary);
        } else {
            $summary .= "\n\nSynchronization completed successfully";
            $this->logInfo($summary);
            $this->info($summary);
        }
    }
}
