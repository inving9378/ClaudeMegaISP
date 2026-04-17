<?php

namespace App\Console\Commands\Disabled\Old;

use App\Http\Traits\RouterConnection;
use App\Models\Network;
use Illuminate\Console\Command;

class CreatePoolInMikrotikCommand extends Command
{
    use  RouterConnection;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'createpoolinmikrotik:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Despliega los pools creados en bases de datos en el Mikrotik dada la ubicación';

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
        $pools = Network::with('location','location.router')
        ->wherehas('location.router')
        ->get();

        if ($pools) {
            foreach ($pools as $pool) {
                $this->isNotExistPoolInMikrotik($pool->location->router->id, $pool->id);
            }
        }

    }
}
