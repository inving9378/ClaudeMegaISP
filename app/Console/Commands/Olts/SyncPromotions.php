<?php

namespace App\Console\Commands\Olts;

use App\Models\ClientPlanPromotion;
use App\Models\OltOnu;
use App\Services\OLTsService;
use Illuminate\Console\Command;

class SyncPromotions extends Command
{
    protected $signature = 'smartolt:sync-promotions';

    protected $description = 'Para volver a los perfiles de velocidad originales aquellas ofertas que caducaron';

    public function handle()
    {
        $caduced = ClientPlanPromotion::caduced()->active()->get();
        if (count($caduced) === 0) {
            $this->info('No existen promociones caducadas');
            return;
        }
        $service = new OLTsService();
        foreach ($caduced as $c) {
            try {
                $this->info('Tratando de cerrar la promoción con Id: ' . $c->id);
                $onu = OltOnu::firstWhere('client_id', $c->client_id);
                if ($onu) {
                    if (isset($onu->unique_external_id)) {
                        $portData = $onu->service_ports;
                        if (isset($portData) && !empty($portData)) {
                            $response = $service->updateSpeedProfile($onu->unique_external_id, [
                                'upload_speed_profile_name' => $c->before_upload_profile,
                                'download_speed_profile_name' => $c->before_download_profile
                            ]);
                            if ($response['success']) {
                                $service->syncOnu($onu);
                                $c->status = 'closed';
                                $c->save();
                                $this->info('Promoción cerrada correctamente');
                            } else {
                                $this->error(sprintf('%s. Id de promoción: %d', $response->error ?? $response->message ?? 'Error al tratar de cerrar la promoción', $c->id));
                            }
                            sleep(5);
                        } else {
                            $this->error(sprintf('No se permite la operación solicitada. La ONU de este cliente no tiene configurado los puertos de servicio. Id de promoción: %d', $c->id));
                        }
                    } else {
                        $this->error(sprintf('No se permite la operación solicitada. La ONU de este cliente no tiene configurado el ID externo. Id de promoción: %d', $c->id));
                    }
                }
            } catch (\Throwable $th) {
                continue;
            }
        }
    }
}
