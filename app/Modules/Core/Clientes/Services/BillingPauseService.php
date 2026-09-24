<?php

namespace App\Modules\Core\Clientes\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientBillingPause;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
