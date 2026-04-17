<?php

namespace App\Console\Commands\Disabled\Old;

use App\Http\Traits\RouterConnection;
use App\Models\Client;
use App\Models\Router;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ClientLastActivity extends Command
{
    use RouterConnection;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:client_last_activity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualizar ultima actividad del cliente';

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
        $routers = Router::where('type_of_nas', 'Mikrotik')->get();
        foreach ($routers as $router) {
            $mikrotik = $router->mikrotik;
            $routerIp = $router->ip_host;
            $connection = $this->initConnection($mikrotik, $routerIp);
            $userListActives = $this->getAllClientPppWithActiveConnection($connection);
            $users_name = $userListActives->pluck('name');
            Client::whereHas('client_main_information', function ($query) use ($users_name) {
                $query->whereIn('user', $users_name);
            })
                ->update(['last_activity' => Carbon::now()->toDateTimeString()]);
        }
    }
}
