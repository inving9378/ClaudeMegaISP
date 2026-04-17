<?php

namespace App\Jobs\Mikrotik;

use App\Models\Router;
use App\Models\Mikrotik;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use Illuminate\Support\Facades\Log;

class MicrotikDeleteRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,RouterConnection;

    protected $mikrotik;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Mikrotik $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $router = Router::find($this->mikrotik->router_id);
        if ($this->mikrotik) {
            $device_login = $this->mikrotik->login_api;
            $device_password = $this->mikrotik->password_api;
            $device_port = $this->mikrotik->port_api;
            $device_ip = $router->ip_host;

            $connected = $this->connection(
                $device_ip,
                $device_login,
                $device_password,
                $device_port);

            $command = '/ip/firewall/filter/';
            $this->removeById($connected, $command,
            $this->getIdByComment($connected, $command, 'MgNet_BLOQUEA_CLIENTES_MOROSOS_ACCEPT_DNS_SRC'));
            $this->removeById($connected, $command,
            $this->getIdByComment($connected, $command, 'MgNet_BLOQUEA_CLIENTES_MOROSOS_ACCEPT_DNS_DST'));
            $this->removeById($connected, $command,
            $this->getIdByComment($connected, $command, 'MgNet_INPUT_MEGANET_TO_API_ACCEPT'));
            $this->removeById($connected, $command,
                $this->getIdByComment($connected, $command, 'MgNet_BLOQUEA_CLIENTES_MOROSOS_DROP'));
            $this->removeById($connected, $command,
                $this->getIdByComment($connected, $command, 'MgNet_FORDWARE_DROPEA_INVALIDAS'));
            $this->removeById($connected, $command,
                $this->getIdByComment($connected, $command, 'MgNet_INPUT_DROPEAR_INVALIDAS'));
            $this->removeById($connected, $command,
                $this->getIdByComment($connected, $command, 'MgNet_INPUT_ESTABLECIDAS_RELACIONADAS'));
            $this->removeById($connected, $command,
                $this->getIdByComment($connected, $command, 'MgNet_FORWARD_ESTABLECIDAS_RELACIONADAS'));
            $this->removeById($connected, $command,
                $this->getIdByComment($connected, $command, 'MgNet_INPUT_DROPEA_EL_RESTO'));

                $this->removeById($connected, '/ip/firewall/nat/',
                $this->getIdByComment($connected,  '/ip/firewall/nat/', 'MgNet_REDIRECT_MOROSOS_TO_WEB_PROXY'));
        }


    }
}
