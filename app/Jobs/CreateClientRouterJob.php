<?php

namespace App\Jobs;

use App\Http\Repository\RouterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use App\Services\NetworkIpService;

class CreateClientRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $model;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->create();
    }

    public function create()
    {
        $routerRepository = new RouterRepository();
        $router = $routerRepository->getRouterById($this->model->router_id);
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

            if ($connected && $this->model->isIpv4AssigmentStatic()) {
                $this->createClientPPoe($connected);
            }

            if ($connected && $this->model->isIpv4AssigmentPoolIp()) {
                $this->createClientHotspot($connected);
            }
        }
    }

    public function createClientPPoe($connected)
    {
        $networkIpService = new NetworkIpService($this->model);
        $type = class_basename(get_class($this->model));

        $networkIp = $networkIpService->getIpFilterByPlanId($this->model->id, $type);
        $nameClient = $this->model->getNameClient();
        $idByName = $this->getIdByName($connected, '/ppp/secret/', $nameClient);
        $password = $this->model->password ?? 'Meganet-' . $this->model->id;
        $comment =  'Meganet_' . $this->model->id;
        if (!$idByName) {
            $this->addItem($connected, '/ppp secret ', [
                'name' =>  $nameClient,
                'password' => $password,
                'service' => 'any',
                'profile' => 'default',
                'remote-address' => $networkIp,
                'comment' => $comment
            ]);
        }
    }

    public function createClientHotspot($connected)
    {
        $networkIpService = new NetworkIpService($this->model);
        $type = class_basename(get_class($this->model));
        $networkIp = $networkIpService->getIpFilterByPlanId($this->model->id, $type);
        $nameClient = $this->model->getNameClient();
        $idByName = $this->getIdByName($connected, '/ip/hotspot/user/', $nameClient);
        $password = $this->model->password;
        $comment =  'Meganet-' . $this->model->id;
        if (!$idByName) {
            $this->addItem($connected, '/ip hotspot user ', [
                'name' =>  $nameClient,
                'password' => $password,
                'profile' => 'default',
                'comment' => $comment,
                'address' => $networkIp,
                'server' => 'all',
            ]);
        }
    }
}
