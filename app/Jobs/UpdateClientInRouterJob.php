<?php

namespace App\Jobs;

use App\Http\Repository\ClientInternetServiceRepository;
use App\Models\ClientInternetService;
use App\Models\ClientMainInformation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Http\Traits\RouterConnection;
use App\Models\Router;

class UpdateClientInRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $clientMainInformation;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ClientMainInformation $clientMainInformation)
    {
        $this->clientMainInformation = $clientMainInformation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client_id = $this->clientMainInformation->client_id;


        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        //TODO Revisar si este Job se usa en Algun lado

        $router_id = ClientInternetService::where('client_id', '=', $client_id)->first()->router_id;
        $router = Router::find($router_id);
        $mikrotik = $router->mikrotik()->first();

        if ($mikrotik) {
            $device_login = $mikrotik->login_api;
            $device_password = $mikrotik->password_api;
            $device_port = $mikrotik->port_api;
            $device_ip = $router->ip_host;

            $login = $this->clientMainInformation->user;
            $password = $this->clientMainInformation->password;
            $disabled = $this->clientMainInformation->status;
            $name = $this->clientMainInformation->name;

            $this->updateClientePpoe(
                $device_ip,
                $device_login,
                $device_password,
                $device_port,
                $name,
                $login,
                $password,
                $disabled
            );

        }
    }

    public function updateClientePpoe(
        $device_ip,
        $device_login,
        $device_password,
        $device_port,
        $name,
        $login,
        $password,
        $disabled
    )
    {
        $connected = $this->connection(
            $device_ip,
            $device_login,
            $device_password,
            $device_port);

        $dataUserbyUpdate = ([
            'name' => 'ppoe-client-Meganet-' .$name,
            'user' => $login,
            'password' => $password,
            'interface' => 'ether2',
            'service-name' => null,
            'disabled' => $this->isDisable($disabled),
        ]);

        $this->setvalueArrayById($connected, '/interface/pppoe-client/',
            $this->getIdByName($connected,'/interface/pppoe-client/','ppoe-client-Meganet-'.$name),
            $dataUserbyUpdate
        );
        Log::info('Mikrotik user actualizado');
    }

    public function isDisable($disable)
    {
        $disable == 'Activado' || $disable == 'Activo' ? $disable = 'no' : $disable = 'yes';
        return $disable;
    }


}
