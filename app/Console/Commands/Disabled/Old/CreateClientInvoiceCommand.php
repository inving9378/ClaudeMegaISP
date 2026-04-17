<?php

namespace App\Console\Commands\Disabled\Old;

use App\Http\Repository\ClientRepository;
use App\Models\Client;
use Illuminate\Console\Command;

class CreateClientInvoiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create_client_invoice_command:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea facturas para los diferentes tipos de clientes recurrentes y custom sin pasar parametros';
    protected $clientRepository;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(ClientRepository $clientRepository)
    {

        $this->clientRepository = $clientRepository;
        $clients = Client::with(
            [
                'client_main_information',
                'billing_configuration',
                'bundle_service',
                'internet_service' => function ($query) {
                    $query->servicioNoPerteneceAUnPaquete();
                },
                'voz_service' => function ($query) {
                    $query->servicioNoPerteneceAUnPaquete();
                },
                'custom_service' => function ($query) {
                    $query->servicioNoPerteneceAUnPaquete();
                }
            ]
        )
            ->tipoRecurrenteYElDiaDeFacturacionSeaHoy()
            ->oSeaDeTipoCustomYTocaFacturarHoy()
            ->get();

        if ($clients) {
            foreach ($clients as $client) {
                if ($client) {
                    $this->clientRepository->addInvoiceService($client->id, false);
                }
            }
        }
    }
}
