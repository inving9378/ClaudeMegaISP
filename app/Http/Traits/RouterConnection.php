<?php

namespace App\Http\Traits;

use App\Http\Controllers\Module\Network\Ipv4CalculatorController;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Repository\RouterRepository;
use App\Jobs\CreateClientRouterJob;
use App\Jobs\CreateClientWithServiceJob;
use App\Jobs\DeletedClientInRouterJob;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Jobs\UpdateClientRouterJob;
use App\Models\Mikrotik;
use App\Models\Network;
use App\Models\Router;
use App\Services\NetworkIpService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Util;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\SocketException;

trait RouterConnection
{
    public function initConnection($mikrotik, $device_ip)
    {
        $device_login = $mikrotik->login_api;
        $device_password = $mikrotik->password_api;
        $device_port = $mikrotik->port_api;
        return $this->connection(
            $device_ip,
            $device_login,
            $device_password,
            $device_port
        );
    }

    public function getConnectionByRouter($router)
    {
        $device_ip = $router->ip_host;
        $device_login = $router->mikrotik->login_api;
        $device_password = $router->mikrotik->password_api;
        $device_port = $router->mikrotik->port_api;
        return $this->connection(
            $device_ip,
            $device_login,
            $device_password,
            $device_port
        );
    }

    public function changeRouter($service, $plan_input)
    {
        return $service->router_id != $plan_input['router_id'];
    }
    public function changeIpv4($service, $plan_input)
    {
        return ($service->ipv4 != $plan_input['ipv4'] && $service->ipv4 != null) || ($service->ipv4_assignment != $plan_input['ipv4_assignment'] && $service->ipv4_assignment != null || $service->ipv4_pool != $plan_input['ipv4_pool'] && $service->ipv4_pool != null && $service->ipv4_assignment == ComunConstantsController::IPV4_ASSIGNMENT_POOL_IP);
    }

    public function changePassword($service, $plan_input)
    {
        return $service->password != $plan_input['password'] && $service->password != null;
    }

    public function validateIfNameExistInMikrotik($routerId, $nameClient, $ipv4Assignment, $input)
    {
        $filedUser = $nameClient;
        $filedRouter = $routerId;
        $routerId = $input[$routerId] ?? $routerId;
        $nameClient = $input[$nameClient] ?? $nameClient;
        $ipv4Assignment = $input[$ipv4Assignment] ?? $ipv4Assignment;
        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById($routerId);
        if ($router) {
            $connected = $this->getConnectionByRouter($router);
            $idByNameHostpot = null;
            $idByNameInSecret = null;
            if (!$connected) {
                return throw ValidationException::withMessages([$filedRouter => ['No Existe Conexion con el mikrotik']]);
            }
            $idByNameInSecret = $this->getIdByName($connected, '/ppp/secret/', $nameClient);
            if ($connected && $ipv4Assignment == ComunConstantsController::IPV4_ASSIGNMENT_POOL_IP) {
                $idByNameHostpot = $this->getIdByName($connected, '/ip/hotspot/user/', $nameClient);
            }
            if ($idByNameHostpot || $idByNameInSecret) {
                return throw ValidationException::withMessages([$filedUser => ['Este Nombre de Usuario No esta disponible']]);
            }
        }
    }

    public function updateIpClientAndService($service, $plan_input)
    {
        if ($service->internet_id) {
            try {
                DeletedClientInRouterJob::dispatch($service);
                $this->liberaLaIpUsada($service);
                $service->update($plan_input);
                $updatedService = $service->fresh();
                SetIPToClientInternetServiceJob::dispatch($updatedService);
                CreateClientWithServiceJob::dispatch($updatedService);
                return $updatedService;
            } catch (\Exception $exception) {
                Log::info($exception);
                return $service;
            }
        }
    }

    public function updateIpv4Service($service, $plan_input)
    {
        $service->update($plan_input);
        $updatedService = $service->fresh();
        $this->liberaLaIpUsada($service);
        SetIPToClientInternetServiceJob::dispatch($updatedService);
        CreateClientWithServiceJob::dispatch($updatedService);
        return $updatedService;
    }

    public function updateUserNameInRouter($service)
    {
        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById($service->router_id);
        if ($router) {
            $device_ip = $router->ip_host;
            $device_login = $router->mikrotik->login_api;
            $device_password = $router->mikrotik->password_api;
            $device_port = $router->mikrotik->port_api;
            $connected = $this->connection(
                $device_ip,
                $device_login,
                $device_password,
                $device_port
            );

            if ($connected) {
                $idByIpAndName = $this->getIdByIpAndNameSecretInMikrotik($service);
                if ($idByIpAndName) {
                    $this->setvalueArrayById(
                        $connected,
                        '/ppp/secret/',
                        $idByIpAndName,
                        [
                            'name' => $service->client_name,
                        ]
                    );
                }
            }
        }
    }

    public function updatePasswordInRouter($service)
    {
        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById($service->router_id);
        if ($router) {
            $connected = $this->getConnectionByRouter($router);
            if ($connected) {
                $idByIpAndName = $this->getIdByIpAndNameSecretInMikrotik($service);
                if ($idByIpAndName) {
                    $this->setvalueArrayById(
                        $connected,
                        '/ppp/secret/',
                        $idByIpAndName,
                        [
                            'password' => $service->password,
                        ]
                    );
                }
            }
        }
    }


    public function liberaLaIpUsada($clientInternetService)
    {
        $networkIpRepository = new NetworkIpRepository();
        $networkIp = $networkIpRepository->getNetworkIpByClientInternetServiceId($clientInternetService->id);
        if ($networkIp) {
            $networkIpRepository->removeUsedIp($networkIp);
        }
    }

    /**
     * Connect to mikrotik device
     * this function must be included in all other functions
     * @param $device_ip
     * @param $device_login
     * @param $device_password
     * @param $device_port
     * @return Client
     */
    protected function connection(
        $device_ip,
        $device_login,
        $device_password,
        $device_port
    ) {
        if (config('app.env') !== "production") {
            $device_ip = $_ENV['MIKROTIK_DEV_IP'] ?? '89.0.142.86';
            $device_login = $_ENV['MIKROTIK_DEV_LOGIN'] ?? 'admin';
            $device_password = $_ENV['MIKROTIK_DEV_PASSWORD'] ?? 'admin';
            $device_port = $_ENV['MIKROTIK_DEV_PORT'] ?? "8735";
        }
        try {
            $client = new Client(
                $device_ip,
                $device_login,
                $device_password,
                $device_port
            );
        } catch (SocketException $e) {
            Log::error(
                'Error al conectar con el enrutador MikroTik: ' .
                    $e->getMessage()
            );
            return null;
        }
        return $client;
    }

    /**
     * @param $client * Connection to mikrotik device
     * @param $dst_Address * Destination host
     * @return mixed * Object
     */
    protected function ping($client, $dst_Address)
    {
        $pingRequest = new Request('/ping count=3');
        $results = $client->sendSync(
            $pingRequest->setArgument('address', $dst_Address)
        );
        return $results;
    }

    /**
     * get version of mikrotik device
     * @param $client * Connection to mikrotik device
     * @return mixed * Object
     */
    protected function getVersion($client)
    {
        $responses = $client->sendSync(new Request('/system/resource/print'));

        foreach ($responses as $response) {
            if ($response->getType() === Response::TYPE_DATA) {
                return collect($response);
            }
            return null;
        }
    }

    public function enableProxy($connected, $enable, $port)
    {
        $addRequest = new Request('/ip proxy ' . 'set');
        $addRequest->setArgument('enabled', $enable);
        $addRequest->setArgument('port', $port);
        $addRequest->setArgument('max-fresh-time', '00:00:10');
        if (
            $connected->sendSync($addRequest)->getType() !==
            Response::TYPE_FINAL
        ) {
            return false;
        }
        return true;
    }

    /**
     * Get list from address list
     * @param $client * Connection to mikrotik device
     * @return array
     */
    protected function getAddressList($client)
    {
        $responses = $client->sendSync(
            new Request(ComunConstantsController::IP_FIREWALL_ADDRESS_LIST_WHIT_SLASH . 'print')
        );
        $data = [];
        $count = 0;
        foreach ($responses as $response) {
            if ($response->getType() === Response::TYPE_DATA) {
                foreach ($response as $name => $value) {
                    $data[$count][$name] = $value;
                }
            }
            $count++;
        }
        return $data;
    }

    protected function getPpoeClients($connected)
    {
        $responses = $connected->sendSync(new Request('/ppp/secret/print'));
        $data = [];
        $count = 0;
        foreach ($responses as $response) {
            if ($response->getType() === Response::TYPE_DATA) {
                foreach ($response as $name => $value) {
                    $data[$count][$name] = $value;
                }
            }
            $count++;
        }
        return $data;
    }

    //TODO agregue esta funcion
    public function getHostpotClients($connected)
    {
        $responses = $connected->sendSync(
            new Request('/ip/hotspot/user/print')
        );
        $data = [];
        $count = 0;
        foreach ($responses as $response) {
            if ($response->getType() === Response::TYPE_DATA) {
                foreach ($response as $name => $value) {
                    $data[$count][$name] = $value;
                }
            }
            $count++;
        }
        return $data;
    }

    /**
     * @param $client * Connection to mikrotik device
     * @param string $queue * name queue
     * @return int  * id
     */
    protected function getIfExistSimpleQueueByName(
        $client,
        $queue = 'SPEEDTEST'
    ) {
        $responses = $client->sendSync(new Request(ComunConstantsController::QUEUE_SIMPLE_WHIT_SLASH . 'print'));

        return count(
            collect($responses)->filter(function ($value) use ($queue) {
                return isset(collect($value)['name']) &&
                    collect($value)['name'] == $queue;
            })
        );
    }

    /**
     * @param $client * Connection to mikrotik device
     * @return array * Queue list
     */
    protected function getAllSimpleQueue($client)
    {
        $responses = $client->sendSync(new Request(ComunConstantsController::QUEUE_SIMPLE_WHIT_SLASH . 'print'));
        $data = [];
        $count = 0;
        foreach ($responses as $response) {
            if ($response->getType() === Response::TYPE_DATA) {
                foreach ($response as $name => $value) {
                    $data[$count][$name] = $value;
                }
            }
            $count++;
        }
        return $data;
    }

    /**
     * add the item in any instance of the mikrotik
     * @param $client * Connection to mikrotik device
     * @param $command * Constant with a place example: QUEUE_SIMPLE
     * @param $arrayArgumentValue * Argument required by the device instance example:
     * (['name'=>'ppoe-client-pepe',
     * 'user'=> 'pepe',
     * 'password'=>'Pass',
     * 'interface'=>'ether2',
     * 'service-name'=> '',
     * 'disabled'=>'no' ])
     * @return void
     */
    public function addItem($conection, $command, $arrayArgumentValue)
    {
        $addRequest = new Request($command . 'add');
        foreach ($arrayArgumentValue as $names => $value) {
            $addRequest->setArgument($names, $value);
        }
        $response = $conection->sendSync($addRequest);
        $responseType = $response->getType();

        if ($responseType === Response::TYPE_FINAL) {
            return true;
        } elseif ($responseType === Response::TYPE_ERROR) {
            $errorMessage = $response->getProperty('message');
            Log::info("Error al ejecutar el comando en el enrutador MikroTik: => " . $errorMessage . "Arguments: " . json_encode($arrayArgumentValue));
        } else {
            Log::info("Error al ejecutar el comando en el enrutador MikroTik: => " . $responseType);
        }
    }

    /**
     * Modificar un valor en un lugar
     * @param $client * Connection to mikrotik device
     * @param $command * Constant with a place example: QUEUE_SIMPLE_WHIT_SLASH
     * @param $id
     * @param $arrayArgumentValue $argument required by the device instance example:
     * (['name'=>'ppoe-client-pepe',
     * 'user'=> 'pepe',
     * 'password'=>'Pass',
     * 'interface'=>'ether2',
     * 'service-name'=> '',
     * 'disabled'=>'no' ])
     */
    protected function setvalueArrayById($client, $command, $id, $arrayArgumentValue)
    {
        $command = rtrim($command, '/');
        $addRequest = new Request($command . '/set');
        $addRequest->setArgument('.id', $id);
        foreach ($arrayArgumentValue as $name => $value) {
            $addRequest->setArgument($name, $value);
        }
        $response = $client->sendSync($addRequest);

        if ($response->getType() === Response::TYPE_ERROR) {
            Log::info("Error al ejecutar el comando en el enrutador MikroTik: => " . $response->getProperty('message') . "Arguments: " . json_encode($arrayArgumentValue) . "para el comando: " . $command);
            return false;
        }
        return true;
    }

    protected function setValueById($client, $command, $id, $names, $value)
    {
        $addRequest = new Request($command . 'set');
        $addRequest->setArgument('numbers', $id);
        $addRequest->setArgument($names, $value);

        if (
            $client->sendSync($addRequest)->getType() !== Response::TYPE_FINAL
        ) {
            return false;
        }
        return true;
    }

    /**
     * Remove item by id
     * @param $client * Connection to mikrotik device
     * @param $command * Constant with a place example: IP_FIREWALL_NAT_WHIT_SLASH
     * @param $name * name
     * @return $id
     */
    protected function removeById($client, $command, $id)
    {
        //$id now contains the ID of the entry we're targeting
        $setRequest = new Request($command . 'remove');
        $setRequest->setArgument('numbers', $id);
        $client->sendSync($setRequest);
    }

    public function deleteClientePpoe($connected, $name)
    {
        if ($this->getIdByName($connected, '/ppp/secret/', $name)) {
            $this->removeById(
                $connected,
                '/ppp/secret/',
                $this->getIdByName($connected, '/ppp/secret/', $name)
            );
        }
    }

    public function deleteClienteHostpot($client, $nameClient)
    {
        if ($this->getIdByName($client, '/ip/hotspot/user/', $nameClient)) {
            $this->removeById(
                $client,
                '/ip/hotspot/user/',
                $this->getIdByName($client, '/ip/hotspot/user/', $nameClient)
            );
        }
    }

    /**
     * Search queue type
     * @param $client * Connection to mikrotik device
     * @param $name * Queue name
     * @return bool * boolean
     */
    protected function getIfExistQueueType($client, $name)
    {
        if (empty($this->getIdByName($client, ComunConstantsController::QUEUE_TYPE_WHIT_SLASH, $name))) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * search by comment
     * @param $client * Connection to mikrotik device
     * @param $command * Constant with a place example: QUEUE_SIMPLE
     * @param $comment * instance comment
     * @return $id
     */
    protected function getIdByComment($client, $command, $comment)
    {
        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', '.id');
        $printRequest->setQuery(Query::where('comment', $comment));
        $id = $client->sendSync($printRequest)->getProperty('.id');
        return $id;
    }

    /**
     * Search by IP
     * @param $client * Connection to mikrotik device
     * @param $command * Constant with a place example: QUEUE_SIMPLE
     * @param $Ip * host Address
     * @return $id
     */
    public function getIdByIp($client, $command, $IP)
    {
        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', '.id');
        $printRequest->setQuery(Query::where('address', $IP));
        $id = $client->sendSync($printRequest)->getProperty('.id');
        return $id;
    }

    public function getPasswordByIp($client, $command, $IP)
    {
        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', 'password');
        $printRequest->setQuery(Query::where('remote-address', $IP));
        $password = $client->sendSync($printRequest)->getProperty('password');
        return $password;
    }

    public function getIdByIpAndNameSecretInMikrotik($clientInternetService)
    {
        $router = Router::find($clientInternetService->router_id);
        if (empty($router)) {
            return null;
        }
        $connected = $this->getConnectionByRouter($router);
        if (empty($connected)) {
            return null;
        }
        $idByIp = $this->getIdByIpSecrets($connected, '/ppp/secret/', $clientInternetService->network_ip_used_by->ip);
        $idByName = $this->getIdByName($connected, '/ppp/secret/', $clientInternetService->getNameClient());
        if ($idByIp && $idByName && $idByIp == $idByName) {
            return $idByIp;
        }
        return null;
    }

    public function getIdByIpSecrets($client, $command, $IP)
    {
        if (empty($IP)) {
            return null;
        }

        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', '.id');
        $printRequest->setQuery(Query::where('remote-address', $IP));
        $id = $client->sendSync($printRequest)->getProperty('.id');
        return $id;
    }
    protected function getPropertyById($client, $command, $id, $property)
    {
        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', $property);
        $printRequest->setQuery(Query::where('.id', $id));
        $value = $client->sendSync($printRequest)->getProperty($property);
        return $value;
    }

    // Métodos originales reimplementados usando el método genérico
    protected function getIpByIdSecrets($client, $command, $id)
    {
        return $this->getPropertyById($client, $command, $id, 'remote-address');
    }

    public function getPasswordById($client, $command, $id)
    {
        return $this->getPropertyById($client, $command, $id, 'password');
    }

    protected function getIpById($client, $command, $id)
    {
        return $this->getPropertyById($client, $command, $id, 'address');
    }

    /**
     * Search by Name
     * @param $client * Connection to mikrotik device
     * @param $command * Constant with a place example: QUEUE_SIMPLE
     * @param $name * instance name
     * @return $id
     */
    protected function getIdByName($client, $command, $name)
    {
        try {
            $printRequest = new Request($command . 'print');
            $printRequest->setArgument('.proplist', '.id');
            $printRequest->setQuery(Query::where('name', $name));
            $id = $client->sendSync($printRequest)->getProperty('.id');
            return $id;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getNameById($client, $command, $id)
    {
        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', 'name');
        $printRequest->setQuery(Query::where('.id', $id));
        $name = $client->sendSync($printRequest)->getProperty('name');
        return $name;
    }

    /**
     * Search by Name
     * @param $client * Connection to mikrotik device
     * @param $command * Constant with a place example: QUEUE_SIMPLE
     * @param $name * instance name
     * @return $id
     */
    protected function getIdByServiceName($client, $command, $name)
    {
        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', '.id');
        $printRequest->setQuery(Query::where('service-name', $name));
        $id = $client->sendSync($printRequest)->getProperty('.id');
        return $id;
    }

    protected function getIdByargument(
        $client,
        $command,
        $argument,
        $value,
        $out
    ) {
        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', $out);
        $printRequest->setQuery(Query::where($argument, $value));
        $id = $client->sendSync($printRequest)->getProperty($out);
        return $id;
    }

    protected function getTargetByName($client, $command, $name)
    {
        $printRequest = new Request($command . 'print');
        $printRequest->setArgument('.proplist', 'target');
        $printRequest->setQuery(Query::where('name', $name));
        $target = $client->sendSync($printRequest)->getProperty('target');
        return $target;
    }

    protected function isNotExistPoolInMikrotik($routerid, $Poolid)
    {
        $pool = Network::find($Poolid);
        $poolName = $pool->title;
        $poolComment = $pool->comment;

        $router = Router::where('id', $routerid)
            ->with('mikrotik')
            ->first();
        $device_ip = $router->ip_host;
        $device_login = $router->mikrotik->login_api;
        $device_password = $router->mikrotik->password_api;
        $device_port = $router->mikrotik->port_api;

        $Ipv4Calculator = new Ipv4CalculatorController();
        $ranges = $Ipv4Calculator->getRangesIP($pool->network, $pool->bm);

        $connected = $this->connection(
            $device_ip,
            $device_login,
            $device_password,
            $device_port
        );
        if ($connected) {
            if (!$this->getIdByName($connected, '/ip/pool/', $poolName)) {
                $this->addItem($connected, '/ip pool ', [
                    'name' => $poolName,
                    'ranges' => collect($ranges)->implode('-'),
                    'comment' => $poolComment,
                ]);
            }
        }
    }

    /**
     * @param $connection
     * @return \Illuminate\Support\Collection
     ['$.id',
     '$name',
     '$service',
     '$caller-id',
     '$address',
     '$uptime',
     '$encoding',
     '$session-id',
     '$limit-bytes-in',
     '$limit-bytes-out',
     '$radius',
     '$comment']
     */
    protected function getAllClientPppWithActiveConnection($connection)
    {
        $ppps = $connection
            ->sendSync(new Request('/ppp/active/print'))
            ->getAllOfType(Response::TYPE_DATA);
        return collect($ppps)->map(function ($val) {
            return collect($val)->toArray();
        });
    }

    protected function getAllPppSecretsIps($connection)
    {
        $command = '/ppp/secret/';
        // Crear una solicitud sin filtro para obtener todos los registros de ppp secrets
        $printRequest = new Request($command . 'print');

        // Enviar la solicitud y obtener todos los resultados
        $responses = $connection->sendSync($printRequest);

        // Procesar las respuestas para extraer las IPs
        $ips = [];
        foreach ($responses as $response) {
            if ($response->getProperty('remote-address')) {
                $ips[] = $response->getProperty('remote-address');
            }
        }
        return $ips;
    }

    protected function getAllIpsInAddressList($connection)
    {
        $command = '/ip/firewall/address-list/';
        $list = 'MgNet_Morosos';
        // Crear una solicitud sin filtro para obtener todos los registros de firewall
        $printRequest = new Request($command . 'print');
        $printRequest->setQuery(Query::where('list', $list));

        // Enviar la solicitud y obtener todos los resultados
        $responses = $connection->sendSync($printRequest);

        // Procesar las respuestas para extraer las IPs
        $ips = [];
        foreach ($responses as $response) {
            if ($response->getProperty('address')) {
                $ips[] = $response->getProperty('address');
            }
        }

        return $ips;
    }

    protected function getAllEntriesInAddressList($connection)
    {
        $command = '/ip/firewall/address-list/';
        $list = 'MgNet_Morosos';

        // Crear una solicitud sin filtro para obtener todos los registros de firewall
        $printRequest = new Request($command . 'print');
        $printRequest->setQuery(Query::where('list', $list));

        // Enviar la solicitud y obtener todos los resultados
        $responses = $connection->sendSync($printRequest);

        // Procesar las respuestas para extraer todos los datos
        $entries = [];
        foreach ($responses as $response) {
            // Obtener todas las propiedades del registro
            $entries[] = [
                'address' => $response->getProperty('address'),
                'list' => $response->getProperty('list'),
                'comment' => $response->getProperty('comment'),
                'disabled' => $response->getProperty('disabled'),
            ];
        }

        return $entries;
    }

    protected function getAllEntriesInPPPSecret($connection)
    {
        $command = '/ppp/secret/';
        // Crear una solicitud sin filtro para obtener todos los registros de ppp secrets
        $printRequest = new Request($command . 'print');

        // Enviar la solicitud y obtener todos los resultados
        $responses = $connection->sendSync($printRequest);

        // Procesar las respuestas para extraer las IPs
        $entries = [];
        foreach ($responses as $response) {
            $entries[] = [
                'name' => $response->getProperty('name'),
                'remote-address' => $response->getProperty('remote-address'),
                'comment' => $response->getProperty('comment'),
                'disabled' => $response->getProperty('disabled'),
                'password' => $response->getProperty('password'),
                'service' => $response->getProperty('service'),
                'profile' => $response->getProperty('profile'),
            ];
        }
        return $entries;
    }

    protected function getAllClientSimpleQueues($connection)
    {
        $ppps = $connection
            ->sendSync(new Request(ComunConstantsController::QUEUE_SIMPLE_WHIT_SLASH . 'print'))
            ->getAllOfType(Response::TYPE_DATA);
        return collect($ppps)->map(function ($val) {
            return collect($val)->toArray();
        });
    }

    public function addWebProxyAccessIpsPermited($connected, $ip_permited)
    {
        $ips = explode(',', $ip_permited);
        foreach ($ips as $ip) {
            $comment = 'MgNet_ACCESS_IP_PERMITED-' . $ip;
            if (
                !$this->getIdByComment(
                    $connected,
                    '/ip/proxy/access/',
                    $comment
                )
            ) {
                $this->addItem($connected, '/ip proxy access ', [
                    'action' => 'allow',
                    'dst-address' => $ip,
                    'comment' => $comment,
                ]);
            }
        }
    }

    public function addWebProxyAccessIpRedirect($connected, $ip_redirect)
    {
        $comment = 'MgNet_ACCESS_IP_REDIRECT';
        if (!$this->getIdByComment($connected, '/ip/proxy/access/', $comment)) {
            $this->addItem($connected, '/ip proxy access ', [
                'action' => 'allow',
                'dst-address' => $ip_redirect,
                'comment' => $comment,
            ]);
        }
    }

    public function addWebProxyAccessUrlRedirect($connected, $url_redirect)
    {
        $comment = 'MgNet_ACCESS_URL_REDIRECT';
        if (!$this->getIdByComment($connected, '/ip/proxy/access/', $comment)) {
            $this->addItem($connected, '/ip proxy access ', [
                'action' => 'deny',
                'redirect-to' => $url_redirect,
                'comment' => $comment,
            ]);
        }
    }

    public function addPpoeProfile($connected, $router)
    {
        if (
            !$this->getIdByName(
                $connected,
                '/ppp/profile/',
                $router->mikrotikconfig->mikrotik_config_server_pppoe_profile
            )
        ) {
            $this->addItem($connected, '/ppp profile ', [
                'name' =>  $router->mikrotikconfig->mikrotik_config_server_pppoe_profile,
                'local-address' =>
                $router->mikrotikconfig->mikrotik_config_server_ppp_local_address,
                'remote-address' =>
                $router->mikrotikconfig->mikrotik_config_server_ppp_remote_address,
                'bridge' => $router->mikrotikconfig->mikrotik_config_server_ppp_bridge,
                'dns-server' => '8.8.8.8,8.8.4.4',
            ]);
        }
    }

    public function addServerRadius($connected, $router, $forceCreate = false)
    {
        if ($forceCreate) {
            $this->addItem($connected, '/radius ', [
                'service' => 'ppp,login',
                'secret' => $router->secret_radius,
                'address' => $router->nas_ip,
                'protocol' => 'udp',
                'comment' => 'MgNet_Radius_Service' . $router->id,
            ]);
            return;
        }

        if (
            !$this->getIdByComment(
                $connected,
                '/radius ',
                'MgNet_Radius_Service' . $router->id
            )
        ) {
            $this->addItem($connected, '/radius ', [
                'service' => 'ppp,login',
                'secret' => $router->secret_radius,
                'address' => $router->nas_ip,
                'protocol' => 'udp',
                'comment' => 'MgNet_Radius_Service' . $router->id,
            ]);
        }
    }

    public function addServerPppoe($connected, $router)
    {
        if (
            !$this->getIdByServiceName(
                $connected,
                '/interface/pppoe-server/server/',
                $router->mikrotikconfig->mikrotik_config_server_pppoe_name
            )
        ) {
            $this->addItem($connected, '/interface pppoe-server server ', [
                'service-name' => $router->mikrotikconfig->mikrotik_config_server_pppoe_name,
                'interface' => $router->mikrotikconfig->mikrotik_config_server_pppoe_interface,
                'max-mtu' => $router->mikrotikconfig->mikrotik_config_server_pppoe_mtu,
                'max-mru' => $router->mikrotikconfig->mikrotik_config_server_pppoe_mru,
                'default-profile' =>
                $router->mikrotikconfig->mikrotik_config_server_pppoe_profile,
                'one-session-per-host' => 'yes',
                'authentication' => 'mschap2, mschap1, chap, pap',
                'disabled' => 'no',
            ]);
        }
    }

    public function updatePpoeProfile($connected, $mikrotikConfig, $namePppProfile)
    {
        $id = $this->getIdByName(
            $connected,
            '/ppp/profile/',
            $namePppProfile
        );
        if ($id) {
            $this->setvalueArrayById($connected, '/ppp/profile/', $id, [
                'name' =>
                $mikrotikConfig->mikrotik_config_server_pppoe_profile,
                'local-address' =>
                $mikrotikConfig->mikrotik_config_server_ppp_local_address,
                'remote-address' =>
                $mikrotikConfig->mikrotik_config_server_ppp_remote_address,
                'bridge' =>
                $mikrotikConfig->mikrotik_config_server_ppp_bridge,
                'dns-server' => '8.8.8.8,8.8.4.4',
            ]);
        }
    }

    public function updateServerPppoe($connected, $mikrotikConfig, $nameServerPpoe)
    {
        $id = $this->getIdByServiceName(
            $connected,
            '/interface/pppoe-server/server/',
            $nameServerPpoe
        );
        if ($id) {
            $this->setvalueArrayById(
                $connected,
                '/interface/pppoe-server/server/',
                $id,
                [
                    'service-name' =>
                    $mikrotikConfig->mikrotik_config_server_pppoe_name,
                    'interface' =>
                    $mikrotikConfig->mikrotik_config_server_pppoe_interface,
                    'max-mtu' =>
                    $mikrotikConfig->mikrotik_config_server_pppoe_mtu,
                    'max-mru' =>
                    $mikrotikConfig->mikrotik_config_server_pppoe_mru,
                    'default-profile' =>
                    $mikrotikConfig->mikrotik_config_server_pppoe_profile,
                    'one-session-per-host' => 'yes',
                    'authentication' => 'mschap2, mschap1, chap, pap',
                    'disabled' => 'no',
                ]
            );
        }
        return false;
    }

    public function updateNatRulesMorososRedirct($connected, $router)
    {
        $comment = 'MgNet_REDIRECT_MOROSOS_TO_WEB_PROXY';
        $id = $this->getIdByComment($connected, ComunConstantsController::IP_FIREWALL_NAT_WHIT_SLASH, $comment);
        if ($id) {
            $this->setvalueArrayById(
                $connected,
                ComunConstantsController::IP_FIREWALL_NAT_WHIT_SLASH,
                $id,
                [
                    'chain' => 'dstnat',
                    'protocol' => 'tcp',
                    'dst-port' => '80',
                    'action' => 'redirect',
                    'to-ports' => $router->mikrotik->port_redirect,
                    'src-address-list' => 'MgNet_Morosos',
                    'comment' => $comment
                ]
            );
        }
    }

    public function updateServerRadius($connected, $router)
    {
        $id = $this->getIdByComment(
            $connected,
            '/radius ',
            'MgNet_Radius_Service' . $router->id
        );
        if ($id) {
            $this->setvalueArrayById(
                $connected,
                '/radius/',
                $id,
                [
                    'service' => 'ppp,login',
                    'secret' => $router->secret_radius,
                    'address' => $router->nas_ip,
                    'protocol' => 'udp',
                    'comment' => 'MgNet_Radius_Service' . $router->id,
                ]
            );
        }
    }

    public function updateWebProxyAccessIpRedirect($connected, $ip_redirect, $router)
    {
        $comment = 'MgNet_ACCESS_IP_REDIRECT';
        $id = $this->getIdByComment($connected, '/ip/proxy/access/', $comment);
        if ($id) {
            $this->setvalueArrayById($connected, '/ip/proxy/access/', $id, ([
                'action' => 'allow',
                'dst-address' => $ip_redirect,
                'comment' => $comment
            ]));
        }
    }

    public function updateWebProxyAccessUrlRedirect($connected, $url_redirect)
    {
        $comment = 'MgNet_ACCESS_URL_RREDIRECT';
        $id = $this->getIdByComment($connected, '/ip/proxy/access/', $comment);
        if ($id) {
            $this->setvalueArrayById($connected, '/ip/proxy/access/', $id, ([
                'action' => 'deny',
                'redirect-to' => $url_redirect,
                'comment' => $comment
            ]));
        }
    }

    public function updateWebProxyAccessIpsPermited($connected, $ip_permited)
    {
        $ips = explode(',', $ip_permited);
        foreach ($ips as $ip) {
            $comment = 'MgNet_ACCESS_IP_PERMITED-' . $ip;
            $id = $this->getIdByComment($connected, '/ip/proxy/access/', $comment);
            if ($id) {
                $this->setvalueArrayById($connected, '/ip/proxy/access/', $id, ([
                    'action' => 'allow',
                    'dst-address' => $ip,
                    'comment' => $comment
                ]));
            }
        }
    }

    public function addRulesInputApiAccept($connected, $command, $srcAddress, $dstPort)
    {
        $comment = 'MgNet_INPUT_MEGANET_TO_API_ACCEPT';
        if (!$this->getIdByComment($connected, $command, $comment)) {
            $this->addItem($connected, ComunConstantsController::IP_FIREWALL_FILTER_WITH_SPACE, [
                'chain' => 'input',
                'action' => 'accept',
                'src-address' => $srcAddress,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => 'tcp',
                'src-port' => null,
                'dst-port' => $dstPort,
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => null,
                'dst-address-list' => null,
                'connection-state' => null,
                'comment' => $comment,
            ]);
        }
    }

    public function updateRulesInputApiAccept($connected, $routerPort_api, $mikrotikconfig)
    {
        $comment = 'MgNet_INPUT_MEGANET_TO_API_ACCEPT';
        $id = $this->getIdByComment($connected, ComunConstantsController::IP_FIREWALL_RULES_WHIT_SLASH, $comment);
        if ($id) {
            $this->setvalueArrayById($connected, ComunConstantsController::IP_FIREWALL_RULES_WHIT_SLASH, $id, ([
                'chain' => 'input',
                'action' => 'accept',
                'src-address' => $mikrotikconfig->meganet_config_ip_address_enable ? $mikrotikconfig->meganet_config_ip_address : null,
                'dst-address' => null,
                'dst-address-type' => null,
                'protocol' => 'tcp',
                'src-port' => null,
                'dst-port' => $routerPort_api,
                'port' => null,
                'in-interface' => null,
                'out-interface' => null,
                'src-address-list' => null,
                'dst-address-list' => null,
                'connection-state' => null,
                'comment' => $comment
            ]));
        }
    }

    public function removeIpSerparatedbyComma($connected, $IpSerparatedbyComma)
    {
        $ips = explode(',', $IpSerparatedbyComma);
        foreach ($ips as $ip) {
            $this->removeById(
                $connected,
                '/ip/proxy/access/',
                $this->getIdByComment($connected, '/ip/proxy/access/', 'MgNet_ACCESS_IP_PERMITED-' . $ip)
            );
        }
    }

    protected function removeAll($client, $command)
    {
        $items = $client->sendSync(new Request($command . 'print'))->getAllOfType(Response::TYPE_DATA);

        foreach ($items  as $item) {
            $id = $item('.id');
            $this->removeById($client, $command, $id);
        }
    }

    protected function fileExport($client)
    {
        $exportFileName = 'EXPORT.rsc';
        $util = new Util($client);
        $client->sendSync($util->newRequest('export', array('file' => $exportFileName)));
        sleep(2);
        $export = $util->fileGetContents($exportFileName);
        $util->filePutContents($exportFileName, null); //Optional; Remove the file from the router
        return $export;
    }

    public function robustUploadToMikrotik($routerId, $localFilePath, $remoteFileName)
    {
        $router = Router::find($routerId);
        if (!$router) {
            throw new \Exception("Router no encontrado");
        }

        $client = $this->getConnectionByRouter($router);
        if (!$client) {
            throw new \Exception("No se pudo conectar al router");
        }

        // 1. Verificación previa
        $this->verifyApiUserPermissions($routerId);
        if (!$this->checkRouterFileSystem($client)) {
            throw new \Exception("Problemas con el sistema de archivos del router");
        }

        $util = new Util($client);

        $remotePath = $remoteFileName;
        $fileContent = file_get_contents($localFilePath);

        if ($fileContent === false) {
            throw new \Exception("No se pudo leer el archivo local");
        }
        try {
            Log::info("Iniciando subida completa del archivo");

            // Subir todo el contenido de una vez
            $util->filePutContents($remotePath, $fileContent);

            Log::info("Subida completada");

            // Verificación estricta
            $this->validateUploadedFile($client, $remotePath, filesize($localFilePath));

            return true;
        } catch (\Exception $e) {

            throw new \Exception("Error en subida: " . $e->getMessage());
        }
    }

    private function validateUploadedFile($client, $remotePath, $expectedSize)
    {
        // Esperar breve momento para sincronización de archivos
        sleep(2);

        // Verificar existencia
        $fileRequest = new Request('/file/print');
        $fileRequest->setQuery(Query::where('name', $remotePath));
        $fileInfo = $client->sendSync($fileRequest);

        if (!$fileInfo->getProperty('.id')) {
            throw new \Exception("El archivo no aparece en el router");
        }

        return true;
    }

    public function verifyApiUserPermissions($routerId)
    {
        $router = Router::find($routerId);
        $client = $this->getConnectionByRouter($router);

        $request = new Request('/user/print');
        $request->setQuery(Query::where('name', $router->mikrotik->login_api));
        $user = $client->sendSync($request);

        if (!$user->getProperty('group') === 'full') {
            // Actualizar permisos
            $setRequest = new Request('/user/set');
            $setRequest->setArgument('numbers', $user->getProperty('.id'));
            $setRequest->setArgument('group', 'full');
            $client->sendSync($setRequest);

            Log::info("Permisos de usuario actualizados a 'full'");
            return true;
        }

        return false;
    }

    public function checkRouterFileSystem($client)
    {
        try {
            // Verificar espacio libre
            $resources = $client->sendSync(new Request('/system/resource/print'));
            $freeSpace = $resources->getProperty('free-hdd-space');

            if ($freeSpace < 1048576) { // Menos de 1MB libre
                throw new \Exception("Espacio insuficiente en router: " . round($freeSpace / 1024 / 1024, 2) . "MB libres");
            }

            // Verificar sistema de archivos
            $health = $client->sendSync(new Request('/system/health/print'));
            if ($health->getProperty('bad-blocks') > 0) {
                throw new \Exception("Sistema de archivos con errores (bad blocks encontrados)");
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Error en sistema de archivos: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Obtiene el consumo en tiempo real (bps) de un cliente por su nombre en Simple Queues
     */
    public function getClientRealTimeSpeed($connected, $queueName)
    {
        $request = new Request('/queue/simple/print');
        $request->setArgument('.proplist', 'rate'); // Solo pedimos el rate para mayor velocidad
        $request->setQuery(Query::where('name', $queueName));

        $response = $connected->sendSync($request);

        if ($response->getType() === Response::TYPE_DATA) {
            $rate = $response->getProperty('rate'); // Retorna algo como "1024/2048" (up/down)
            $parts = explode('/', $rate);
            return [
                'upload' => $parts[0],   // bits por segundo
                'download' => $parts[1]  // bits por segundo
            ];
        }
        return ['upload' => 0, 'download' => 0];
    }


    /**
     * Obtiene bytes totales subidos y bajados en la sesión actual de un cliente PPPoE
     */
    public function getPppClientTraffic($connected, $userName)
    {
        $request = new Request('/ppp/active/print');
        $request->setQuery(Query::where('name', $userName));

        $response = $connected->sendSync($request);

        if ($response->getType() === Response::TYPE_DATA) {
            return [
                'bytes_in' => $response->getProperty('bytes-in'),   // Subida total
                'bytes_out' => $response->getProperty('bytes-out'), // Bajada total
                'uptime' => $response->getProperty('uptime')        // Tiempo conectado
            ];
        }
        return null;
    }

    public function monitorInterfaceTraffic($connected, $interfaceName)
    {
        $request = new Request('/interface/monitor-traffic');
        $request->setArgument('interface', $interfaceName);
        $request->setArgument('once', ''); // Importante: 'once' para que no se quede pegado el script

        $response = $connected->sendSync($request);

        if ($response->getType() === Response::TYPE_DATA) {
            return [
                'rx' => $response->getProperty('rx-bits-per-second'),
                'tx' => $response->getProperty('tx-bits-per-second')
            ];
        }
        return null;
    }


    public function configureLegalAccounting($connected, $router)
    {
        // 1. Activar el Accounting de PPP para que use RADIUS
        $this->addItem($connected, '/ppp/aaa ', [
            'use-radius' => 'yes',
            'accounting' => 'yes',
            'interim-update' => '00:03:00' // Reportar consumo cada 3 minutos (CRUCIAL)
        ]);

        // 2. Asegurarte que el servidor RADIUS esté configurado para accounting
        // El puerto por defecto para accounting es 1813
        $this->addItem($connected, '/radius ', [
            'service' => 'ppp',
            'address' => 'IP_DE_TU_SERVIDOR_LARAVEL',
            'secret' => 'tu_clave_secreta_radius',
            'authentication-port' => 1812,
            'accounting-port' => 1813,
            'comment' => 'MgNet_Accounting_Service'
        ]);
    }

    public function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function formatSeconds($seconds) {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    public function formatToGB($bytes) {
        if ($bytes < 1073741824) { // Menor a 1 GB
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }
        return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }

    public function formatSpeed($bits) {
        if ($bits >= 1000000) return round($bits / 1000000, 2) . ' Mbps';
        if ($bits >= 1000) return round($bits / 1000, 2) . ' Kbps';
        return $bits . ' bps';
    }

    public function parseMikrotikUptimeToSeconds($uptime) {
        if (empty($uptime)) return 0;

        $seconds = 0;

        // Formato con letras: 2w2d23h1m21s
        $units = [
            'w' => 604800,
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
            's' => 1
        ];

        foreach ($units as $unit => $value) {
            if (preg_match('/(\d+)' . $unit . '/', $uptime, $matches)) {
                $seconds += (int)$matches[1] * $value;
            }
        }

        // Si no encontró letras, intentar el formato clásico HH:MM:SS
        if ($seconds === 0 && strpos($uptime, ':') !== false) {
            $parts = explode(':', $uptime);
            $count = count($parts);
            if ($count == 3) { // HH:MM:SS
                $seconds = ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
            } elseif ($count == 2) { // MM:SS
                $seconds = ($parts[0] * 60) + $parts[1];
            }
        }

        return (int)$seconds;
    }

}
