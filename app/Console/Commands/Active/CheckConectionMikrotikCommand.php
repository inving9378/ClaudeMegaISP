<?php

namespace App\Console\Commands\Active;

use App\Http\Repository\RouterRepository;
use App\Http\Traits\RouterConnection;
use App\Models\Router;
use Illuminate\Console\Command;

class CheckConectionMikrotikCommand extends Command
{
    use RouterConnection;
    protected $signature = 'check_conection_mikrotik:process';
    protected $description = 'Verifica las conexiones con el mikrotik';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

        $routerId = 2;
        $status = $this->getMikrotikStatus($routerId);
        $routerRepository = new RouterRepository();
        if ($status == "ERROR") {
            $routerRepository->sendNotifications($routerId);
        }
    }



    public function getMikrotikStatus($id)
    {
        $router = Router::find($id);
        $mikrotik = $router->mikrotik()->first();
        if ($mikrotik) {
            $device_ip = $router->ip_host;
            $connected = $this->initConnection($mikrotik, $device_ip);
            $status = 'ERROR';
            $versionMikrotik = [];
            if ($connected != null) {
                $versionMikrotik = $this->getVersion($connected);
                $status = isset($versionMikrotik['version']) ? 'OK' : 'ERROR';
            }
            return $status;
        }
        return "ERROR";
    }
}
