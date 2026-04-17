<?php

namespace App\Jobs;

use App\Http\Repository\ClientRepository;
use App\Http\Repository\RouterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Services\LogService;
use Illuminate\Support\Facades\Log;

class DeletedClientInRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $clientInternetService;
    protected $routerRepository;
    protected $clientInternetServiceRepository;
    protected $clientUserRepository;
    protected $model;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($model)
    {
        $this->model =  $model;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->routerRepository = new RouterRepository();
        $router = $this->routerRepository->getRouterById($this->model->router_id);
        $mikrotik = $router->mikrotik()->first();
        $nameClient = $this->model->getNameClient();
        if ($mikrotik && $this->model->client()->first()) {
            $connected = $this->initConnection($mikrotik, $router->ip_host);

            try {
                $id = $this->getIdByName($connected, '/ppp/secret/', $nameClient);
                $this->removeById(
                    $connected,
                    '/ppp/secret/',
                    $id
                );
                $this->model->client()->first()->mikrotik_client_ppoe()->delete();
            } catch (\Exception $exception) {
                Log::info($exception);
            }

            if ($this->model->isIpv4AssigmentPoolIp()) {
                try {
                    $id = $this->getIdByName($connected, '/ip/hotspot/user/', $nameClient);
                    $this->removeById(
                        $connected,
                        '/ip/hotspot/user/',
                        $id
                    );
                    $this->model->client()->first()->mikrotik_client_hostpot_user()->delete();
                } catch (\Exception $exception) {
                    Log::info($exception);
                }
            }

            $this->deleteClientAddressList();
        }
    }

    public function deleteClientAddressList()
    {
        MikrotikRemoveClientServiceFromAddressList::dispatch($this->model);
        $client = (new ClientRepository)->getClientById($this->model->client_id);
        $logService = new LogService();
        $logService->log($client, 'Su servicio ' . $this->model->service_name . ' fue removido de address_list desde el DeletedClientInRouterJob');
    }
    public function isNameClientSufix($nameClient)
    {
        if (strpos($nameClient, '-')) {
            return true;
        } else {
            return false;
        }
    }
}
