<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Network;
use Illuminate\Support\Facades\Log;


class CreateNetWorkIpRowsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $network;
    protected $ips;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Network $network,$ips)
    {
        $this->network = $network;
        $this->ips = $ips;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->ips as $ip){ 
            $this->network->network_ip()->create([
                'ip' => $ip,
                'used' => false,
                'used_by' => '--',
                'title' => '',
                'location_id' => $this->network->location_id,
                'ping' => 'Desconocido',
                'host_category' => 'Ninguno',
            ]);
        }
    }
}
