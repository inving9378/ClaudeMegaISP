<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\GestionRed\Models\OltOnu;
use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MR-21 (item roadmap #957) — semáforo de salud por NAP (caja de servicio) alimentado por
 * MultiOLT (D18: no se construye un motor de umbrales dB propio; se consumen los campos que
 * el driver de MultiOLT ya categoriza — `OltOnu.status`/`OltOnu.signal`). El emparejamiento
 * ONU↔enlace es SIEMPRE por serie (D19), nunca por ID de BD.
 *
 * "RX"/"TX" que expone el dashboard son los mismos campos que ya documenta HuaweiDriver:
 * `signal_1310` = lo que la OLT recibe del ONT (upstream) → "Rx".
 * `signal_1490` = lo que el ONT recibe de la OLT (downstream, lo que llega al cliente) → "Tx".
 */
class MapaRedNapHealthService
{
    public const VERDE = 'verde';
    public const AMARILLO = 'amarillo';
    public const ROJO = 'rojo';
    public const GRIS = 'gris';

    /** Estados de OltOnu que cuentan como "desconectado" para el semáforo D17. */
    private const ESTADOS_OFFLINE = ['Offline', 'Power fail', 'LOS'];

    /**
     * Umbral de caída (sesión 2026-10-09, decisión de Irving): cuánto puede empeorar la
     * potencia más baja de las ONUs de un NAP respecto a su "potencia de instalación" antes de
     * marcarse como caída detectada. 3 dB es un valor estándar de la industria para fibra —
     * suficiente para no disparar por el ruido normal de medición, pero sí por un conector
     * sucio/dañado o una fusión nueva de mala calidad. Configurable si hace falta ajustarlo.
     */
    private const UMBRAL_CAIDA_DB = 3.0;

    /**
     * Dashboard completo de una NAP (DoD #957): potencia promedio, conteos y la lista de ONUs.
     */
    public function calcular(string $puertableType, int $puertableId): array
    {
        $enlaces = MapaRedEnlaceServicio::porNap($puertableType, $puertableId)
            ->filter(fn ($enlace) => $enlace->estado === 'activo')
            ->values();

        $onusPorSerie = $this->buscarOnusPorSerie($enlaces->pluck('ont_serie'));

        $onus = $enlaces->map(function (MapaRedEnlaceServicio $enlace) use ($onusPorSerie) {
            $clave = $this->normalizarSerie($enlace->ont_serie);
            $onu = $clave !== null ? ($onusPorSerie[$clave] ?? null) : null;

            return [
                'cliente_nombre' => $enlace->cliente_nombre,
                'ont_serie' => $enlace->ont_serie,
                'estado' => $onu->status ?? 'sin dato',
                'senal' => $onu->signal ?? 'sin dato',
                'puerto_nap' => $enlace->puertoNap?->numero,
                'rx_dbm' => $onu->signal_1310 ?? null,
                'tx_dbm' => $onu->signal_1490 ?? null,
                'olt_nombre' => $onu->olt_name ?? null,
                'con_dato' => $onu !== null,
            ];
        })->values();

        $resumen = $this->resumen($onus);
        $resumen = $this->aplicarPotenciaInstalacion($puertableType, $puertableId, $resumen);

        return array_merge($resumen, ['onus' => $onus]);
    }

    /**
     * "Potencia de instalación" del NAP (sesión 2026-10-09, decisión de Irving): se autocaptura
     * con la primera lectura real disponible (nunca se sobreescribe sola después — solo un
     * PUT manual la corrige, ver actualizarPotenciaInstalacion()), y se compara contra la
     * potencia MÁS BAJA de las ONUs conectadas hoy para marcar "caída detectada" si empeoró
     * más de UMBRAL_CAIDA_DB. Solo aplica a NAPs (puertableType = MapaRedLayer) — un splitter
     * u otro contenedor no tiene este campo.
     */
    private function aplicarPotenciaInstalacion(string $puertableType, int $puertableId, array $resumen): array
    {
        $resumen['potencia_instalacion_dbm'] = null;
        $resumen['caida_detectada'] = false;

        if ($puertableType !== MapaRedLayer::class) {
            return $resumen;
        }

        $layer = MapaRedLayer::find($puertableId);
        if (!$layer) {
            return $resumen;
        }

        if ($layer->potencia_instalacion_dbm === null && $resumen['potencia_minima_dbm'] !== null) {
            try {
                $layer->potencia_instalacion_dbm = $resumen['potencia_minima_dbm'];
                $layer->saveQuietly();
            } catch (\Throwable $e) {
                // Best-effort: nunca romper el dashboard de salud por no poder guardar la
                // captura automática (mismo criterio que MapaRedHistorial::registrar()).
                Log::warning('[MapaRedNapHealthService] No se pudo autocapturar potencia de instalación', [
                    'layer_id' => $puertableId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $resumen['potencia_instalacion_dbm'] = $layer->potencia_instalacion_dbm !== null
            ? (float) $layer->potencia_instalacion_dbm
            : null;

        if ($resumen['potencia_instalacion_dbm'] !== null && $resumen['potencia_minima_dbm'] !== null) {
            $caida = $resumen['potencia_instalacion_dbm'] - $resumen['potencia_minima_dbm'];
            $resumen['caida_detectada'] = $caida >= self::UMBRAL_CAIDA_DB;
        }

        return $resumen;
    }

    /** Guarda (o corrige) a mano la potencia de instalación de un NAP. */
    public function actualizarPotenciaInstalacion(int $layerId, ?float $potenciaDbm): MapaRedLayer
    {
        $layer = MapaRedLayer::findOrFail($layerId);
        $layer->potencia_instalacion_dbm = $potenciaDbm;
        $layer->save();

        return $layer;
    }

    /**
     * Semáforo (y solo el semáforo) de varias NAPs del mismo tipo en una sola pasada — para
     * pintar el mapa completo sin hacer una consulta por marcador.
     *
     * @return array<int, array{semaforo:string,total_onus:int}>
     */
    public function calcularParaVarias(string $puertableType, array $puertableIds): array
    {
        $resultado = [];

        foreach ($puertableIds as $id) {
            $resumen = $this->calcular($puertableType, (int) $id);
            $resultado[(int) $id] = [
                'semaforo' => $resumen['semaforo'],
                'total_onus' => $resumen['total_onus'],
                'caida_detectada' => $resumen['caida_detectada'],
            ];
        }

        return $resultado;
    }

    private function resumen(Collection $onus): array
    {
        $conDatos = $onus->filter(fn ($fila) => $fila['con_dato']);

        $totalOnus = $onus->count();
        $onusActivas = $conDatos->filter(fn ($fila) => $fila['estado'] === 'Online')->count();
        $onusOffline = $conDatos->filter(fn ($fila) => in_array($fila['estado'], self::ESTADOS_OFFLINE, true))->count();
        $onusSenalBaja = $conDatos->filter(fn ($fila) => in_array($fila['senal'], ['Warning', 'Critical'], true))->count();
        $onusSinLectura = $totalOnus - $conDatos->count();

        $lecturasRx = $conDatos->pluck('rx_dbm')->filter(fn ($v) => $v !== null);
        $potenciaPromedioDbm = $lecturasRx->isNotEmpty() ? round($lecturasRx->avg(), 2) : null;
        // dBm: más negativo = más débil. La "más baja" es el mínimo matemático (ej. -27 < -19).
        $potenciaMinimaDbm = $lecturasRx->isNotEmpty() ? round($lecturasRx->min(), 2) : null;

        return [
            'semaforo' => $this->semaforo($conDatos),
            'potencia_promedio_dbm' => $potenciaPromedioDbm,
            'potencia_minima_dbm' => $potenciaMinimaDbm,
            'total_onus' => $totalOnus,
            'onus_activas' => $onusActivas,
            'onus_offline' => $onusOffline,
            'onus_senal_baja' => $onusSenalBaja,
            'onus_sin_lectura' => $onusSinLectura,
        ];
    }

    /**
     * D17: Verde señal estable · Amarillo variación · Rojo señal mala · Gris sin ONUs asociadas.
     * Sin serie histórica de lecturas (no existe tabla de tiempo real para esto), "variación" se
     * interpreta como dispersión de categorías entre las ONUs de la misma caja en el snapshot
     * actual (algunas en aviso) — decisión registrada vía circuito:reportar.
     */
    private function semaforo(Collection $conDatos): string
    {
        if ($conDatos->isEmpty()) {
            return self::GRIS;
        }

        $hayOffline = $conDatos->contains(fn ($fila) => in_array($fila['estado'], self::ESTADOS_OFFLINE, true));
        $haySenalCritica = $conDatos->contains(fn ($fila) => $fila['senal'] === 'Critical');

        if ($hayOffline || $haySenalCritica) {
            return self::ROJO;
        }

        $haySenalAviso = $conDatos->contains(fn ($fila) => $fila['senal'] === 'Warning');

        if ($haySenalAviso) {
            return self::AMARILLO;
        }

        return self::VERDE;
    }

    /**
     * @return Collection<string, OltOnu> keyBy serie normalizada (mayúsculas + trim, D19).
     */
    private function buscarOnusPorSerie(Collection $series): Collection
    {
        $series = $series->filter()->map(fn ($s) => $this->normalizarSerie($s))->unique()->values();

        if ($series->isEmpty()) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, $series->count(), '?'));

        return OltOnu::query()
            ->whereRaw("UPPER(TRIM(sn)) IN ({$placeholders})", $series->all())
            ->get()
            ->keyBy(fn (OltOnu $onu) => $this->normalizarSerie($onu->sn));
    }

    private function normalizarSerie(?string $serie): ?string
    {
        $serie = trim((string) $serie);

        return $serie === '' ? null : strtoupper($serie);
    }
}
