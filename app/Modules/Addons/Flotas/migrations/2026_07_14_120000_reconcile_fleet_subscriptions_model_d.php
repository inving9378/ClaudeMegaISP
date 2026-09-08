<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corte A de #65 — formaliza en un archivo versionado el esquema Modelo D de
 * fleet_subscriptions, ya aplicado en BD DEV (batch 626, migración fantasma sin
 * commitear — patrón #534). Idempotente: solo añade lo que falte y solo migra
 * los datos legacy que aún existan.
 *
 * Modelo D (ratificado por Irving, 2026-07-14 16:05): tiers por vehículo activo +
 * excedente. Básico $99 (≤5) / Medio $199 (≤15) / Pro $349 (≤30) MXN/mes,
 * excedente $15 MXN/vehículo extra/mes. GPS add-on por vehículo fuera de alcance
 * (precio pendiente).
 *
 * `price_per_vehicle` queda DEPRECADA pero NO se elimina aquí (patrón en dos
 * tiempos del guardrail #1018 — borrar una columna es borrar datos, frontera
 * dura fuera de mi alcance sin la maduración que el guard exige). Ningún
 * código lee ni escribe esa columna desde este corte; su retiro real queda
 * para una migración de contracción posterior con `contraccion_de: V{n}`.
 */
return new class extends Migration
{
    private const NEW_COLUMNS = ['base_price', 'included_units', 'overage_units', 'overage_unit_price', 'overage_amount'];

    private const LEGACY_TO_MODEL_D = [
        'gestion_plus'          => 'basico',
        'gestion_plus_tracking' => 'medio',
        'empresa'               => 'pro',
    ];

    public function up(): void
    {
        Schema::table('fleet_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('fleet_subscriptions', 'base_price')) {
                $table->decimal('base_price', 8, 2)->default(0)->after('vehicles_count');
            }
            if (! Schema::hasColumn('fleet_subscriptions', 'included_units')) {
                $table->unsignedInteger('included_units')->default(0)->after('base_price');
            }
            if (! Schema::hasColumn('fleet_subscriptions', 'overage_units')) {
                $table->unsignedInteger('overage_units')->default(0)->after('included_units');
            }
            if (! Schema::hasColumn('fleet_subscriptions', 'overage_unit_price')) {
                $table->decimal('overage_unit_price', 8, 2)->default(15.00)->after('overage_units');
            }
            if (! Schema::hasColumn('fleet_subscriptions', 'overage_amount')) {
                $table->decimal('overage_amount', 10, 2)->default(0)->after('overage_unit_price');
            }
        });

        foreach (self::LEGACY_TO_MODEL_D as $legacy => $modelD) {
            DB::table('fleet_subscriptions')->where('plan', $legacy)->update(['plan' => $modelD]);
        }
    }

    public function down(): void
    {
        foreach (array_flip(self::LEGACY_TO_MODEL_D) as $modelD => $legacy) {
            DB::table('fleet_subscriptions')->where('plan', $modelD)->update(['plan' => $legacy]);
        }

        Schema::table('fleet_subscriptions', function (Blueprint $table) {
            foreach (self::NEW_COLUMNS as $column) {
                if (Schema::hasColumn('fleet_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
