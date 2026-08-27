<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corte B de #65 — la migración anterior (2026_07_14_120000) sólo renombró las
 * claves de plan legacy → Modelo D; las filas que ya existían antes de ese
 * corte se quedaron con base_price/included_units en 0 (default de columna
 * nueva), así que su monthly_price quedaba mal calculado (solo excedente, sin
 * el precio base del tier). Repuebla esos 3 campos desde el catálogo de tiers
 * ratificado y recalcula overage_units/overage_amount/monthly_price para
 * TODAS las filas — misma fórmula que `FleetSubscriptionService::syncVehicleCount()`,
 * así que no diverge de lo que la app calcularía. Idempotente: reaplicar no
 * cambia nada si ya está correcto.
 */
return new class extends Migration
{
    private const OVERAGE_UNIT_PRICE = 15.00;

    private const TIERS = [
        'basico' => ['base_price' => 99.00,  'included_units' => 5],
        'medio'  => ['base_price' => 199.00, 'included_units' => 15],
        'pro'    => ['base_price' => 349.00, 'included_units' => 30],
    ];

    public function up(): void
    {
        foreach (self::TIERS as $plan => $tier) {
            DB::table('fleet_subscriptions')
                ->where('plan', $plan)
                ->update([
                    'base_price'         => $tier['base_price'],
                    'included_units'     => $tier['included_units'],
                    'overage_unit_price' => self::OVERAGE_UNIT_PRICE,
                ]);
        }

        DB::table('fleet_subscriptions')
            ->select('id', 'vehicles_count', 'base_price', 'included_units', 'overage_unit_price')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $overageUnits  = max(0, (int) $row->vehicles_count - (int) $row->included_units);
                    $overageAmount = round($overageUnits * (float) $row->overage_unit_price, 2);
                    $monthlyPrice  = round((float) $row->base_price + $overageAmount, 2);

                    DB::table('fleet_subscriptions')->where('id', $row->id)->update([
                        'overage_units'  => $overageUnits,
                        'overage_amount' => $overageAmount,
                        'monthly_price'  => $monthlyPrice,
                    ]);
                }
            });
    }

    /**
     * No reversible a un estado anterior conocido (recalcular hacia atrás no
     * tiene un origen fiable: la migración previa ya movió los datos legacy).
     * No-op deliberado — no hay pérdida de datos porque no borra nada.
     */
    public function down(): void
    {
    }
};
