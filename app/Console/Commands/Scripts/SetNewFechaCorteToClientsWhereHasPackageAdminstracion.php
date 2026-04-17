<?php

namespace App\Console\Commands\Scripts;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Jobs\Mikrotik\MikrotikRemoveClientServiceFromAddressList;
use App\Models\Client;
use App\Models\ClientMainInformation;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class SetNewFechaCorteToClientsWhereHasPackageAdminstracion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-new-fecha-corte-to-clients-where-has-package-adminstracion';

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
        $clients = Client::whereHas('internet_service', function ($query) {
            $query->whereHas('internet', function ($query) {
                $query->where('title', 'administracion');
            });
        })->with('internet_service')->get();
        foreach ($clients as $client) {
            $client->fecha_corte = "2027-12-31 23:59:59";
            $client->fecha_pago = "2027-12-30 23:59:59";
            $client->fecha_fin_periodo_gracia = null;
            $client->save();
            $clientMainInformation = ClientMainInformation::where('client_id', $client->id)->first();
            if ($clientMainInformation->isNotActive()) {
                $clientMainInformation->estado = ComunConstantsController::STATE_ACTIVE;
                $clientMainInformation->save();
            }
            foreach ($client->internet_service as $service) {
                MikrotikRemoveClientServiceFromAddressList::dispatch($service);
            }
            activity()->tap(function(Activity $activity) use ($client) {
                $activity->client_id = $client->id;
            })->log('Cliente #' . $client->id . ' se establece nueva fecha de corte y de pago hasta el por el 2027 porque tiene paquete de administracion');
        }
    }
}
