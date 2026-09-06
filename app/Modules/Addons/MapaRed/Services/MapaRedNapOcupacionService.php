<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;

/**
 * MR-20 (item roadmap #956) — semáforo de ocupación de puertos por NAP (D16), calcado del
 * patrón de MapaRedNapHealthService (MR-21/D17). "Usados" = enlaces de servicio ACTIVOS
 * (consistente con MR-14/D19: la fuente de verdad de un cliente conectado es el enlace, no
 * `MapaRedPuerto.estado`, que hoy nadie sincroniza al dar de alta un enlace). "Totales" = los
 * puertos `nap_salida` ya generados para la caja; si aún no se generaron (backfill pendiente),
 * cae a la capacidad de diseño (`mapared_layers.inputs`) para no reportar 0/0.
 */
class MapaRedNapOcupacionService
{
    public const GRIS = 'gris';
    public const AMARILLO = 'amarillo';
    public const NARANJA = 'naranja';
    public const ROJO = 'rojo';

    /**
     * Ocupación de una sola NAP (DoD #956: usados/totales al pasar el cursor).
     */
    public function calcular(string $puertableType, int $puertableId): array
    {
        $totalPuertos = MapaRedPuerto::query()
            ->delDueno($puertableType, $puertableId)
            ->where('rol', MapaRedPuerto::ROL_NAP_SALIDA)
            ->count();

        if ($totalPuertos === 0 && $puertableType === MapaRedLayer::class) {
            $totalPuertos = (int) (MapaRedLayer::find($puertableId)->inputs ?? 0);
        }

        $puertosUsados = MapaRedEnlaceServicio::porNap($puertableType, $puertableId)
            ->filter(fn ($enlace) => $enlace->estado === 'activo')
            ->count();

        $porcentaje = $totalPuertos > 0 ? (int) round($puertosUsados / $totalPuertos * 100) : 0;

        return [
            'puertos_usados' => $puertosUsados,
            'puertos_totales' => $totalPuertos,
            'porcentaje' => $porcentaje,
            'semaforo' => $this->semaforo($porcentaje, $totalPuertos),
        ];
    }

    /**
     * Ocupación de varias NAPs en una sola pasada — para pintar el mapa completo sin hacer
     * una consulta por marcador (mismo objetivo que `MapaRedNapHealthService::calcularParaVarias`).
     *
     * @return array<int, array{puertos_usados:int,puertos_totales:int,porcentaje:int,semaforo:string}>
     */
    public function calcularParaVarias(string $puertableType, array $puertableIds): array
    {
        $resultado = [];

        foreach ($puertableIds as $id) {
            $resultado[(int) $id] = $this->calcular($puertableType, (int) $id);
        }

        return $resultado;
    }

    /**
     * D16: Gris 0–50% · Amarillo 51–70% · Naranja 71–99% · Rojo 100%. Sin puertos (capacidad
     * desconocida) también cuenta como gris.
     */
    private function semaforo(int $porcentaje, int $totalPuertos): string
    {
        if ($totalPuertos === 0) {
            return self::GRIS;
        }

        if ($porcentaje >= 100) {
            return self::ROJO;
        }

        if ($porcentaje >= 71) {
            return self::NARANJA;
        }

        if ($porcentaje >= 51) {
            return self::AMARILLO;
        }

        return self::GRIS;
    }
}
