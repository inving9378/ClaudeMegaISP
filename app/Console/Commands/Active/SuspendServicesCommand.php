<?php

namespace App\Console\Commands\Active;

use App\Services\ClientService\SuspendService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SuspendServicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suspends_services_early_in_the_day:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'suspende los servicios que el tiempo de expiracion es hoy o ya paso y no tienen periodo de gracia a la hora que este programada';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected $clientRepository;

    public function handle()
    {
        $suspendService = new SuspendService();
        $suspendService->suspendClientsServices();
        Log::info("Comando de suspende servicios ejecutado");
    }
}
