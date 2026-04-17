<?php

namespace App\Console\Commands\Scripts;

use App\Models\ClientInternetService;
use App\Services\InformationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddServicesToClientsWhereHasIpAndNotService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-services-to-clients-where-has-ip-and-not-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $informationService = new InformationService();
        $clients = $informationService->getClientsWhereHasIpAndNotInternetService();

        foreach ($clients as $client) {
            $newService = [
                'client_id' => $client->id,
                'internet_id' => 34,
                'client_bundle_service_id' => null,
                'description' => 'Internet creado manual',
                'amount' => 1,
                'unity' => 1,
                'price' => 449,
                'pay_period' => 'Periodo 1',
                'start_date' => '2024-11-13T14:19',
                'finish_date' => null,
                'discount' => false,
                'discount_percent' => null,
                'start_date_discount' => null,
                'end_date_discount' => null,
                'discount_message' => null,
                'estado' => 'Pendiente',
                'router_id' => 2,
                'client_name' => 'Meganet__' . $client->id,
                'user' => 'Meganet__' . $client->id,
                'password' => 'Meganet__' . $client->id,
                'ipv4_assignment' => 'IP Estatica',
                'ipv4' => $client->network_ip[0]->id,
                'additional_ipv4' => null,
                'ipv4_pool' => null,
                'ipv6' => null,
                'delegated_ipv6' => null,
                'mac' => null,
                'portid' => null,
                'payment_type' => null,
                'deferred_payment_in_month' => null,
                'cost_activation' => '0.00',
                'charged' => false,
                'deployed' => false,
            ];
            try {
                ClientInternetService::create($newService);
                Log::info('Servicio agregado manualmente para el Cliente ' . $client->id);
            } catch (Exception $e) {
                // En caso de error, retornar un mensaje o manejar el error según sea necesario
                return $e;
            }
        }
    }
}
