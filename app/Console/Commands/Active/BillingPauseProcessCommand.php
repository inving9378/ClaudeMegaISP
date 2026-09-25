<?php

namespace App\Console\Commands\Active;

use App\Modules\Core\Clientes\Models\ClientBillingPause;
use App\Modules\Core\Clientes\Services\BillingPauseService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * Job diario de la pausa de facturación programada (Etapa 2 del feature, 2026-09-24):
 * (a) activa las `esperando_pago` cuyo balance ya cubre la cuota (Opción A: se detecta por
 *     delta de balance, no por una factura — ver BillingPauseService::crearPausa()); (b) inicia
 *     las `programada` cuyo fecha_inicio ya llegó — suspende el servicio y pasa a en_curso;
 *     (c) concluye las `en_curso` cuyo fecha_fin ya llegó — reactiva el servicio, recalcula
 *     fecha_corte/fecha_pago para que invoice:create-proformas la retome normal en su próxima
 *     corrida, y pasa a concluida; (d) cancela las `esperando_pago` cuyo fecha_inicio ya llegó
 *     sin que la cuota se haya pagado (Regla 9 del spec).
 *
 * Deliberadamente NO genera la proforma directamente al concluir — se apoya en que
 * invoice:create-proformas corre después (02:00 este comando, 03:00 esa) y ya la
 * generará normal en cuanto el cliente deje de estar en_curso y su fecha_corte recalculada
 * caiga en el período correspondiente. Evita duplicar esa lógica.
 *
 * La lógica de suspender/reactivar servicios y recalcular fechas vive en BillingPauseService
 * (fuente única) — este comando solo orquesta el recorrido diario, y BillingPauseService::
 * reanudarAnticipada() (acción manual desde la UI) reusa exactamente los mismos métodos.
 */
class BillingPauseProcessCommand extends Command
{
    protected $signature = 'billing:procesar-pausas';
    protected $description = 'Inicia y concluye las pausas de facturación programadas del día';

    public function __construct(private BillingPauseService $pauseService)
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->activarPorPagoDeCuota();
        $this->cancelarEsperandoPagoVencidas();
        $this->iniciarProgramadas();
        $this->concluirEnCurso();
    }

    private function activarPorPagoDeCuota(): void
    {
        $pausas = ClientBillingPause::where('estado', ClientBillingPause::ESTADO_ESPERANDO_PAGO)
            ->whereNotNull('balance_al_crear')
            ->get();

        foreach ($pausas as $pausa) {
            try {
                if ($this->pauseService->cuotaYaFuePagada($pausa)) {
                    $this->pauseService->activarPorPagoDeCuota($pausa);
                    $this->info("Pausa #{$pausa->id} (cliente {$pausa->client_id}) activada: cuota de conservación pagada.");
                }
            } catch (\Throwable $e) {
                Log::error("[billing:procesar-pausas] Error activando pausa #{$pausa->id} por pago de cuota: " . $e->getMessage());
                $this->error("Error con pausa #{$pausa->id}: " . $e->getMessage());
            }
        }
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
                $this->pauseService->suspenderServiciosDelCliente($pausa->client_id);

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
                $this->pauseService->reactivarServiciosDelCliente($pausa->client_id);
                $this->pauseService->recalcularFechasDeFacturacion($pausa->client_id, $pausa->fecha_fin);

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
}
