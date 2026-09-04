<?php

namespace App\Modules\Addons\Flotas\Services\Billing;

use App\Modules\Addons\Flotas\Models\FleetSubscription;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Services\IvaInformationService;
use Carbon\Carbon;

/**
 * Prorrateo diario por vehículo de la suscripción SaaS de Flotas (item #755, sub-item
 * de seguimiento de #720). Reglas aprobadas por Irving (respuestas q1-q5 del item):
 *
 *  - Una línea de facturación POR VEHÍCULO, con el desglose de días activos (q1 opción 1).
 *  - Tarifa fija por vehículo: FleetSubscription::price_per_vehicle (q2 opción 1).
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
     * Líneas de facturación de una suscripción para el mes de $fechaReferencia (por
     * defecto, el mes actual): una por vehículo con al menos 1 día activo en el período,
     * prorrateada por días_activos / días_del_periodo × tarifa_por_vehiculo.
     *
     * Vehículos que aún no existían o que ya estaban de baja dentro del período completo
     * se omiten (0 días activos → nada que cobrar).
     */
    public function calcularLineas(FleetSubscription $subscription, ?Carbon $fechaReferencia = null): array
    {
        $tarifa = (float) $subscription->price_per_vehicle;
        if ($tarifa <= 0) {
            return [];
        }

        $fechaReferencia = ($fechaReferencia ?? now())->copy();
        $periodoInicio   = $fechaReferencia->copy()->startOfMonth()->startOfDay();
        $periodoFin      = $fechaReferencia->copy()->endOfMonth()->startOfDay();
        $diasPeriodo     = $periodoInicio->diffInDays($periodoFin) + 1;

        $vehiculos = FleetVehicle::withTrashed()
            ->where('client_id', $subscription->client_id)
            ->where('created_at', '<=', $periodoFin)
            ->where(function ($q) use ($periodoInicio) {
                $q->whereNull('deleted_at')->orWhere('deleted_at', '>=', $periodoInicio);
            })
            ->orderBy('id')
            ->get();

        $lineas = [];
        foreach ($vehiculos as $vehiculo) {
            $dias = $this->diasActivosEnPeriodo($vehiculo, $periodoInicio, $periodoFin);
            if ($dias <= 0) {
                continue;
            }

            $montoConIva = round(($dias / $diasPeriodo) * $tarifa, 2);
            $info = (new IvaInformationService(self::IVA_PORCENT, $montoConIva))->getIvaInformation();

            $lineas[] = [
                'service_name'  => sprintf('Flotas SaaS — %s (%d/%d días)', $this->etiquetaVehiculo($vehiculo), $dias, $diasPeriodo),
                'iva_porcent'   => self::IVA_PORCENT,
                'iva'           => $info['iva'],
                'monto'         => $info['monto'],
                'service_id'    => $subscription->id,
                'service_class' => FleetSubscription::class,
                'vehicle_id'    => $vehiculo->id,
                'dias_activos'  => $dias,
                'dias_periodo'  => $diasPeriodo,
            ];
        }

        return $lineas;
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

    private function etiquetaVehiculo(FleetVehicle $vehiculo): string
    {
        $partes = array_filter([$vehiculo->plates, $vehiculo->brand, $vehiculo->model]);

        return $partes ? implode(' ', $partes) : "Vehículo #{$vehiculo->id}";
    }
}
