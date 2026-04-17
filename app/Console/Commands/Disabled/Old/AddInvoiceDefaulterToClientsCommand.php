<?php

namespace App\Console\Commands\Disabled\Old;

use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientRepository;
use App\Models\TypeBilling;
use Illuminate\Console\Command;

class AddInvoiceDefaulterToClientsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addinvoicedefaultertoclients:process';
    private $clientRepository;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Agregue Factura Morosa a Clientes con Prepagos Personalizados cuando no paguen a tiempo y excedan un mes y 1 día';

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
    public function handle(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;

        $clientIternetServiceRepository = new ClientInternetServiceRepository();

        $clientServices = $clientIternetServiceRepository
            ->obtenerServiciosDeInternetActivosDesplegadosYPagadosYNoPertenecenAPaquetes()
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->isClientTypeOfBilling(TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY)
                        ->getClientDontHaveClientPaymentToday()
                        ->getClientDontHaveTransactionToday()
                        ->getClientDontHaveInvoiceTypeSurchargeDefaulter();
                })
                    //Custom
                    ->orWhere(function ($query) {
                        $query->isClientTypeOfBilling(TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM)
                            ->whereDoesntHave('client_payment_service', function ($query) {
                                $query->whereRaw('DATE(created_at) >= DATE_ADD(DATE_SUB(now(), INTERVAL 1 MONTH), INTERVAL 1 DAY)');
                            })
                            ->getClientDontHaveInvoiceTypeSurchargeDefaulter();
                    });
            })
            ->get();

        $this->ifNotClientRecurrentAndAsLogTimeToAddInvoiceDefaulter($clientServices);
    }

    public function ifNotClientRecurrentAndAsLogTimeToAddInvoiceDefaulter($clientServices)
    {
        foreach ($clientServices as $clientService) {
            if ($clientService->count()) {
                $client = $clientService->client;
                $cost = 99.0;

                $newBalanceAndPrice = [
                    'new_balance' =>  $client->balance->amount - $cost,
                    'price' => $cost,
                    'cost' => $cost,
                    'payment_in_time' => null
                ];

                $invoice = $this->clientRepository->addInvoiceDefaulter($client, $client->balance->amount - $cost >= 0,  $cost);
                $client->balance()->update(['amount' => $newBalanceAndPrice['new_balance']]);
                $this->clientRepository->addDebitTransactionForPaymentDefaulter($client, $cost, $newBalanceAndPrice, $invoice);
            }
        }
    }
}
