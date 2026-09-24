<?php

namespace App\Console\Commands\Active;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Jobs\SuspendServiceJob;
use App\Modules\Core\Clientes\Models\ClientBillingPause;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use App\Modules\Core\Clientes\Services\BillingExpirationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * Job diario de la pausa de facturación programada (Etapa 2 del feature, 2026-09-24):
 * (a) inicia las pausas `programada` cuyo fecha_inicio ya llegó — suspende el servicio y
 *     pasa a en_curso; (b) concluye las `en_curso` cuyo fecha_fin ya llegó — reactiva el
 *     servicio, recalcula fecha_corte/fecha_pago para que invoice:create-proformas la
 *     retome normal en su próxima corrida, y pasa a concluida; (c) cancela las
 *     `esperando_pago` cuyo fecha_inicio ya llegó sin que la cuota se haya pagado (Regla 9
 *     del spec: "si no se paga antes de la fecha de inicio, la pausa se cancela").
 *
 * Deliberadamente NO genera la proforma directamente al concluir — se apoya en que
 * invoice:create-proformas corre después (02:00 este comando, 03:00 esa) y ya la
 * generará normal en cuanto el cliente deje de estar en_curso y su fecha_corte recalculada
 * caiga en el período correspondiente. Evita duplicar esa lógica.
 */
class BillingPauseProcessCommand extends Command
{
    protected $signature = 'billing:procesar-pausas';
    protected $description = 'Inicia y concluye las pausas de facturación programadas del día';

    public function handle()
    {
        $this->cancelarEsperandoPagoVencidas();
        $this->iniciarProgramadas();
        $this->concluirEnCurso();
    }

    private function cancelarEsperandoPagoVencidas(): void
    {
        $pausas = ClientBillingPause::where('estado', ClientBillingPause::ESTADO_ESPERANDO_PAGO)
            ->where('fecha_inicio', '<=', Carbon::now())
            ->get();

        foreach ($pausas as $pausa) {
            try {
                $pausa->update(['estado' => ClientBillingPause::ESTADO_CANCELADA]);

                activity()->tap(function (Activity $activity) use ($pausa) {
                    $activity->client_id = $pausa->client_id;
                })->log("Pausa de facturación #{$pausa->id} cancelada: la cuota de conservación no se pagó antes de la fecha de inicio.");

                $this->info("Pausa #{$pausa->id} (cliente {$pausa->client_id}) cancelada por falta de pago de la cuota.");
            } catch (\Throwable $e) {
                Log::error("[billing:procesar-pausas] Error cancelando pausa #{$pausa->id}: " . $e->getMessage());
                $this->error("Error con pausa #{$pausa->id}: " . $e->getMessage());
            }
        }
    }

    private function iniciarProgramadas(): void
    {
        $pausas = ClientBillingPause::where('estado', ClientBillingPause::ESTADO_PROGRAMADA)
            ->where('fecha_inicio', '<=', Carbon::now())
            ->get();

        foreach ($pausas as $pausa) {
            try {
                $this->suspenderServiciosDelCliente($pausa->client_id);

                $pausa->update(['estado' => ClientBillingPause::ESTADO_EN_CURSO]);

                activity()->tap(function (Activity $activity) use ($pausa) {
                    $activity->client_id = $pausa->client_id;
                })->log("Pausa de facturación #{$pausa->id} iniciada: servicio suspendido, sin cobro por {$pausa->meses} " . ($pausa->meses == 1 ? 'mes' : 'meses') . '.');

                $this->info("Pausa #{$pausa->id} (cliente {$pausa->client_id}) iniciada.");
            } catch (\Throwable $e) {
                Log::error("[billing:procesar-pausas] Error iniciando pausa #{$pausa->id}: " . $e->getMessage());
                $this->error("Error con pausa #{$pausa->id}: " . $e->getMessage());
            }
        }
    }

    private function concluirEnCurso(): void
    {
        $pausas = ClientBillingPause::where('estado', ClientBillingPause::ESTADO_EN_CURSO)
            ->where('fecha_fin', '<=', Carbon::now())
            ->get();

        foreach ($pausas as $pausa) {
            try {
                $this->reactivarServiciosDelCliente($pausa->client_id);
                $this->recalcularFechasDeFacturacion($pausa->client_id, $pausa->fecha_fin);

                $pausa->update([
                    'estado' => ClientBillingPause::ESTADO_CONCLUIDA,
                    'fecha_fin_real' => $pausa->fecha_fin,
                ]);

                activity()->tap(function (Activity $activity) use ($pausa) {
                    $activity->client_id = $pausa->client_id;
                })->log("Pausa de facturación #{$pausa->id} concluida: servicio reactivado, facturación normal reanudada.");

                $this->info("Pausa #{$pausa->id} (cliente {$pausa->client_id}) concluida.");
            } catch (\Throwable $e) {
                Log::error("[billing:procesar-pausas] Error concluyendo pausa #{$pausa->id}: " . $e->getMessage());
                $this->error("Error con pausa #{$pausa->id}: " . $e->getMessage());
            }
        }
    }

    private function suspenderServiciosDelCliente(int $clientId): void
    {
        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getServicesForClient($clientId);

        foreach (ComunConstantsController::ALL_CLIENT_SERVICE as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                SuspendServiceJob::dispatchSync($clientService);
            }
        }
    }

    private function reactivarServiciosDelCliente(int $clientId): void
    {
        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getServicesForClient($clientId);

        foreach (ComunConstantsController::ALL_CLIENT_SERVICE as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                $repository = $clientService->getRepository();
                (new $repository())->setDeployedTrueAndActiveService($clientService);
            }
        }
    }

    /**
     * Recorre fecha_pago y fecha_corte al terminar la pausa, para que
     * invoice:create-proformas retome al cliente normal en su siguiente corrida.
     * Se fija fecha_pago = fecha_fin de la pausa (equivalente a "como si hubiera
     * renovado el día que termina la pausa") y de ahí se deriva fecha_corte con el
     * servicio existente, respetando el tipo de facturación real del cliente.
     */
    private function recalcularFechasDeFacturacion(int $clientId, $fechaFinPausa): void
    {
        $clientRepository = new ClientRepository();
        $client = $clientRepository->getClientById($clientId);

        $client->fecha_pago = Carbon::parse($fechaFinPausa)->toDateTimeString();
        $client->fecha_corte = Carbon::parse($fechaFinPausa)->toDateTimeString();
        $client->save();

        $client->refresh();
        (new BillingExpirationService($client))->setNewFechaCorteForClient(null, 1, true, false);
    }
}
