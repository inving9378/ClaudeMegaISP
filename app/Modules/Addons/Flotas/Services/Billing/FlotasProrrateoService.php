<?php

namespace App\Modules\Addons\Flotas\Services\Billing;

use App\Models\Invoice;
use App\Modules\Addons\Flotas\Models\FleetSubscription;
use App\Modules\Addons\Flotas\Models\FleetSubscriptionEvent;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Support\FleetPlans;
use App\Services\IvaInformationService;
use Carbon\Carbon;

/**
 * Prorrateo diario por vehículo de la suscripción SaaS de Flotas (item #755, sub-item
 * de seguimiento de #720). Reglas aprobadas por Irving (respuestas q1-q5 del item):
 *
 *  - Una línea de facturación POR VEHÍCULO, con el desglose de días activos (q1 opción 1).
 *  - (Reemplazado en F2b #623: ya no hay tarifa por vehículo; se cobra el bloque vigente cada día, FleetPlans.)
 *  - "Días activos" = días entre la fecha de alta y la fecha de baja del vehículo EN EL
 *    SISTEMA dentro del período — fuente de verdad: fleet_vehicles.created_at (alta) y
 *    fleet_vehicles.deleted_at (baja, soft delete). Sin tabla nueva: la propia tabla de
 *    vehículos ya es un registro fiable de cuándo entró/salió cada unidad (q3 opción 1).
 *  - Cálculo on-demand, al momento de facturar — sin job de acumulación diaria (q5 opción 1).
 *
 * Standalone a propósito: todavía NO se engancha a
 * ClientRepository::calculateAmounts()/resolveFleetSubscriptionLines() (q4 opción 1, mismo
 * lugar que ya usa Contratables). Esa base la introduce el item #720 (branch aprobada por
 * Irving, nivel C, pendiente de su merge manual vía la Torre) y todavía no está en main.
 * Enganchar aquí antes de que esa base exista duplicaría/pelearía ese cambio sobre el mismo
 * método. El cableado queda registrado como sub-item de seguimiento para correrse en cuanto
 * #720 aterrice en main.
 */
class FlotasProrrateoService
{
    /**
     * Tasa de IVA general del sistema. Mismo criterio ya registrado por el item #720 para
     * la línea única de Flotas (sin catálogo propio de IVA): 16%, precio de tarifa
     * IVA-incluido, igual que resolveContratableLines() desglosa vía IvaInformationService.
     */
    private const IVA_PORCENT = 16;

    /**
     * Líneas de Flotas de la factura del período $periodo ('Y-m') — Decisión 7, Opción A
     * (item #622). La proforma del período P se genera DURANTE P-1, así que:
     *
     *  1) CARGO POR ADELANTADO de P: bloque de las unidades registradas AL GENERAR la factura
     *     (mes completo; solo los días desde la conversión si la suscripción no estará activa
     *     todo P).
     *  2) AJUSTE del último mes CERRADO (el anterior al mes en que se genera la factura; con la
     *     proforma del día 25 es P-2): uso real de ese mes prorrateado por días (solo días con
     *     la suscripción activa) MENOS lo cobrado por adelantado para él (evento 'billed').
     *     Positivo = cargo, negativo = descuento; cero = sin línea. Solo se ajusta un mes que
     *     TUVO cargo por adelantado (así, al prender el cobro no se cobra de golpe el mes en que
     *     Flotas no se facturaba) y una sola vez (si otra factura viva ya lo ajustó, no se repite).
     *     Ajustar P-1 —el mes en curso al generar— dejaba sin cobrar ni descontar lo que el
     *     cliente cambiaba del 25 al fin de mes.
     *
     * Solo lectura (calculateAmounts es puro): lo cobrado por adelantado se persiste al crear
     * la factura (InvoiceService::createProformaInvoice → FleetSubscriptionService::markBilled).
     */
    public function calcularLineasPeriodo(FleetSubscription $subscription, string $periodo, ?Carbon $ahora = null): array
    {
        $ahora  = ($ahora ?? now())->copy();
        $inicio = Carbon::createFromFormat('Y-m-d', $periodo . '-01')->startOfDay();
        $fin    = $inicio->copy()->endOfMonth()->startOfDay();
        $dias   = $inicio->daysInMonth;
        $activaDesde = $this->activaDesde($subscription);

        $lineas = [];

        // 1) Cargo por adelantado de P
        $unidades = FleetVehicle::where('client_id', $subscription->client_id)
            ->where('created_at', '<=', $ahora)->count();
        if ($unidades > 0 && $activaDesde && $activaDesde->lessThanOrEqualTo($fin)) {
            $diasCobrados = $activaDesde->greaterThan($inicio) ? $activaDesde->diffInDays($fin) + 1 : $dias;
            $monto = self::montoAdelantado($unidades, $diasCobrados, $dias);
            $lineas[] = $this->linea(
                $subscription, $monto,
                sprintf('Flotas — bloque %s (%d unidades)%s', FleetPlans::get(FleetPlans::keyForUnits($unidades))['name'], $unidades, $diasCobrados < $dias ? sprintf(', %d/%d días', $diasCobrados, $dias) : ''),
                ['flotas_tipo' => 'adelanto', 'flotas_periodo' => $periodo, 'flotas_unidades' => $unidades, 'flotas_monto_adelantado' => $monto]
            );
        }

        // 2) Ajuste del último mes cerrado (anterior al mes en que se genera esta factura)
        $cerrado = $ahora->copy()->startOfMonth()->subMonthNoOverflow()->startOfDay();
        $periodoAj = $cerrado->format('Y-m');
        $adelantado = $periodoAj < $periodo ? $this->adelantadoDe($subscription, $periodoAj) : null;
        if ($adelantado !== null && ! $this->ajusteYaFacturado($subscription, $periodoAj)) {
            $real = $this->usoRealPeriodo($subscription, $cerrado, $activaDesde);
            $dif = round($real - $adelantado, 2);
            if (abs($dif) >= 0.01) {
                $lineas[] = $this->linea(
                    $subscription, $dif,
                    sprintf('Flotas — ajuste %s: %s', $periodoAj, $dif > 0 ? 'unidades agregadas' : 'unidades quitadas'),
                    ['flotas_tipo' => 'ajuste', 'flotas_ajuste_de' => $periodoAj, 'flotas_monto_ajuste' => $dif]
                );
            }
        }

        return $lineas;
    }

    /** Monto (IVA incluido) del cargo por adelantado: precio del bloque × días cobrados / días del mes. */
    public static function montoAdelantado(int $unidades, int $diasCobrados, int $diasMes): float
    {
        return round(FleetPlans::priceForUnits($unidades) * $diasCobrados / $diasMes, 2);
    }

    /** Costo de un mes dado el conteo de unidades de cada día (0-based): Σ precio_bloque(unidades_día) / días. */
    public static function costoDiario(array $unidadesPorDia, int $primerDiaActivo = 0): float
    {
        $dias = count($unidadesPorDia);
        $total = 0.0;
        foreach ($unidadesPorDia as $i => $u) {
            if ($u > 0 && $i >= $primerDiaActivo) {
                $total += FleetPlans::priceForUnits($u) / $dias;
            }
        }
        return round($total, 2);
    }

    /** Fecha desde la que la suscripción cobra: conversión (active/past_due) o fin de la promo (promo en curso). Null = no cobra. */
    private function activaDesde(FleetSubscription $sub): ?Carbon
    {
        if (in_array($sub->status, ['active', 'past_due'], true)) {
            return $sub->started_at ? $sub->started_at->copy()->startOfDay() : Carbon::create(2000, 1, 1);
        }
        if ($sub->status === 'trial' && $sub->is_promo && $sub->trial_ends_at) {
            return $sub->trial_ends_at->copy()->addDay()->startOfDay();
        }
        return null;
    }

    /** Uso real del mes $inicio..fin: unidades de cada día, contando solo días con la suscripción activa. */
    private function usoRealPeriodo(FleetSubscription $sub, Carbon $inicio, ?Carbon $activaDesde): float
    {
        if (! $activaDesde) {
            return 0.0;
        }
        $fin  = $inicio->copy()->endOfMonth()->startOfDay();
        $dias = $inicio->daysInMonth;
        $vehiculos = FleetVehicle::withTrashed()
            ->where('client_id', $sub->client_id)
            ->where('created_at', '<=', $fin->copy()->endOfDay())
            ->where(function ($q) use ($inicio) {
                $q->whereNull('deleted_at')->orWhere('deleted_at', '>=', $inicio);
            })->get();

        $porDia = array_fill(0, $dias, 0);
        foreach ($vehiculos as $v) {
            foreach ($this->diasActivosIndices($v, $inicio, $fin) as $i) {
                $porDia[$i]++;
            }
        }
        $primer = $activaDesde->greaterThan($inicio) ? $inicio->diffInDays($activaDesde) : 0;

        return self::costoDiario($porDia, $primer);
    }

    /**
     * Monto (IVA incluido) cobrado por adelantado para $periodo: el evento 'billed' más reciente de
     * ese período cuya factura sigue viva. Null = ese mes no tuvo cargo por adelantado (no se ajusta).
     */
    private function adelantadoDe(FleetSubscription $sub, string $periodo): ?float
    {
        $ev = $this->eventosVivos($sub, fn ($q) => $q->where('metadata->periodo', $periodo))->first();

        return $ev ? (float) ($ev->metadata['monto_adelantado'] ?? 0) : null;
    }

    /** ¿Otra factura viva ya llevó el ajuste de $periodo? (evento 'billed' con ajuste_de). */
    private function ajusteYaFacturado(FleetSubscription $sub, string $periodo): bool
    {
        return $this->eventosVivos($sub, fn ($q) => $q->where('metadata->ajuste_de', $periodo))->isNotEmpty();
    }

    /** Eventos 'billed' de la suscripción (más recientes primero) cuya factura no fue borrada. */
    private function eventosVivos(FleetSubscription $sub, \Closure $filtro)
    {
        $q = FleetSubscriptionEvent::where('subscription_id', $sub->id)->where('event_type', 'billed');
        $filtro($q);

        return $q->orderByDesc('id')->get()->filter(function ($ev) {
            $invoiceId = $ev->metadata['invoice_id'] ?? null;

            return $invoiceId && Invoice::whereKey($invoiceId)->exists();
        })->values();
    }

    private function linea(FleetSubscription $sub, float $montoConIva, string $nombre, array $extra): array
    {
        $info = (new IvaInformationService(self::IVA_PORCENT, $montoConIva))->getIvaInformation();

        return array_merge([
            'service_name'  => $nombre,
            'iva_porcent'   => self::IVA_PORCENT,
            'iva'           => $info['iva'],
            'monto'         => $info['monto'],
            'service_id'    => $sub->id,
            'service_class' => FleetSubscription::class,
        ], $extra);
    }

    /** Índices (0-based desde $periodoInicio) de los días del período en que el vehículo estuvo de alta. */
    private function diasActivosIndices(FleetVehicle $vehiculo, Carbon $periodoInicio, Carbon $periodoFin): array
    {
        $dias = $this->diasActivosEnPeriodo($vehiculo, $periodoInicio, $periodoFin);
        if ($dias <= 0) {
            return [];
        }
        $alta   = $vehiculo->created_at->copy()->startOfDay();
        $inicio = $alta->greaterThan($periodoInicio) ? $periodoInicio->diffInDays($alta) : 0;

        return range($inicio, $inicio + $dias - 1);
    }

    /** Días del período [$periodoInicio, $periodoFin] (ambos inclusive) en los que el vehículo estuvo de alta. */
    private function diasActivosEnPeriodo(FleetVehicle $vehiculo, Carbon $periodoInicio, Carbon $periodoFin): int
    {
        $alta           = $vehiculo->created_at->copy()->startOfDay();
        $inicioEfectivo = $alta->greaterThan($periodoInicio) ? $alta : $periodoInicio->copy();

        $finEfectivo = $periodoFin->copy();
        if ($vehiculo->deleted_at) {
            $baja = $vehiculo->deleted_at->copy()->startOfDay();
            if ($baja->lessThan($finEfectivo)) {
                $finEfectivo = $baja;
            }
        }

        if ($finEfectivo->lessThan($inicioEfectivo)) {
            return 0;
        }

        return $inicioEfectivo->diffInDays($finEfectivo) + 1;
    }
}
