<?php

namespace App\Console\Commands\Active;

use App\Services\ClientService\ClientBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BillingServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing_service_command:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cobra los servicios que No están pagados, tienen dinero en la cuenta y el dia de facturación es hoy o ya pasó, los activa y crea una factura';

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
        $billingService = new ClientBillingService();
        $billingService->billingClientServices();
         Log::info("Comando de Cobrar servicios ejecutado");
    }
}
