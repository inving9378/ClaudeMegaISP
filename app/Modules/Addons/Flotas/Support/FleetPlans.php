<?php

namespace App\Modules\Addons\Flotas\Support;

/**
 * Catálogo de planes SaaS de Flotas — fuente única de verdad.
 *
 * Modelo freemium BYOD (Bring Your Own Device): el GPS SIEMPRE lo paga y compra
 * el cliente donde quiera; el software es agnóstico de marca. El precio es por
 * vehículo/mes. Definitivo según CLAUDE.md item #65, Sesión 5 (2026-06-01 PM).
 */
class FleetPlans
{
    public const PLANS = [
        'gestion_plus' => [
            'key'               => 'gestion_plus',
            'name'              => 'Gestión Plus',
            'tagline'           => 'Control de flota sin GPS',
            'mode'              => 'A',            // Modo A: sin tracking
            'gps'               => false,
            'price_per_vehicle' => 49.00,
            'trial_days'        => 180,           // 6 meses gratis
            'features'          => [
                'Vehículos ilimitados', 'Mantenimientos y recordatorios',
                'Documentos con semáforo de vencimientos', 'Bitácora de combustible (km/L)',
                'Asignación de operadores', 'Dashboard y reportes',
            ],
        ],
        'gestion_plus_tracking' => [
            'key'               => 'gestion_plus_tracking',
            'name'              => 'Gestión Plus + Tracking',
            'tagline'           => 'Todo lo anterior + GPS en vivo',
            'mode'              => 'B',            // Modo B: con tracking
            'gps'               => true,
            'price_per_vehicle' => 149.00,
            'trial_days'        => 90,            // 3 meses gratis
            'features'          => [
                'Todo lo de Gestión Plus', 'Tracking GPS en vivo (mapa)',
                'Historial de recorridos y GPX', 'Alertas de exceso de velocidad / encendido',
                'Compatible con Ruptela, Concox, GT06', 'Multi-marca (BYOD)',
            ],
        ],
        'empresa' => [
            'key'               => 'empresa',
            'name'              => 'Empresa',
            'tagline'           => 'Flotas grandes con soporte prioritario',
            'mode'              => 'B',
            'gps'               => true,
            'price_per_vehicle' => 299.00,
            'trial_days'        => 30,            // 1 mes gratis
            'features'          => [
                'Todo lo de Tracking', 'Geocercas y zonas', 'Reportes avanzados y API',
                'Soporte prioritario', 'Onboarding asistido', 'SLA dedicado',
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

    public static function pricePerVehicle(string $key): float
    {
        return (float) (self::PLANS[$key]['price_per_vehicle'] ?? 0);
    }

    public static function trialDays(string $key): int
    {
        return (int) (self::PLANS[$key]['trial_days'] ?? 0);
    }

    public static function hasGps(string $key): bool
    {
        return (bool) (self::PLANS[$key]['gps'] ?? false);
    }

    /** Claves válidas para reglas de validación `in:`. */
    public static function keys(): array
    {
        return array_keys(self::PLANS);
    }
}
