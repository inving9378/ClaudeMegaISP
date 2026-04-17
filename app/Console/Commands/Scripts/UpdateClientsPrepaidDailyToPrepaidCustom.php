<?php

namespace App\Console\Commands\Scripts;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\PaymentRepository;
use App\Jobs\Mikrotik\MikrotikCreateAddressList;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Models\Client;
use App\Models\TypeBilling;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class UpdateClientsPrepaidDailyToPrepaidCustom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-clients-prepaid-daily-to-prepaid-custom';

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
        $clientsDaily = Client::with('payments')->whereHas('payments')->whereHas('client_main_information', function ($query) {
            $query->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY);
        })->get();
        foreach ($clientsDaily as $client) {
            $PaymentRepository = new PaymentRepository();
            $lastPayment = $PaymentRepository->obtenerLaFechaDelUltimoPagoByClientId($client->id);
            $dateMonthPass = Carbon::now()->subMonth()->subDay()->toDateTimeString();
            if ($lastPayment > $dateMonthPass) {
                $client->billing_configuration()->update([
                    'type_billing_id' => TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM
                ]);
                $client->client_main_information->type_of_billing_id = TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM;
                $client->client_main_information->estado = ComunConstantsController::STATE_ACTIVE;
                $client->client_main_information->save();
                $client->balance->amount = 0;
                $client->balance->save();
                $clientInternetServiceRepository = new ClientInternetServiceRepository();
                $allServiceInternet = $clientInternetServiceRepository->getServiceFilterByClientId($client->id);
                foreach($allServiceInternet as $service){
                    MikrotikRemoveClientServiceFromAddressList::dispatch($service);
                }
                activity()->tap(function (Activity $activity) use ($client) {
                    $activity->client_id = $client->id;
                })->log('Cliente # ' . $client->id . ' se ha cambiado el tipo de facturacion a Personalizado y se le ha cambiado el balance a 0 y se le ha activado los servicios de Internet desde el scrip UpdateClientsPrepaidDailyToPrepaidCustom.');
            } else {
                $client->billing_configuration()->update([
                    'type_billing_id' => TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM
                ]);
                $client->client_main_information->type_of_billing_id = TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM;
                $client->client_main_information->estado = ComunConstantsController::STATE_BLOCKED;
                $client->client_main_information->save();
                $client->balance->amount = 0;
                $client->balance->save();
                $clientInternetServiceRepository = new ClientInternetServiceRepository();
                $allServiceInternet = $clientInternetServiceRepository->getServiceFilterByClientId($client->id);
                foreach($allServiceInternet as $service){
                    MikrotikCreateAddressList::dispatch($service);
                }

                activity()->tap(function (Activity $activity) use ($client) {
                    $activity->client_id = $client->id;
                })->log('Cliente # ' . $client->id . ' se ha cambiado el tipo de facturacion a Personalizado y se le ha cambiado el balance a 0 y se le ha bloqueado los servicios de Internet desde el scrip UpdateClientsPrepaidDailyToPrepaidCustom.');


            }
           /*  $costAllService = (new ClientRepository())->getCostAllService($client->id);
            if ($client->balance->amount > $costAllService) {
                $clientesQueSuSaldoEsMayorQueElCOsto[] = $client->id;
            } */
        }
    }
}
