<?php

namespace App\Console\Commands\Active;

use App\Http\Traits\RouterConnection;
use App\Models\Client;
use App\Models\Router;
use App\Services\MikrotikService;
use Exception;
use Illuminate\Console\Command;
use PEAR2\Net\RouterOS\Client as RouterOSClient;

class UpdatePasswordAllClientsInMikrotik extends Command
{
    use RouterConnection;

    protected $signature = 'app:update-password-all-clients-in-mikrotik
                            {client_id? : Procesar solo un cliente específico}
                            {limit? : Número máximo de clientes a procesar}';

    protected $description = 'Actualiza la contraseña de los clientes en Mikrotik, con soporte para límite y cliente específico.';

    protected ?RouterOSClient $routerConnection = null;
    protected $router;

    public function handle()
    {
        $clientId = $this->argument('client_id');
        $limit    = $this->argument('limit');

        // Sanear valores
        $clientId = $clientId !== null ? (int) $clientId : null;
        $limit    = $limit !== null ? (int) $limit : null;

        if ($limit !== null && $limit <= 0) {
            $this->error("El límite debe ser mayor que 0.");
            return Command::FAILURE;
        }

        $this->initializeRouter();
        $this->getConnection();

        $this->updatePasswordAllClientsInMikrotik($clientId, $limit);

        $this->closeConnection();

        return Command::SUCCESS;
    }

    private function getConnection()
    {
        try {
            if (!$this->routerConnection) {
                $mikrotikService = new MikrotikService();
                $this->routerConnection = $mikrotikService->getConnection($this->router);

                $this->info("Connected to Mikrotik router: {$this->router->name}");
            }
            return $this->routerConnection;
        } catch (Exception $e) {
            $this->error("Connection failed: " . $e->getMessage());
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

                $this->info("Connection closed");
            }
        } catch (Exception $e) {
            $this->error("Error closing connection: " . $e->getMessage());
        }
    }

    private function initializeRouter(int $id = 2): bool
    {
        $router = Router::find($id);

        if (!$router) {
            $this->error("Router with ID {$id} not found");
            return false;
        }

        $this->router = $router;
        return true;
    }

    /**
     * Procesa todos o un cliente específico usando chunks.
     */
    private function updatePasswordAllClientsInMikrotik(?int $clientId = null, ?int $limit = null)
    {
        // Si se pasa un client_id → procesar solo ese cliente, saltar chunks
        if ($clientId !== null) {
            $client = Client::whereHas('internet_service')->find($clientId);

            if (!$client) {
                $this->error("Client ID {$clientId} not found or has no internet_service.");
                return;
            }

            foreach ($client->internet_service as $service) {
                $this->updatePasswordInRouter($service);
            }

            $this->info("Password updated for client ID: {$client->id}");
            return;
        }

        // Procesamiento normal por chunks
        $processed = 0;

        Client::whereHas('internet_service')
            ->orderBy('id')
            ->chunk(500, function ($clients) use (&$processed, $limit) {
                foreach ($clients as $client) {

                    // Si ya llegamos al límite → cortar chunking
                    if ($limit !== null && $processed >= $limit) {
                        return false;
                    }

                    foreach ($client->internet_service as $service) {
                        $this->updatePasswordInRouter($service);
                    }

                    $processed++;
                    $this->info("Password updated for client ID: {$client->id}");
                }
            });

        $this->info("Total processed: {$processed}");
    }
}
