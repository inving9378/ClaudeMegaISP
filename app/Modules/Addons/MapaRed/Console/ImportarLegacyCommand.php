<?php

namespace App\Modules\Addons\MapaRed\Console;

use App\Modules\Addons\MapaRed\Models\MapaRedDevicePort;
use App\Modules\Addons\MapaRed\Models\MapaRedFiber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MR-05 (item roadmap #941) — Copia legado → espejo `mapared_*` (idempotente, con dry-run).
 *
 * Lectura PURA de las 9 tablas `map_*` confirmadas como el sistema vivo (MR-01a/MR-01b,
 * mismo alcance que el espejo de MR-04/#940). Escritura SOLO en las 9 tablas `mapared_*`
 * espejo — nunca en `map_*`, nunca en las entidades nuevas de MR-08+ (mapared_cables,
 * mapared_puertos, etc., que son responsabilidad de MR-15/#951 vía `mapared:backfill`).
 *
 * Idempotencia: cada fila espejo se inserta con el MISMO `id` que su fila legado
 * (`origen_legacy_id` = `id`) — así las referencias `*_id` internas del legado
 * (layer_id, device_id, project_id, parent_id, …) siguen apuntando correctas sin
 * necesidad de remapeo, y una corrida repetida hace upsert por `id` en vez de duplicar.
 * Los strings de clase polimórficos legado (`App\Models\MapDevicePort`, `App\Models\MapFiber`)
 * SÍ se remapean a su clase MapaRed viva, porque `MapaRedDevicePortConnection::from()/to()`
 * (morphTo, ya en uso por los controllers portados en MR-06a) los resuelve como clase real.
 */
class ImportarLegacyCommand extends Command
{
    protected $signature = 'mapared:importar-legacy {--dry-run} {--zona=} {--truncar}';

    protected $description = 'MR-05: copia las 9 tablas map_* legado a su espejo mapared_* (idempotente por origen_legacy_id)';

    /** Remapeo de clase legado → clase MapaRed viva para columnas polimórficas (*_type). */
    private const MAPA_TIPOS = [
        'App\\Models\\MapDevicePort' => MapaRedDevicePort::class,
        'App\\Models\\MapFiber' => MapaRedFiber::class,
    ];

    private const TABLAS_MIRROR = [
        'mapared_devices_ports_connections',
        'mapared_layers_routes',
        'mapared_fibers_cut',
        'mapared_fibers',
        'mapared_devices_ports',
        'mapared_ports',
        'mapared_devices',
        'mapared_layers',
        'mapared_proyects',
    ];

    private bool $dryRun = false;

    /** @var array<int, string|null> legacy layer id => zona detectada */
    private array $layerZona = [];

    /** @var array<int, true> legacy layer ids incluidos tras el filtro --zona */
    private array $layerIncluido = [];

    /** @var array<int, array{lat: float|null, lng: float|null}> legacy layer id => geometría derivada */
    private array $layerLatLng = [];

    /** @var array<int, true> legacy device ids incluidos (cascada de --zona vía layer_id) */
    private array $deviceIncluido = [];

    /** @var array<int, array{lat: float|null, lng: float|null}> legacy device id => lat/lng heredado del layer */
    private array $deviceLatLng = [];

    private array $legacyIds = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $zonaFiltro = $this->option('zona') ? $this->normalizar($this->option('zona')) : null;
        $truncar = (bool) $this->option('truncar');

        if ($truncar) {
            if ($this->dryRun) {
                $this->warn('(dry-run) --truncar: se vaciarían las ' . count(self::TABLAS_MIRROR) . ' tablas mapared_* antes de importar. No se ejecuta.');
            } else {
                $this->truncarMirror();
            }
        }

        $this->cargarIdsLegacy();

        $reportes = [];
        $reportes['mapared_proyects'] = $this->procesarProyects();
        $reportes['mapared_layers'] = $this->procesarLayers($zonaFiltro);
        $reportes['mapared_devices'] = $this->procesarDevices($zonaFiltro);
        $reportes['mapared_devices_ports'] = $this->procesarDevicesPorts($zonaFiltro);
        $reportes['mapared_fibers'] = $this->procesarFibers($zonaFiltro);
        $reportes['mapared_fibers_cut'] = $this->procesarFibersCut($zonaFiltro);
        $reportes['mapared_layers_routes'] = $this->procesarLayersRoutes($zonaFiltro);
        $reportes['mapared_devices_ports_connections'] = $this->procesarDevicesPortsConnections($zonaFiltro);
        $reportes['mapared_ports'] = $this->procesarPorts();

        $this->imprimirReporte($reportes);

        if ($this->dryRun) {
            $this->warn('--dry-run: no se escribió nada en la base de datos.');
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Utilidades de zona (mismo criterio que mapared:backfill / MR-15)
    // ------------------------------------------------------------------

    private function normalizar(string $texto): string
    {
        return trim(Str::of($texto)->ascii()->upper());
    }

    private function zonasConocidas(): array
    {
        return DB::table('map_layers')
            ->where('dialog', 'region')
            ->get(['data', 'text'])
            ->map(fn ($row) => $this->normalizar((json_decode($row->data, true)['name'] ?? null) ?? $row->text ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function detectarZona(string $texto, array $zonasConocidas): ?string
    {
        $textoNorm = $this->normalizar($texto);
        foreach ($zonasConocidas as $zona) {
            if ($zona !== '' && str_contains($textoNorm, $zona)) {
                return $zona;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Geometría (D8) — solo map_layers tiene coords reales; el resto hereda
    // el lat/lng de su layer cuando se puede resolver, sin inventar datos.
    // ------------------------------------------------------------------

    /**
     * `coords` legado tiene 3 formas según `type` (marker={lat,lng}; polyline=[{lat,lng},…];
     * polygon=[[{lat,lng},…]], anillo(s) anidados un nivel extra) — se camina recursivo para
     * no depender de la forma exacta y cubrir las 3 con el mismo código.
     */
    private function extraerPuntos($nodo, array &$puntos): void
    {
        if (!is_array($nodo)) {
            return;
        }
        if (isset($nodo['lat'], $nodo['lng']) && is_numeric($nodo['lat']) && is_numeric($nodo['lng'])) {
            $puntos[] = [(float) $nodo['lat'], (float) $nodo['lng']];

            return;
        }
        foreach ($nodo as $hijo) {
            $this->extraerPuntos($hijo, $puntos);
        }
    }

    private function derivarGeometria(?string $coordsRaw): array
    {
        $coords = json_decode($coordsRaw ?? '', true);
        $puntos = [];
        $this->extraerPuntos($coords, $puntos);

        if (empty($puntos)) {
            return ['ok' => false, 'lat' => null, 'lng' => null, 'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null];
        }

        $lats = array_column($puntos, 0);
        $lngs = array_column($puntos, 1);

        return [
            'ok' => true,
            'lat' => array_sum($lats) / count($lats),
            'lng' => array_sum($lngs) / count($lngs),
            'bbox_min_lat' => min($lats),
            'bbox_max_lat' => max($lats),
            'bbox_min_lng' => min($lngs),
            'bbox_max_lng' => max($lngs),
        ];
    }

    // ------------------------------------------------------------------
    // Set-up
    // ------------------------------------------------------------------

    private function truncarMirror(): void
    {
        foreach (self::TABLAS_MIRROR as $tabla) {
            DB::table($tabla)->truncate();
        }
        $this->info('--truncar: ' . count(self::TABLAS_MIRROR) . ' tablas mapared_* vaciadas (map_* legado intacto).');
    }

    /** Sets de ids legado (para validar "padre inexistente" contra el origen, no contra el destino). */
    private function cargarIdsLegacy(): void
    {
        foreach (['map_proyects', 'map_layers', 'map_devices', 'map_devices_ports', 'map_fibers'] as $tabla) {
            $this->legacyIds[$tabla] = array_flip(DB::table($tabla)->pluck('id')->all());
        }
    }

    private function existeLegacy(string $tabla, $id): bool
    {
        return $id !== null && isset($this->legacyIds[$tabla][$id]);
    }

    // ------------------------------------------------------------------
    // Por tabla
    // ------------------------------------------------------------------

    private function procesarProyects(): array
    {
        $rows = DB::table('map_proyects')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];

        foreach ($rows as $row) {
            if ($row->parent_id !== null && !$this->existeLegacy('map_proyects', $row->parent_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: parent_id={$row->parent_id}"];
                continue;
            }

            $insertar[] = [
                'id' => $row->id,
                'name' => $row->name,
                'parent_id' => $row->parent_id,
                'classification' => $row->classification,
                'level' => $row->level,
                'created_by' => $row->created_by,
                'updated_by' => $row->updated_by,
                'lat' => null, 'lng' => null, 'geom_json' => null,
                'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null,
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_proyects', $rows->count(), $insertar, $omitidas);
    }

    private function procesarLayers(?string $zonaFiltro): array
    {
        $zonasConocidas = $this->zonasConocidas();
        $rows = DB::table('map_layers')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];
        $leidas = 0;

        foreach ($rows as $row) {
            $data = json_decode($row->data, true) ?: [];
            $nombre = $data['name'] ?? $row->text ?? "(layer #{$row->id})";
            $zona = $this->detectarZona($nombre, $zonasConocidas);

            if ($zonaFiltro !== null && $zonaFiltro !== ($zona ?? '')) {
                continue;
            }

            $leidas++;
            $this->layerZona[$row->id] = $zona;

            if ($row->project_id !== null && !$this->existeLegacy('map_proyects', $row->project_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: project_id={$row->project_id}"];
                continue;
            }
            if ($row->service_box_id !== null && !$this->existeLegacy('map_layers', $row->service_box_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: service_box_id={$row->service_box_id}"];
                continue;
            }

            $geo = $this->derivarGeometria($row->coords);
            if (!$geo['ok']) {
                $omitidas[] = ['id' => $row->id, 'motivo' => 'lat/lng nula: coords vacío o formato desconocido'];
                continue;
            }

            $this->layerIncluido[$row->id] = true;
            $this->layerLatLng[$row->id] = ['lat' => $geo['lat'], 'lng' => $geo['lng']];

            $insertar[] = [
                'id' => $row->id,
                'project_id' => $row->project_id,
                'classification' => $row->classification,
                'type' => $row->type,
                'color' => $row->color,
                'route' => $row->route,
                'dialog' => $row->dialog,
                'text' => $row->text,
                'icon' => $row->icon,
                'icon_color' => $row->icon_color,
                'weight' => $row->weight,
                'distance' => $row->distance,
                'label' => $row->label,
                'layerable_id' => $row->layerable_id,
                'layerable_type' => $row->layerable_type,
                'service_box_id' => $row->service_box_id,
                'coords' => $row->coords,
                'data' => $row->data,
                'inputs' => $row->inputs,
                'level' => $row->level,
                'lat' => $geo['lat'], 'lng' => $geo['lng'], 'geom_json' => $row->coords,
                'bbox_min_lat' => $geo['bbox_min_lat'], 'bbox_max_lat' => $geo['bbox_max_lat'],
                'bbox_min_lng' => $geo['bbox_min_lng'], 'bbox_max_lng' => $geo['bbox_max_lng'],
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_layers', $leidas, $insertar, $omitidas);
    }

    private function procesarDevices(?string $zonaFiltro): array
    {
        $rows = DB::table('map_devices')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];
        $leidas = 0;

        foreach ($rows as $row) {
            if ($zonaFiltro !== null && !isset($this->layerIncluido[$row->layer_id])) {
                continue;
            }
            $leidas++;

            if ($row->layer_id !== null && !$this->existeLegacy('map_layers', $row->layer_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: layer_id={$row->layer_id}"];
                continue;
            }
            if ($row->parent_id !== null && !$this->existeLegacy('map_devices', $row->parent_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: parent_id={$row->parent_id}"];
                continue;
            }

            $heredado = $this->layerLatLng[$row->layer_id] ?? ['lat' => null, 'lng' => null];
            $this->deviceIncluido[$row->id] = true;
            $this->deviceLatLng[$row->id] = $heredado;

            $insertar[] = [
                'id' => $row->id,
                'name' => $row->name,
                'type' => $row->type,
                'description' => $row->description,
                'position_x' => $row->position_x,
                'position_y' => $row->position_y,
                'orientation' => $row->orientation,
                'layer_id' => $row->layer_id,
                'parent_id' => $row->parent_id,
                'data' => $row->data,
                'lat' => $heredado['lat'], 'lng' => $heredado['lng'], 'geom_json' => null,
                'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null,
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_devices', $leidas, $insertar, $omitidas);
    }

    private function procesarDevicesPorts(?string $zonaFiltro): array
    {
        $rows = DB::table('map_devices_ports')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];
        $leidas = 0;

        foreach ($rows as $row) {
            if ($zonaFiltro !== null && !isset($this->deviceIncluido[$row->device_id])) {
                continue;
            }
            $leidas++;

            if ($row->device_id !== null && !$this->existeLegacy('map_devices', $row->device_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: device_id={$row->device_id}"];
                continue;
            }

            $heredado = $this->deviceLatLng[$row->device_id] ?? ['lat' => null, 'lng' => null];

            $insertar[] = [
                'id' => $row->id,
                'name' => $row->name,
                'type' => $row->type,
                'orientation' => $row->orientation,
                'device_id' => $row->device_id,
                'client_id' => $row->client_id,
                'connected' => $row->connected,
                'transfer' => $row->transfer,
                'transfer_type' => $row->transfer_type,
                'card' => $row->card,
                'note' => $row->note,
                'zone' => $row->zone,
                'data' => $row->data,
                'lat' => $heredado['lat'], 'lng' => $heredado['lng'], 'geom_json' => null,
                'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null,
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_devices_ports', $leidas, $insertar, $omitidas);
    }

    private function procesarFibers(?string $zonaFiltro): array
    {
        $rows = DB::table('map_fibers')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];
        $leidas = 0;

        foreach ($rows as $row) {
            if ($zonaFiltro !== null && !isset($this->layerIncluido[$row->fiber_id])) {
                continue;
            }
            $leidas++;

            if (!$this->existeLegacy('map_layers', $row->fiber_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: fiber_id(layer)={$row->fiber_id}"];
                continue;
            }

            $heredado = $this->layerLatLng[$row->fiber_id] ?? ['lat' => null, 'lng' => null];

            $insertar[] = [
                'id' => $row->id,
                'parent_buffer' => $row->parent_buffer,
                'buffer' => $row->buffer,
                'number' => $row->number,
                'color' => $row->color,
                'fiber_id' => $row->fiber_id,
                'zone' => $row->zone,
                'lat' => $heredado['lat'], 'lng' => $heredado['lng'], 'geom_json' => null,
                'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null,
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_fibers', $leidas, $insertar, $omitidas);
    }

    private function procesarFibersCut(?string $zonaFiltro): array
    {
        $rows = DB::table('map_fibers_cut')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];
        $leidas = 0;

        foreach ($rows as $row) {
            if ($zonaFiltro !== null && !isset($this->layerIncluido[$row->layer_id])) {
                continue;
            }
            $leidas++;

            if (!$this->existeLegacy('map_fibers', $row->fiber_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: fiber_id={$row->fiber_id}"];
                continue;
            }
            if (!$this->existeLegacy('map_layers', $row->layer_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: layer_id={$row->layer_id}"];
                continue;
            }

            $heredado = $this->layerLatLng[$row->layer_id] ?? ['lat' => null, 'lng' => null];

            $insertar[] = [
                'id' => $row->id,
                'fiber_id' => $row->fiber_id,
                'layer_id' => $row->layer_id,
                'state' => $row->state,
                'current_input' => $row->current_input,
                'route_id' => $row->route_id,
                'lat' => $heredado['lat'], 'lng' => $heredado['lng'], 'geom_json' => null,
                'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null,
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_fibers_cut', $leidas, $insertar, $omitidas);
    }

    private function procesarLayersRoutes(?string $zonaFiltro): array
    {
        $rows = DB::table('map_layers_routes')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];
        $leidas = 0;

        foreach ($rows as $row) {
            if ($zonaFiltro !== null && (!isset($this->layerIncluido[$row->route_id]) || !isset($this->layerIncluido[$row->layer_id]))) {
                continue;
            }
            $leidas++;

            if (!$this->existeLegacy('map_layers', $row->route_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: route_id={$row->route_id}"];
                continue;
            }
            if (!$this->existeLegacy('map_layers', $row->layer_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: layer_id={$row->layer_id}"];
                continue;
            }

            $insertar[] = [
                'id' => $row->id,
                'route_id' => $row->route_id,
                'layer_id' => $row->layer_id,
                'position_x' => $row->position_x,
                'position_y' => $row->position_y,
                'direction' => $row->direction,
                'input' => $row->input,
                'calculate_distance' => $row->calculate_distance,
                'real_distance' => $row->real_distance,
                'lat' => null, 'lng' => null, 'geom_json' => null,
                'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null,
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_layers_routes', $leidas, $insertar, $omitidas);
    }

    private function procesarDevicesPortsConnections(?string $zonaFiltro): array
    {
        $rows = DB::table('map_devices_ports_connections')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];
        $leidas = 0;

        foreach ($rows as $row) {
            if ($zonaFiltro !== null && !isset($this->layerIncluido[$row->layer_id])) {
                continue;
            }
            $leidas++;

            $fromTipo = self::MAPA_TIPOS[$row->from_type] ?? null;
            $toTipo = self::MAPA_TIPOS[$row->to_type] ?? null;
            if ($fromTipo === null || $toTipo === null) {
                $claseDesconocida = $fromTipo === null ? $row->from_type : $row->to_type;
                $omitidas[] = ['id' => $row->id, 'motivo' => "tipo desconocido: {$claseDesconocida}"];
                continue;
            }

            if (!$this->existeLegacy('map_layers', $row->layer_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: layer_id={$row->layer_id}"];
                continue;
            }

            $tablaFrom = $row->from_type === 'App\\Models\\MapDevicePort' ? 'map_devices_ports' : 'map_fibers';
            $tablaTo = $row->to_type === 'App\\Models\\MapDevicePort' ? 'map_devices_ports' : 'map_fibers';
            if (!$this->existeLegacy($tablaFrom, $row->from_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: from_id={$row->from_id}"];
                continue;
            }
            if (!$this->existeLegacy($tablaTo, $row->to_id)) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "padre inexistente: to_id={$row->to_id}"];
                continue;
            }

            $heredado = $this->layerLatLng[$row->layer_id] ?? ['lat' => null, 'lng' => null];

            $insertar[] = [
                'id' => $row->id,
                'from_type' => $fromTipo,
                'from_id' => $row->from_id,
                'from_input' => $row->from_input,
                'to_type' => $toTipo,
                'to_id' => $row->to_id,
                'to_input' => $row->to_input,
                'from_element' => $row->from_element,
                'to_element' => $row->to_element,
                'from_route_id' => $row->from_route_id,
                'to_route_id' => $row->to_route_id,
                'connection_type' => $row->connection_type,
                'type' => $row->type,
                'color' => $row->color,
                'width' => $row->width,
                'animate' => $row->animate,
                'layer_id' => $row->layer_id,
                'data' => $row->data,
                'lat' => $heredado['lat'], 'lng' => $heredado['lng'], 'geom_json' => null,
                'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null,
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_devices_ports_connections', $leidas, $insertar, $omitidas);
    }

    /**
     * map_ports (17 filas): device_type único observado = 'App\Models\MapSplitter', clase que
     * ya no existe en el codebase (sin equivalente MapaRed confiable) — se omiten TODAS
     * reportando "tipo desconocido" en vez de inventar un remapeo. No hay --zona aplicable
     * (map_ports no cuelga de ningún layer).
     */
    private function procesarPorts(): array
    {
        $rows = DB::table('map_ports')->orderBy('id')->get();
        $insertar = [];
        $omitidas = [];

        foreach ($rows as $row) {
            $tipo = self::MAPA_TIPOS[$row->device_type] ?? null;
            if ($tipo === null) {
                $omitidas[] = ['id' => $row->id, 'motivo' => "tipo desconocido: {$row->device_type}"];
                continue;
            }

            $insertar[] = [
                'id' => $row->id,
                'name' => $row->name,
                'position_x' => $row->position_x,
                'position_y' => $row->position_y,
                'type' => $row->type,
                'orientation' => $row->orientation,
                'client_id' => $row->client_id,
                'connected' => $row->connected,
                'transfer' => $row->transfer,
                'transfer_type' => $row->transfer_type,
                'card' => $row->card,
                'note' => $row->note,
                'device_type' => $tipo,
                'device_id' => $row->device_id,
                'lat' => null, 'lng' => null, 'geom_json' => null,
                'bbox_min_lat' => null, 'bbox_max_lat' => null, 'bbox_min_lng' => null, 'bbox_max_lng' => null,
                'empresa_id' => null,
                'origen_legacy_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $this->escribir('mapared_ports', $rows->count(), $insertar, $omitidas);
    }

    // ------------------------------------------------------------------
    // Escritura + reporte
    // ------------------------------------------------------------------

    private function escribir(string $tabla, int $leidas, array $insertar, array $omitidas): array
    {
        $existentes = $this->dryRun
            ? []
            : array_flip(DB::table($tabla)->pluck('id')->all());

        $insertadas = 0;
        $actualizadas = 0;
        foreach ($insertar as $fila) {
            if (isset($existentes[$fila['id']])) {
                $actualizadas++;
            } else {
                $insertadas++;
            }
        }

        if (!$this->dryRun) {
            foreach (array_chunk($insertar, 500) as $lote) {
                DB::table($tabla)->upsert($lote, ['id'], array_diff(array_keys($lote[0]), ['id']));
            }
        }

        return [
            'leidas' => $leidas,
            'insertadas' => $insertadas,
            'actualizadas' => $actualizadas,
            'omitidas' => $omitidas,
        ];
    }

    private function imprimirReporte(array $reportes): void
    {
        $this->info('== RESUMEN mapared:importar-legacy ==');
        $filas = [];
        $totalOmitidas = [];
        foreach ($reportes as $tabla => $r) {
            $filas[] = [$tabla, $r['leidas'], $r['insertadas'], $r['actualizadas'], count($r['omitidas'])];
            foreach ($r['omitidas'] as $o) {
                $totalOmitidas[] = [$tabla, $o['id'], $o['motivo']];
            }
        }
        $this->table(['Tabla', 'Leídas', 'Insertadas', 'Actualizadas', 'Omitidas'], $filas);

        if (!empty($totalOmitidas)) {
            $this->info('== DETALLE DE OMITIDAS ==');
            $this->table(['Tabla', 'origen_legacy_id', 'Motivo'], $totalOmitidas);
        } else {
            $this->info('0 filas omitidas.');
        }
    }
}
