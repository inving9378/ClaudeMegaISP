<?php

namespace App\Jobs\Mikrotik;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use Illuminate\Support\Facades\Log;

class CheckMikrotikConection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $clientService;
    protected $router;
    protected $mikrotik;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientService)
    {
        $this->clientService = $clientService;
        if (isset($clientService->router)) {
            $this->router = $this->clientService->router;
            $this->mikrotik = $this->router->mikrotik;
        }

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->router->type_of_nas == 'Mikrotik') {
            if ($this->mikrotik) {
                if (!$this->initConnection($this->mikrotik, $this->router->ip_host)){
                    throw new \Exception('No existe conexion con el dispositivo');
                }
                return true;
            }
        }
        throw new \Exception('No existe router');
    }
}
