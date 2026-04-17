<?php

namespace App\Jobs;

use App\Http\Repository\RouterRepository;
use App\Models\MikrotikClientPpoe;
use App\Models\MikrotikTariffTargetTail;
use App\Services\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Router;
use App\Http\Traits\RouterConnection;
use App\Models\MikrotikTariffMainTail;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Models\ClientAdditionalInformation;
use App\Models\MikrotikClientHostpotUser;
use App\Services\NetworkIpService;
use Illuminate\Support\Facades\Log;

class CreateClientWithServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $clientInternetService;
    protected $model;
    protected $parentTailCommentPrefix;
    protected $parentTailNamePrefix;
    protected $sunTailNamePrefix;
    protected $internet;
    protected $client;
    protected $clientIp;
    protected $authorizationAccounting;
    protected $forceCreate;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientInternetService, $model = 'App\Models\Internet', $forceCreate = false)
    {
        $this->clientInternetService = $clientInternetService;
        $this->model = $model;
        $this->forceCreate = $forceCreate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(MikrotikService $mikrotikService)
    {
        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById($this->clientInternetService->router_id);
        $tail = '';
        $son = '';
        if ($router && $router->isMikrotik()) {
            $this->authorizationAccounting = $router->authorization_accounting;
            $mikrotik = $router->mikrotik()->first();
            $connection = $mikrotikService->getConnection($router);
            if ($this->isClientAdditionalInformationConectionTypeWifi($this->clientInternetService)) {
                if ($this->isShapingTypeSimpleQueue($mikrotik)) {
                    $parentTail = $this->getParentTailIfExistAndIfHasSpace();
                    if (!$parentTail) {
                        $tail = $this->getCountOfParentQueue();
                        $parentTail = $this->createParentQueue($mikrotik, $connection, $tail);
                        $son = 1;
                    } else {
                        $tail = $this->getCountOfParentQueue($parentTail);
                        $son = $this->getSonForTailParent($parentTail);
                    }
                    $this->addTargetToParentQueue($connection, $parentTail);
                    $this->createSunQueue($mikrotik, $connection, $tail, $parentTail, $son);
                }
            }

            if ($this->authorizationAccounting == Router::PPPOE_USER) {
                $this->createClientPPoeIfNotExist($connection, $mikrotik, $tail, $son, $this->forceCreate);
            } else if ($this->authorizationAccounting == Router::HOSTPOT_USER) {
                $this->createClientHotspotIfNotExist($connection, $mikrotik, $tail, $son, $this->forceCreate);
            } else if ($this->authorizationAccounting == Router::RADIUS_USER) {
                $this->addServerRadius($connection, $router, $this->forceCreate);
                $this->createClientPPoeIfNotExist($connection, $mikrotik, $tail, $son, $this->forceCreate);
            }
        }
    }

    public
    function getParentTailIfExistAndIfHasSpace()
    {
        return MikrotikTariffMainTail::withCount('mikrotik_tariff_target_tail')
            ->leftJoin('internets', 'mikrotik_tariff_main_tails.tariff_id', '=', 'internets.id')
            ->where('mikrotik_tariff_main_tails.model', $this->model)
            ->where('mikrotik_tariff_main_tails.tariff_id', '=', $this->clientInternetService->internet_id)
            ->selectRaw('mikrotik_tariff_main_tails.*, internets.aggregation')
            ->get()
            ->filter(function ($val, $key) {
                return $val->mikrotik_tariff_target_tail_count < $val->aggregation;
            })->first();
    }

    public
    function getSonForTailParent($parentTail)
    {
        return $parentTail->mikrotik_tariff_target_tail_count + 1;
    }

    public
    function getCountOfParentQueue($parentTail = null)
    {
        $this->parentTailNamePrefix = $this->clientInternetService->router->mikrotikconfig->custom_config_name_parent_router;

        if ($parentTail) {
            $json = json_decode($parentTail->json);
            $parentname = collect($json)->get('name');
            return Str::between($parentname, $this->parentTailNamePrefix . ' #', '-');
        }

        $count = MikrotikTariffMainTail::where('model', $this->model)
            ->where('tariff_id', '=', $this->clientInternetService->internet_id)
            ->count();
        return $count + 1;
    }

    public
    function _isDisable($disable)
    {
        $disable == 'Activado' || $disable == 'Nuevo' || $disable == 'Pendiente'
            ? $disable = 'yes'
            : $disable = 'no';
        return $disable;
    }

    public
    function _priority($priotity)
    {
        switch ($priotity) {
            case 'Baja':
                return '8';
                break;
            case 'Normal':
                return '5';
                break;
            case 'Alta':
                return '1';
                break;
        }
    }

    public function createParentQueue($mikrotik, $connection, $cola_id)
    {
        $this->parentTailNamePrefix = $this->clientInternetService->router->mikrotikconfig->custom_config_name_parent_router;
        $this->internet = $this->clientInternetService->internet;
        $networkIpService = new NetworkIpService($this->clientInternetService);

        $queueName = $this->parentTailNamePrefix . ' #' . $cola_id . '-' . $this->internet->id;

        $array = [
            'name' => $queueName,
            'comment' => $this->clientInternetService->router->mikrotikconfig->custom_config_comment_parent_router . ' #' . $cola_id . '-' . $this->internet->service_name,
            'target' => $networkIpService->getClientIp(), // Nota: Generalmente el parent lleva el segmento completo, ej: 10.1.0.0/16
            'queue' => 'pcq-upload-default/pcq-download-default',
            'total-queue' => 'default-small',
            'parent' => 'none',
            'priority' => $this->_priority($this->internet->priority) . '/' . $this->_priority($this->internet->priority),
            'limit-at' => ($this->internet->upload_speed * 16) . '/' . ($this->internet->download_speed * 16),
            'burst-limit' => ($this->internet->upload_speed * 16 * 2) . '/' . ($this->internet->download_speed * 16 * 2),
            'max-limit' => ($this->internet->upload_speed * 16) . '/' . ($this->internet->download_speed * 16),
            'burst-time' => '800/800',
            'burst-threshold' => ($this->internet->upload_speed * 16) . '/' . ($this->internet->download_speed * 16)
        ];

        $existingId = $this->getIdByName($connection, '/queue/simple/', $queueName);

        if ($existingId) {
            $this->setvalueArrayById($connection, '/queue/simple/', $existingId, $array);
        } else {
            $this->addItem($connection, '/queue simple ', $array);
        }

        // Sincronización con la DB local: updateOrCreate evita duplicados
        return MikrotikTariffMainTail::updateOrCreate(
            [
                'mikrotik_id' => $mikrotik->id,
                'tariff_id' => $this->internet->id,
                'model' => $this->model, // Asumiendo que esta propiedad existe en tu clase
            ],
            ['json' => collect($array)]
        );
    }

    public function createSunQueue($mikrotik, $connection, $cola_id, $parentTail, $idSon)
    {
        $this->client = $this->clientInternetService->client;
        $this->internet = $this->clientInternetService->internet;
        $this->parentTailNamePrefix = $this->clientInternetService->router->mikrotikconfig->custom_config_name_parent_router;
        $this->sunTailNamePrefix = $this->clientInternetService->router->mikrotikconfig->custom_config_comment_sun_router;

        $networkIpService = new NetworkIpService($this->clientInternetService);
        $clientIp = $networkIpService->getClientIp();

        $queueName = $this->sunTailNamePrefix . ' #' . $cola_id . $this->internet->id . '-' . $idSon;
        $login = $this->clientInternetService->getNameClient();

        $array = [
            'name' => $queueName,
            'comment' => $login,
            'target' => $clientIp,
            'queue' => 'pcq-upload-default/pcq-download-default',
            'total-queue' => 'default-small',
            'parent' => $this->parentTailNamePrefix . ' #' . $cola_id . '-' . $this->internet->id,
            'priority' => $this->_priority($this->internet->priority) . '/' . $this->_priority($this->internet->priority),
            'limit-at' => $this->internet->upload_speed . '/' . $this->internet->download_speed,
            'max-limit' => $this->internet->upload_speed . '/' . $this->internet->download_speed,
            'burst-limit' => ($this->internet->upload_speed * 2) . '/' . ($this->internet->download_speed * 2),
            'burst-time' => '800/800',
            'burst-threshold' => $this->internet->upload_speed . '/' . $this->internet->download_speed
        ];

        // Verificar si la cola hijo ya existe (por comentario es más seguro si el nombre cambia)
        $haveSunSimpleQueueId = $this->getIdByComment($connection, '/queue/simple/', $login);

        if (!$haveSunSimpleQueueId) {
            $this->addItem($connection, '/queue simple ', $array);
        } else {
            $this->setvalueArrayById($connection, '/queue/simple/', $haveSunSimpleQueueId, $array);
        }

        // Actualizar base de datos local
        $sunTail = MikrotikTariffTargetTail::updateOrCreate(
            [
                'client_internet_service_id' => $this->clientInternetService->id,
            ],
            [
                'mikrotik_tariff_main_tail_id' => $parentTail->id,
                'mikrotik_id' => $mikrotik->id,
                'tariff_id' => $this->internet->id,
                'model' => $this->model,
                'json' => collect($array),
            ]
        );

        // Marcar IP como usada
        if ($clientIp) {
            // Asumiendo que el servicio de IP tiene un método para marcar uso
            $this->clientInternetService->update(['estado' => 'Activado']);
        }

        return $sunTail;
    }

    public function createClientPPoeIfNotExist($connection, $mikrotik, $cola_id, $idSon, $forceCreate = false)
    {
        $this->client = $this->clientInternetService->client;
        $this->internet = $this->clientInternetService->internet;
        $networkIpService = new NetworkIpService($this->clientInternetService);
        $login = $this->clientInternetService->getNameClient();
        $password = $this->clientInternetService->password ?? 'Meganet-' . $this->clientInternetService->id;
        $disabled = $this->client->clientGetStatus();
        $cola_id != '' ? $comment = 'Meganet_' . $cola_id . $this->clientInternetService->id . '-' . $idSon : $comment = 'Meganet_' . $this->clientInternetService->id;

        if ($forceCreate) {
            $this->addItem($connection, '/ppp secret ', [
                'name' => $login,
                'password' => $password,
                'service' => 'any',
                'profile' => 'default',
                'remote-address' => $networkIpService->getClientIp(),
                'disabled' => $this->_isDisable($disabled),
                'comment' => $comment
            ]);
            return;
        }
        if (!$this->getIdByName($connection, '/ppp/secret/', $login)) {
            $this->addItem($connection, '/ppp secret ', [
                'name' => $login,
                'password' => $password,
                'service' => 'any',
                'profile' => 'default',
                'remote-address' => $networkIpService->getClientIp(),
                'disabled' => $this->_isDisable($disabled),
                'comment' => $comment
            ]);
        } else {
            $this->setvalueArrayById(
                $connection,
                '/ppp/secret/',
                $this->getIdByName($connection, '/ppp/secret/', $login),
                [
                    'name' => $login,
                    'password' => $password,
                    'service' => 'any',
                    'profile' => 'default',
                    'remote-address' => $networkIpService->getClientIp(),
                    'disabled' => $this->_isDisable($disabled),
                    'comment' => $comment
                ]
            );
        }
        $datos = [
            'client_id' => $this->client->id,
            'mikrotik_id' => $mikrotik->id,
        ];
        MikrotikClientPpoe::updateOrCreate($datos);
    }


    public function createClientHotspotIfNotExist($connection, $mikrotik, $cola_id, $idSon, $forceCreate = false)
    {
        $this->client = $this->clientInternetService->client;
        $this->internet = $this->clientInternetService->internet;
        $networkIpService = new NetworkIpService($this->clientInternetService);
        $login = $this->clientInternetService->getNameClient();
        $password = $this->clientInternetService->password;
        $comment = 'Meganet-' . $cola_id . $this->clientInternetService->client_id . '-' . $idSon;

        if ($forceCreate) {
            $this->addItem($connection, '/ip hotspot user ', [
                'name' => $login,
                'password' => $password,
                'profile' => 'default',
                'comment' => $comment,
                'address' => $networkIpService->getClientIp(),
                'server' => 'all',
            ]);
            return;
        }

        $disabled = $this->client->clientGetStatus();
        if (!$this->getIdByName($connection, '/ip/hotspot/user/', $login)) {
            $this->addItem($connection, '/ip hotspot user ', [
                'name' => $login,
                'password' => $password,
                'profile' => 'default',
                'comment' => $comment,
                'address' => $networkIpService->getClientIp(),
                'server' => 'all',
            ]);
        } else {
            $this->setvalueArrayById(
                $connection,
                '/ip/hotspot/user/',
                $this->getIdByName($connection, '/ip/hotspot/user/', $login),
                [
                    'name' => $login,
                    'password' => $password,
                    'profile' => 'default',
                    'disabled' => $this->_isDisable($disabled),
                    'address' => $networkIpService->getClientIp(),
                    'server' => 'all',
                ]
            );
        }
        $datos = [
            'client_id' => $this->client->id,
            'mikrotik_id' => $mikrotik->id,
        ];
        MikrotikClientHostpotUser::updateOrCreate($datos);
    }

    public function addTargetToParentQueue($connection, $parentTail)
    {
        $networkIpService = new NetworkIpService($this->clientInternetService);
        $newTarget = $networkIpService->getClientIp();

        if ($parentTail && $newTarget) {
            // 1. Decodificar manualmente el string JSON
            $json = json_decode($parentTail->json, true);

            // Validar que la decodificación fue exitosa y es un array
            if (!is_array($json)) {
                Log::error("Error decodificando JSON de la cola padre ID: " . $parentTail->id);
                return;
            }

            $currentTargets = $json['target'] ?? '';
            $parentName = $json['name'] ?? '';

            // 2. Limpiar y convertir la lista actual de IPs en un array
            $targetsArray = array_filter(explode(',', $currentTargets));
            $targetsArray = array_map('trim', $targetsArray);

            // 3. SOLO proceder si la IP no está ya en la lista
            if (!in_array($newTarget, $targetsArray)) {
                $targetsArray[] = $newTarget;
                $updatedTargetStr = implode(',', $targetsArray);

                // 4. Buscar el ID en MikroTik por el nombre que tenemos en el JSON
                $mikrotikId = $this->getIdByName($connection, '/queue/simple/', $parentName);

                if ($mikrotikId) {
                    // 5. Intentar actualizar en el MikroTik
                    $success = $this->setValueById(
                        $connection,
                        '/queue/simple/',
                        $mikrotikId,
                        'target',
                        $updatedTargetStr
                    );

                    if ($success) {
                        // 6. Si el router aceptó el cambio, actualizamos el array y lo encodificamos
                        $json['target'] = $updatedTargetStr;
                        $parentTail->json = json_encode($json);
                        $parentTail->save();
                    }
                } else {
                    Log::warning("No se encontró la cola padre en MikroTik con nombre: " . $parentName);
                }
            }
        }
    }

    public
    function updateJsonByTarget($json, $target, $newTarget)
    {
        $arrayJson = collect($json)->toArray();
        Arr::set($arrayJson, 'target', $target . ',' . $newTarget);
        $newJson = json_encode($arrayJson);
        return $newJson;
    }

    public function isShapingTypeSimpleQueue($mikrotik)
    {
        return 'Simple queue(Con árbol de cola)' == $mikrotik->shaping_type;
    }

    public function isClientAdditionalInformationConectionTypeWifi($clientInternetService)
    {
        $clientAdditionalInformation = ClientAdditionalInformation::where('client_id', $clientInternetService->client_id)->first();
        if ($clientAdditionalInformation) {
            return 'wifi' == $clientAdditionalInformation->connection_type;
        }
        return false;
    }
}
