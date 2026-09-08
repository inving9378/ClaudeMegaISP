<?php

namespace App\Modules\Addons\Flotas\Support;

/**
 * Catálogo de planes SaaS de Flotas — fuente única de verdad.
 *
 * Modelo D — híbrido tier + excedente (ratificado por Irving 2026-07-14, item #65
 * Corte A): tiers por cantidad de vehículos activos (unidad facturable) + excedente
 * por vehículo extra al mes. GPS es add-on opcional por vehículo, precio pendiente,
 * fuera de alcance de este catálogo.
 */
class FleetPlans
{
    /** $MXN por vehículo excedente/mes, sobre el tope incluido del tier. */
    public const OVERAGE_UNIT_PRICE = 15.00;

    /** Días de demo, uniformes para los 3 tiers. */
    public const TRIAL_DAYS = 15;

    public const PLANS = [
        'basico' => [
            'key'                => 'basico',
            'name'               => 'Básico',
            'tagline'            => 'Hasta 5 vehículos',
            'base_price'         => 99.00,
            'included_units'     => 5,
            'overage_unit_price' => self::OVERAGE_UNIT_PRICE,
            'trial_days'         => self::TRIAL_DAYS,
            'features'           => [
                'Vehículos hasta el tope del plan', 'Mantenimientos y recordatorios',
                'Documentos con semáforo de vencimientos', 'Bitácora de combustible (km/L)',
                'Tracking GPS en vivo y geocercas', 'Asignación de operadores', 'Dashboard y reportes',
            ],
        ],
        'medio' => [
            'key'                => 'medio',
            'name'               => 'Medio',
            'tagline'            => 'Hasta 15 vehículos',
            'base_price'         => 199.00,
            'included_units'     => 15,
            'overage_unit_price' => self::OVERAGE_UNIT_PRICE,
            'trial_days'         => self::TRIAL_DAYS,
            'features'           => [
                'Vehículos hasta el tope del plan', 'Mantenimientos y recordatorios',
                'Documentos con semáforo de vencimientos', 'Bitácora de combustible (km/L)',
                'Tracking GPS en vivo y geocercas', 'Asignación de operadores', 'Dashboard y reportes',
            ],
        ],
        'pro' => [
            'key'                => 'pro',
            'name'               => 'Pro',
            'tagline'            => 'Hasta 30 vehículos',
            'base_price'         => 349.00,
            'included_units'     => 30,
            'overage_unit_price' => self::OVERAGE_UNIT_PRICE,
            'trial_days'         => self::TRIAL_DAYS,
            'features'           => [
                'Vehículos hasta el tope del plan', 'Mantenimientos y recordatorios',
                'Documentos con semáforo de vencimientos', 'Bitácora de combustible (km/L)',
                'Tracking GPS en vivo y geocercas', 'Asignación de operadores', 'Dashboard y reportes',
                'Reportes avanzados', 'Soporte prioritario',
            ],
        ],
    ];

    public static function all(): array
    {
        return array_values(self::PLANS);
    }

    public static function exists(string $key): bool
    {
        return isset(self::PLANS[$key]);
    }

    public static function get(string $key): ?array
    {
        return self::PLANS[$key] ?? null;
    }

    public static function basePrice(string $key): float
    {
        return (float) (self::PLANS[$key]['base_price'] ?? 0);
    }

    public static function includedUnits(string $key): int
    {
        return (int) (self::PLANS[$key]['included_units'] ?? 0);
    }

    public static function overageUnitPrice(): float
    {
        return self::OVERAGE_UNIT_PRICE;
    }

    public static function trialDays(string $key): int
    {
        return (int) (self::PLANS[$key]['trial_days'] ?? 0);
    }

    /** Claves válidas para reglas de validación `in:`. */
    public static function keys(): array
    {
        return array_keys(self::PLANS);
    }
}
