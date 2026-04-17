<?php

namespace App\Console\Commands\Disabled;

use App\Http\Traits\RouterConnection;
use App\Models\MikrotikItemToExcecuteAction;
use App\Models\Router;
use Illuminate\Console\Command;

class ActionPoolInMikrotikCommand extends Command
{
    use RouterConnection;
    protected $secrcetIpOld;
    protected $networkIp;
    protected $clientInternetService;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'actionpoolinmikrotik:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina o actualiza Pools en los mikrotik dada las ubicaciones';

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
        foreach (MikrotikItemToExcecuteAction::all() as $v) {
            if ($v->model == 'Network') {
                $this->ActionOverPools($v);
            }
            if ($v->model == 'MikrotikArray') {
                $this->ActionOverMikrotik($v);
            }
            if ($v->model == 'MikrotikConfig') {
                $this->ActionOverMikrotikConfig($v);
            }
        }
    }

    public function ActionOverPools($v)
    {
        foreach (Router::where(
            'location_id',
            json_decode($v->value)->location_id
        )->get()
            as $router) {
            $poolName = json_decode($v->value)->title;
            $poolComment = json_decode($v->value)->comment;
            $isDeleting = $v->action == 'delete';
            $isUpdate = $v->action == 'update';

            if ($router) {
                $device_ip = $router->ip_host;
                $mikrotik = $router->mikrotik();
                if ($mikrotik) {
                    $connected = $this->initConnection($mikrotik, $device_ip);

                    if ($connected && $isDeleting) {
                        if (
                            $this->getIdByName($connected, $v->place, $poolName)
                        ) {
                            $this->removeById(
                                $connected,
                                $v->place,
                                $this->getIdByName(
                                    $connected,
                                    $v->place,
                                    $poolName
                                )
                            );
                        }
                        $v->delete();
                    }

                    if ($connected && $isUpdate) {
                        if (
                            $this->getIdByComment(
                                $connected,
                                $v->place,
                                $poolComment
                            )
                        ) {
                            $this->setvalueArrayById(
                                $connected,
                                $v->place,
                                $this->getIdByComment(
                                    $connected,
                                    $v->place,
                                    $poolComment
                                ),
                                ['name' => $poolName]
                            );
                        }
                        $v->delete();
                    }
                }
            }
        }
    }

    public function ActionOverMikrotik($v)
    {
        $isDeleting = $v->action == 'delete';
        $isUpdate = $v->action == 'update';
        $router = Router::find($v->place); // En el campo obtenermos el id del router
        $device_ip = $router->ip_host;

        if ($router) {
            $mikrotik = $router->mikrotik()->first();
            if ($mikrotik) {
                $connected = $this->initConnection($mikrotik, $device_ip);
                if ($connected && $isUpdate) {
                    $this->updateNatRulesMorososRedirct($connected, $router);
                    $this->removeAll($connected, '/ip/proxy/access/');
                    $this->addWebProxyAccessIpRedirect($connected, json_decode($v->value)->ip_redirect, $router);
                    $this->addWebProxyAccessUrlRedirect($connected, json_decode($v->value)->url_redirect);
                    $this->addWebProxyAccessIpsPermited($connected, json_decode($v->value)->ips_with_comma_permited);
                    $v->delete();
                }
            }
        }
    }

    public function ActionOverMikrotikConfig($v)
    {
        $isUpdate = $v->action == 'update';
        $origin = json_decode($v->origin);
        $mikrotikConfig = json_decode($v->value);
        $router = Router::find(json_decode($v->value)->router_id);
        $device_ip = $router->ip_host;
        if ($router) {
            $mikrotik = $router->mikrotik()->first();
            if ($mikrotik) {
                $connected = $this->initConnection($mikrotik, $device_ip);
                if ($connected && $isUpdate) {
                    $this->updateRulesInputApiAccept($connected, $router->port_api, $mikrotikConfig);
                    $this->updatePpoeProfile($connected, $mikrotikConfig, $origin->mikrotik_config_server_pppoe_profile);
                    $this->updateServerPppoe($connected, $mikrotikConfig, $origin->mikrotik_config_server_pppoe_name);

                    $v->delete();
                }
            }
        }
    }

    public function _isDisable($disable)
    {
        $disable == 'Activado' || $disable == 'Nuevo' || $disable == 'Pendiente'
            ? ($disable = 'no')
            : ($disable = 'yes');
        return $disable;
    }

    public function _priority($priotity)
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

    public function setNewQueuefromJson($jsonString)
    {
        $json = json_decode($jsonString, true);
        return [
            'name' => $json['name'],
            'comment' => $json['comment'],
            'target' => $json['target'],
            'queue' => $json['queue'],
            'total-queue' => $json['total-queue'],
            'parent' => $json['parent'],
            'priority' => $json['priority'],
            'limit-at' => $json['limit-at'],
            'burst-limit' => $json['burst-limit'],
            'max-limit' => $json['max-limit'],
            'burst-time' => $json['burst-time'],
            'burst-threshold' => $json['burst-threshold'],
        ];
    }

    public function isDisable($disable)
    {
        $disable == 'Activado' || $disable == 'Activo' || $disable == 'Nuevo' ? $disable = 'no' : $disable = 'yes';
        return $disable;
    }
}
