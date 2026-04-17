<?php

namespace App\Jobs;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\NetworkIpRepository;
use App\Models\Router;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use App\Services\LogService;
use Illuminate\Support\Facades\Log;

class RectifyClientsInRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    private const MEMORY_LIMIT = '8912M';
    private const TARGET_ROUTER_ID = 2;

    protected Router $router;
    protected bool $debug;
    protected $routerConnection;

    public function __construct(Router $router, bool $debug = false)
    {
        $this->router = $router;
        $this->debug = $debug;
    }

    public function handle(): void
    {
        if (!$this->shouldProcessRouter()) {
            return;
        }

        $this->setupEnvironment();
        $this->routerConnection = $this->getConnectionByRouter($this->router);

        $this->processAddressList();
        $this->processPPPSecrets();
    }

    private function shouldProcessRouter(): bool
    {
        return $this->router && $this->router->id === self::TARGET_ROUTER_ID;
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
        Log::info("Terminado de sincronizar clientes en el address list del router");
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
        $this->removeExtraAddresses($serviceIps, $mikrotikIps);
    }

    private function addMissingAddresses(array $serviceIps, array $mikrotikIps, $services): void
    {
        $ipsToAdd = array_diff($serviceIps, $mikrotikIps);

        foreach ($ipsToAdd as $serviceId => $ip) {
            $this->processAddressAddition($serviceId, $ip, $services);
        }
    }

    private function processAddressAddition(int $serviceId, string $ip, $services): void
    {
        $service = $services->find($serviceId);
        if (!$service) return;

        $clientName = $service->getNameClient();
        $addressList = ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH;
        $idByIp = $this->getIdByIp($this->routerConnection, $addressList, $ip);

        if ($idByIp) return;

        if ($this->debug) {
            dump('Add ' . $service->id . ' for client # ' . $service->client_id . ' to address list, ip: ' . $ip);
            return;
        }

        $this->addItem($this->routerConnection, $addressList, [
            'list' => 'MgNet_Morosos',
            'address' => $ip,
            'comment' => $clientName . '-' . $service->id
        ]);

        $service->service_in_address_list()->updateOrCreate(['deployed' => true]);
        $this->logServiceAction($service, 'agregado');
    }

    private function removeExtraAddresses(array $serviceIps, array $mikrotikIps): void
    {
        $ipsToRemove = array_diff($mikrotikIps, $serviceIps);
        $addressList = ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH;
        $networkIpRepository = new NetworkIpRepository();
        $clientRepository = new ClientInternetServiceRepository();

        foreach ($ipsToRemove as $ip) {
            $this->processAddressRemoval($ip, $addressList, $networkIpRepository, $clientRepository);
        }
    }

    private function processAddressRemoval(string $ip, string $addressList, $networkIpRepo, $clientRepo): void
    {
        $idByIp = $this->getIdByIp($this->routerConnection, $addressList, $ip);

        if (!$idByIp) return;

        if ($this->debug) {
            $serviceId = $networkIpRepo->getServiceIdByIp($ip);
            $service = $serviceId ? $clientRepo->getServiceFilterById($serviceId) : null;
            if ($service) {
                dump('Remove ' . $service->id . ' for client # ' . $service->client_id . ' from address list, ip: ' . $ip);
            }
            return;
        }

        $this->removeById($this->routerConnection, $addressList, $idByIp);
        $this->cleanUpServiceRecord($ip, $networkIpRepo, $clientRepo);
    }

    private function cleanUpServiceRecord(string $ip, $networkIpRepo, $clientRepo): void
    {
        $serviceId = $networkIpRepo->getServiceIdByIp($ip);
        if (!$serviceId) return;

        $service = $clientRepo->getServiceFilterById($serviceId);
        if (!$service) return;

        $service->service_in_address_list()->delete();
        $this->logServiceAction($service, 'removido');
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
        $this->removeExtraPPPSecrets($serviceIps, $mikrotikIps);
    }

    private function addMissingPPPSecrets(array $serviceIps, array $mikrotikIps, $services): void
    {
        $ipsToAdd = array_diff($serviceIps, $mikrotikIps);

        foreach ($ipsToAdd as $serviceId => $ip) {
            $this->processPPPAddition($serviceId, $ip, $services);
        }
    }

    private function processPPPAddition(int $serviceId, string $ip, $services): void
    {
        $service = $services->find($serviceId);
        if (!$service || !$this->routerConnection) return;

        $login = $service->getNameClient();
        $password = $service->password;
        $comment = 'Meganet_' . $service->id;

        $idByIpAndName = $this->getIdByIpAndNameSecretInMikrotik($service);

        if ($idByIpAndName) {
            $this->updatePPPCredentialsIfNeeded($idByIpAndName, $password, $login, $ip, $service);
            return;
        }

        if ($this->debug) {
            dump('Add ppp secret for client # ' . $service->client_id . ', ip: ' . $ip);
            return;
        }

        $this->addItem($this->routerConnection, '/ppp secret ', [
            'name' => $login,
            'password' => $password,
            'service' => 'any',
            'profile' => 'default',
            'remote-address' => $ip,
            'disabled' => 'no',
            'comment' => $comment
        ]);
    }

    private function updatePPPCredentialsIfNeeded(string $id, string $newPassword, string $login, string $ip, $service): void
    {
        $currentPassword = $this->getPasswordById($this->routerConnection, '/ppp/secret/', $id);

        if ($currentPassword && $currentPassword !== $newPassword) {
            $this->setvalueArrayById($this->routerConnection, '/ppp/secret/', $id, [
                'password' => $newPassword,
                'name' => $login
            ]);
            Log::info("Actualizada contraseña en el Mikrotik del ip $ip cliente: {$service->client_id}");
        }
    }

    private function removeExtraPPPSecrets(array $serviceIps, array $mikrotikIps): void
    {
        $ipsToRemove = array_diff($mikrotikIps, $serviceIps);

        foreach ($ipsToRemove as $ip) {
            $this->processPPPRemoval($ip);
        }
    }

    private function processPPPRemoval(string $ip): void
    {
        if (!$this->routerConnection) return;

        $idByIp = $this->getIdByIpSecrets($this->routerConnection, '/ppp/secret/', $ip);
        if (!$idByIp) return;

        if ($this->debug) {
            dump('Delete ppp secret, ip: ' . $ip);
            return;
        }

        $this->removeById($this->routerConnection, '/ppp secret ', $idByIp);
    }

    private function logServiceAction($service, string $action): void
    {
        $client = $service->client;
        $message = "Su servicio {$service->service_name} fue $action del address_list desde el RectifyClientsInRouterJob debido a que " .
            ($action === 'agregado' ? 'NO esta activo' : 'ESTA activo');

        (new LogService())->log($client, $message);
    }
}
