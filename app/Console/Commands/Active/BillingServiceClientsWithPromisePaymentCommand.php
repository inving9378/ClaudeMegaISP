<?php

namespace App\Console\Commands\Active;

use App\Http\Repository\ClientRepository;
use Illuminate\Console\Command;

class BillingServiceClientsWithPromisePaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing_service_client_with_promise_payment_command:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cobra los servicios a los clientes con promesa de Pago Activa';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clientRepository = new ClientRepository();
        $clientWithPromisePayment = $clientRepository->getClientsWithPromisePayment();

        foreach ($clientWithPromisePayment as $client) {
            $clientRepository = new ClientRepository();
            $costAllServices = $clientRepository->getCostAllService($client->id);
            $this->rectifyBalance($client, $costAllServices);
        }
    }


    public function rectifyBalance($client, $costAllServices)
    {
        $balance = $client->balance;
        $amount = $balance->amount;
        $saldoFinal = $amount - $costAllServices;
        $balance->update([
            'amount' => $saldoFinal
        ]);
        activity()->log('Desde el comando BillingServiceClientsWithPromisePaymentCommand saldo actualizado para el cliente con promesa de pago activa ' . $client->id . ' tiene el nuevo balance ' . $saldoFinal);
    }
}
