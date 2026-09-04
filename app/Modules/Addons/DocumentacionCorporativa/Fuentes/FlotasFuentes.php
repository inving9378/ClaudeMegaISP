<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Fuentes;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\FuenteRegistry;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetPositionService;

/**
 * Fase 1.4 (item #731) — fuente viva de flota vehicular para el Apartado V.
 *
 * Mismo mecanismo que Fase 1.1/1.2/1.3: solo lectura, nunca escribe.
 * `$empresaId` no se usa (mismo motivo documentado en `FinanzasFuentes`: una
 * sola empresa corporativa hoy).
 *
 * Decisión registrada (Fase 1.4, ver `circuito:reportar --tipo=decision`):
 * lista TODOS los vehículos sin filtrar por `client_id` — a diferencia de
 * `FleetPositionService::getCurrentPositions()` (que sí es multi-tenant, para
 * el mapa operativo del cliente dueño), este concepto es un CENSO corporativo
 * de activos de Meganet (Apartado V), así que debe incluir los vehículos
 * asignados a un cliente igual que los internos. Reusa
 * `FleetPositionService::getLastPosition()`/`::liveStatus()` (Fase 2 GPS, ya
 * documentada en CLAUDE.md) para no duplicar la lógica de última posición ni
 * la clasificación moving/stopped/idle/offline.
 *
 * La vista sigue siendo `dc-concepto-tabla` (SistemaResolver no tiene un tipo
 * de vista "mapa"; ningún concepto de InventarioResolver con `config.mapa`
 * true lo tiene tampoco todavía — es una mejora de UI aparte, fuera de
 * alcance de esta fase). lat/lng viajan como columnas de la tabla.
 */
class FlotasFuentes
{
    public static function registrar(FuenteRegistry $registry): void
    {
        $registry->registrar('flotas.vehiculos', static fn (array $config, int $empresaId): array => self::vehiculos());
    }

    private static function vehiculos(): array
    {
        $service = app(FleetPositionService::class);

        $vehiculos = FleetVehicle::query()
            ->with('currentAssignment.operator')
            ->orderBy('plates')
            ->get();

        $datos = $vehiculos->map(function (FleetVehicle $v) use ($service) {
            $pos = $v->has_gps ? $service->getLastPosition($v->id) : null;

            return [
                'vehiculo'        => $v->display_name,
                'placas'          => $v->plates,
                'tipo'            => $v->vehicle_type,
                'estado'          => $v->status,
                'operador'        => $v->currentAssignment?->operator?->name,
                'lat'             => $pos ? (float) $pos->lat : null,
                'lng'             => $pos ? (float) $pos->lng : null,
                'ultima_posicion' => optional($pos?->recorded_at)->format('d/m/Y H:i'),
                'estado_gps'      => $v->has_gps ? $service->liveStatus($pos) : 'sin_gps',
            ];
        })->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total_vehiculos' => $vehiculos->count(),
                'con_gps'         => $vehiculos->where('has_gps', true)->count(),
            ],
            'mensaje' => $datos === [] ? 'Sin vehículos registrados en este entorno.' : null,
        ];
    }
}
