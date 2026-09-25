<?php

namespace App\Modules\Core\Clientes\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Jobs\SuspendServiceJob;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientBillingPause;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use App\Modules\Core\Clientes\Services\BillingExpirationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Reglas de elegibilidad para la pausa de facturación programada (decididas por Irving,
 * 2026-09-24). No toca facturación real todavía (eso es Etapa 2) — aquí solo se decide
 * si un cliente PUEDE pedir una pausa y con qué duración máxima.
 */
class BillingPauseService
{
    public const MESES_MIN_ENTRE_PAUSAS = 6;
    public const MAX_PAUSAS_EN_12_MESES = 2;
    public const MAX_MESES_PAUSADOS_EN_12_MESES = 6;
    public const MAX_MESES_SIN_CUOTA = 3;
    public const MAX_MESES_CON_CUOTA = 6;
    public const CUOTA_MENSUAL = 99.00;

    public function puedePausar(Client $client): ResultadoElegibilidadPausa
    {
        $client->loadMissing('client_main_information', 'balance');

        // Regla 7a — cliente Activo
        $estado = $client->client_main_information->estado ?? null;
        if ($estado !== ComunConstantsController::STATE_ACTIVE) {
            return $this->noElegible("El cliente no está Activo (estado actual: " . ($estado ?? 'sin estado') . ").");
        }

        // Regla 7b — sin saldo pendiente (balance negativo = adeudo)
        $balance = (float) ($client->balance->amount ?? 0);
        if ($balance < 0) {
            return $this->noElegible('El cliente tiene saldo pendiente (adeudo).');
        }

        // Regla 7c — sin otra pausa programada o en curso
        $pausaViva = ClientBillingPause::where('client_id', $client->id)
            ->whereIn('estado', ClientBillingPause::ESTADOS_ACTIVOS)
            ->exists();
        if ($pausaViva) {
            return $this->noElegible('Ya existe una pausa programada o en curso para este cliente.');
        }

        // Regla 4 — periodo mínimo de 6 meses facturados desde que terminó su última pausa.
        // "Terminó" = concluida (fin normal) o reanudada_anticipada (fin adelantado); una
        // pausa cancelada (Regla 8) nunca llega a iniciar, así que no cuenta aquí.
        $ultimaPausaCerrada = ClientBillingPause::where('client_id', $client->id)
            ->whereIn('estado', [ClientBillingPause::ESTADO_CONCLUIDA, ClientBillingPause::ESTADO_REANUDADA_ANTICIPADA])
            ->orderByDesc('fecha_fin_real')
            ->orderByDesc('fecha_fin')
            ->first();

        if ($ultimaPausaCerrada) {
            $finReal = $ultimaPausaCerrada->fecha_fin_real ?? $ultimaPausaCerrada->fecha_fin;
            $disponibleDesde = Carbon::parse($finReal)->addMonthsNoOverflow(self::MESES_MIN_ENTRE_PAUSAS);

            if (Carbon::now()->lt($disponibleDesde)) {
                return $this->noElegible(
                    'Debe facturar ' . self::MESES_MIN_ENTRE_PAUSAS . ' meses desde su última pausa antes de pedir otra.',
                    $disponibleDesde
                );
            }
        }

        // Regla 5 — topes en ventana móvil de 12 meses (máx. 2 pausas, máx. 6 meses pausados).
        // Cuentan las pausas que de verdad se vivieron: en_curso, concluida o reanudada_anticipada
        // (una cancelada nunca inició, Regla 8). Ventana = fecha_inicio dentro de los últimos 12 meses.
        $pausasVentana = ClientBillingPause::where('client_id', $client->id)
            ->whereIn('estado', ClientBillingPause::ESTADOS_CONSUMEN_TOPE)
            ->where('fecha_inicio', '>=', Carbon::now()->subMonthsNoOverflow(12))
            ->get();

        $pausasEn12Meses = $pausasVentana->count();
        $mesesPausadosEn12Meses = $this->sumarMesesPausados($pausasVentana);

        if ($pausasEn12Meses >= self::MAX_PAUSAS_EN_12_MESES || $mesesPausadosEn12Meses >= self::MAX_MESES_PAUSADOS_EN_12_MESES) {
            $masAntigua = $pausasVentana->min('fecha_inicio');
            $proxima = $masAntigua ? Carbon::parse($masAntigua)->addMonthsNoOverflow(12) : null;

            return $this->noElegible(
                'Límite alcanzado: máximo ' . self::MAX_PAUSAS_EN_12_MESES . ' pausas o ' . self::MAX_MESES_PAUSADOS_EN_12_MESES . ' meses pausados en una ventana de 12 meses.',
                $proxima,
                $pausasEn12Meses,
                $mesesPausadosEn12Meses
            );
        }

        $mesesRestantesVentana = self::MAX_MESES_PAUSADOS_EN_12_MESES - $mesesPausadosEn12Meses;

        return new ResultadoElegibilidadPausa(
            elegible: true,
            motivo: null,
            proximaFechaDisponible: null,
            pausasEn12Meses: $pausasEn12Meses,
            mesesPausadosEn12Meses: $mesesPausadosEn12Meses,
            maxMesesSinCuota: max(0, min(self::MAX_MESES_SIN_CUOTA, $mesesRestantesVentana)),
            maxMesesConCuota: max(0, min(self::MAX_MESES_CON_CUOTA, $mesesRestantesVentana)),
        );
    }

    /**
     * Crea la pausa (Regla 3: siempre inicia el día siguiente a la fecha de corte actual del
     * cliente, meses completos, sin prorrateo). sin_cuota nace ya `programada`; con_cuota nace
     * `esperando_pago` — el job diario la pasa a `programada` cuando el balance del cliente
     * suba lo suficiente para cubrir la cuota (Opción A, ver BillingPauseProcessCommand).
     *
     * @throws \RuntimeException si el cliente no es elegible o los meses no son válidos para el tipo.
     */
    public function crearPausa(Client $client, string $tipo, int $meses, ?string $motivo = null, ?string $canal = null, ?string $evidenciaPath = null): ClientBillingPause
    {
        $elegibilidad = $this->puedePausar($client);
        if (!$elegibilidad->elegible) {
            throw new \RuntimeException($elegibilidad->motivo);
        }

        $maxMeses = $tipo === ClientBillingPause::TIPO_CON_CUOTA
            ? $elegibilidad->maxMesesConCuota
            : $elegibilidad->maxMesesSinCuota;

        if ($meses < 1 || $meses > $maxMeses) {
            throw new \RuntimeException("Para el tipo '{$tipo}' la duración debe ser entre 1 y {$maxMeses} meses.");
        }

        $fechaInicio = Carbon::parse($client->fecha_corte)->addDay()->startOfDay();
        $fechaFin = $fechaInicio->copy()->addMonthsNoOverflow($meses)->subDay()->endOfDay();

        $datos = [
            'client_id' => $client->id,
            'tipo' => $tipo,
            'meses' => $meses,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'motivo' => $motivo,
            'canal' => $canal,
            'evidencia_path' => $evidenciaPath,
        ];

        if ($tipo === ClientBillingPause::TIPO_CON_CUOTA) {
            $client->loadMissing('balance');
            $datos['estado'] = ClientBillingPause::ESTADO_ESPERANDO_PAGO;
            $datos['cuota_mensual'] = self::CUOTA_MENSUAL;
            $datos['monto_cuota'] = round(self::CUOTA_MENSUAL * $meses, 2);
            $datos['balance_al_crear'] = (float) ($client->balance->amount ?? 0);
        } else {
            $datos['estado'] = ClientBillingPause::ESTADO_PROGRAMADA;
        }

        $pausa = ClientBillingPause::create($datos);

        activity()->tap(function (Activity $activity) use ($client) {
            $activity->client_id = $client->id;
        })->log("Pausa de facturación #{$pausa->id} solicitada ({$tipo}, {$meses} " . ($meses == 1 ? 'mes' : 'meses') . '): ' . ($pausa->estado === ClientBillingPause::ESTADO_ESPERANDO_PAGO ? "esperando pago de la cuota (\${$pausa->monto_cuota})." : 'programada.'));

        return $pausa;
    }

    /**
     * Regla 8: cancelar una pausa que AÚN no inició (esperando_pago o programada). No consume
     * pausa (nunca entra a ESTADOS_CONSUMEN_TOPE). Si la cuota ya estaba pagada (con_cuota en
     * estado programada), el monto queda como saldo a favor — sin devolución en efectivo.
     *
     * @throws \RuntimeException si la pausa ya inició o ya está cerrada.
     */
    public function cancelarPausaProgramada(ClientBillingPause $pausa, ?int $canceladoPor = null): ClientBillingPause
    {
        if (!in_array($pausa->estado, [ClientBillingPause::ESTADO_ESPERANDO_PAGO, ClientBillingPause::ESTADO_PROGRAMADA], true)) {
            throw new \RuntimeException("Solo se puede cancelar una pausa que aún no inició (estado actual: {$pausa->estado}).");
        }

        DB::transaction(function () use ($pausa, $canceladoPor) {
            $huboCuotaPagada = $pausa->tipo === ClientBillingPause::TIPO_CON_CUOTA
                && $pausa->estado === ClientBillingPause::ESTADO_PROGRAMADA
                && (float) $pausa->monto_cuota > 0;

            if ($huboCuotaPagada) {
                $this->acreditarBalance($pausa->client, (float) $pausa->monto_cuota, "Saldo a favor por cancelación de la pausa de facturación #{$pausa->id} (cuota ya pagada).");
            }

            $pausa->update([
                'estado' => ClientBillingPause::ESTADO_CANCELADA,
                'cancelled_by' => $canceladoPor ?: (auth()->user()->id ?? null),
            ]);

            activity()->tap(function (Activity $activity) use ($pausa) {
                $activity->client_id = $pausa->client_id;
            })->log("Pausa de facturación #{$pausa->id} cancelada antes de iniciar." . ($huboCuotaPagada ? " Cuota de \${$pausa->monto_cuota} devuelta como saldo a favor." : ''));
        });

        return $pausa->refresh();
    }

    /**
     * Regla 9: reanudar antes de tiempo una pausa en_curso. Reactiva el servicio de inmediato,
     * recalcula fechas de facturación desde HOY (no desde el fecha_fin original), consume la
     * pausa con los meses REALMENTE pausados (arranca los 6 meses mínimos desde hoy) y, si
     * había cuota pagada, los meses no usados quedan como saldo a favor (sin devolución).
     *
     * @throws \RuntimeException si la pausa no está en curso.
     */
    public function reanudarAnticipada(ClientBillingPause $pausa): ClientBillingPause
    {
        if ($pausa->estado !== ClientBillingPause::ESTADO_EN_CURSO) {
            throw new \RuntimeException("Solo se puede reanudar anticipadamente una pausa en_curso (estado actual: {$pausa->estado}).");
        }

        $ahora = Carbon::now();

        DB::transaction(function () use ($pausa, $ahora) {
            $this->reactivarServiciosDelCliente($pausa->client_id);
            $this->recalcularFechasDeFacturacion($pausa->client_id, $ahora);

            $mesesUsados = max(1, Carbon::parse($pausa->fecha_inicio)->diffInMonths($ahora));
            $mesesNoUsados = max(0, $pausa->meses - $mesesUsados);

            $huboSaldoAFavor = $pausa->tipo === ClientBillingPause::TIPO_CON_CUOTA && $mesesNoUsados > 0;
            $montoAFavor = $huboSaldoAFavor ? round($mesesNoUsados * (float) $pausa->cuota_mensual, 2) : 0;

            if ($huboSaldoAFavor) {
                $this->acreditarBalance($pausa->client, $montoAFavor, "Saldo a favor por reanudación anticipada de la pausa de facturación #{$pausa->id} ({$mesesNoUsados} " . ($mesesNoUsados == 1 ? 'mes no usado' : 'meses no usados') . ').');
            }

            $pausa->update([
                'estado' => ClientBillingPause::ESTADO_REANUDADA_ANTICIPADA,
                'fecha_fin_real' => $ahora,
            ]);

            activity()->tap(function (Activity $activity) use ($pausa) {
                $activity->client_id = $pausa->client_id;
            })->log("Pausa de facturación #{$pausa->id} reanudada anticipadamente. Servicio reactivado." . ($huboSaldoAFavor ? " Saldo a favor de \${$montoAFavor} por meses no usados." : ''));
        });

        return $pausa->refresh();
    }

    /**
     * Opción A (2026-09-24): la cuota se paga por el flujo normal de captura de pagos (no hay
     * factura/cargo dedicado). Se detecta comparando el balance actual contra el balance que
     * tenía el cliente al crear la pausa — el delta es lo que se pagó.
     */
    public function cuotaYaFuePagada(ClientBillingPause $pausa): bool
    {
        if ($pausa->balance_al_crear === null || $pausa->monto_cuota === null) {
            return false;
        }

        $client = $pausa->client()->with('balance')->first();
        $balanceActual = (float) ($client->balance->amount ?? 0);
        $delta = $balanceActual - (float) $pausa->balance_al_crear;

        return $delta >= (float) $pausa->monto_cuota;
    }

    /**
     * Pasa la pausa de esperando_pago a programada y descuenta la cuota del balance (ese
     * crédito ya quedó "consumido" por la pausa, no debe seguir disponible como saldo libre).
     */
    public function activarPorPagoDeCuota(ClientBillingPause $pausa): ClientBillingPause
    {
        if ($pausa->estado !== ClientBillingPause::ESTADO_ESPERANDO_PAGO) {
            throw new \RuntimeException("La pausa #{$pausa->id} no está esperando pago (estado actual: {$pausa->estado}).");
        }

        DB::transaction(function () use ($pausa) {
            $client = $pausa->client()->with('balance')->first();
            $nuevoBalance = (float) ($client->balance->amount ?? 0) - (float) $pausa->monto_cuota;
            $client->balance->update(['amount' => $nuevoBalance]);

            $pausa->update(['estado' => ClientBillingPause::ESTADO_PROGRAMADA]);

            activity()->tap(function (Activity $activity) use ($pausa) {
                $activity->client_id = $pausa->client_id;
            })->log("Pausa de facturación #{$pausa->id} programada: cuota de conservación (\${$pausa->monto_cuota}) detectada como pagada y descontada del balance.");
        });

        return $pausa->refresh();
    }

    public function suspenderServiciosDelCliente(int $clientId): void
    {
        $clientRepository = new ClientRepository();
        $clientWithServices = $clientRepository->getServicesForClient($clientId);

        foreach (ComunConstantsController::ALL_CLIENT_SERVICE as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                SuspendServiceJob::dispatchSync($clientService);
            }
        }
    }

    public function reactivarServiciosDelCliente(int $clientId): void
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
     * Recorre fecha_pago y fecha_corte al terminar la pausa (o al reanudar anticipadamente),
     * para que invoice:create-proformas retome al cliente normal en su siguiente corrida. Se
     * fija fecha_pago = fecha en que termina la pausa (equivalente a "como si hubiera renovado
     * ese día") y de ahí se deriva fecha_corte con el servicio existente, respetando el tipo de
     * facturación real del cliente.
     */
    public function recalcularFechasDeFacturacion(int $clientId, $fechaFinPausa): void
    {
        $clientRepository = new ClientRepository();
        $client = $clientRepository->getClientById($clientId);

        $client->fecha_pago = Carbon::parse($fechaFinPausa)->toDateTimeString();
        $client->fecha_corte = Carbon::parse($fechaFinPausa)->toDateTimeString();
        $client->save();

        $client->refresh();
        (new BillingExpirationService($client))->setNewFechaCorteForClient(null, 1, true, false);
    }

    private function acreditarBalance(Client $client, float $monto, string $motivoLog): void
    {
        $client->loadMissing('balance');
        $nuevoBalance = (float) ($client->balance->amount ?? 0) + $monto;
        $client->balance->update(['amount' => $nuevoBalance]);

        activity()->tap(function (Activity $activity) use ($client) {
            $activity->client_id = $client->id;
        })->log($motivoLog . " Balance anterior: {$client->balance->getOriginal('amount')}, nuevo: {$nuevoBalance}.");
    }

    /**
     * Meses realmente pausados: si la pausa se reanudó antes de tiempo, cuentan los meses
     * REALES (fecha_inicio → fecha_fin_real), no los meses originalmente programados (Regla 9).
     */
    private function sumarMesesPausados(Collection $pausas): int
    {
        return (int) $pausas->sum(function (ClientBillingPause $pausa) {
            if ($pausa->fecha_fin_real) {
                return max(1, Carbon::parse($pausa->fecha_inicio)->diffInMonths(Carbon::parse($pausa->fecha_fin_real)));
            }

            return $pausa->meses;
        });
    }

    private function noElegible(
        string $motivo,
        ?Carbon $proximaFechaDisponible = null,
        int $pausasEn12Meses = 0,
        int $mesesPausadosEn12Meses = 0
    ): ResultadoElegibilidadPausa {
        return new ResultadoElegibilidadPausa(
            elegible: false,
            motivo: $motivo,
            proximaFechaDisponible: $proximaFechaDisponible,
            pausasEn12Meses: $pausasEn12Meses,
            mesesPausadosEn12Meses: $mesesPausadosEn12Meses,
            maxMesesSinCuota: 0,
            maxMesesConCuota: 0,
        );
    }
}
