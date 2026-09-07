<?php

namespace App\Modules\Addons\MapaRed\Console;

use App\Modules\Addons\MapaRed\Models\MapaRedHilo;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * MR-15 (item roadmap #951) — Backfill: convierte lo que MR-05 (#941) copió del legado a
 * `mapared_layers` (espejo, MR-04/#940) al modelo nuevo (`mapared_hilos` MR-11/#947,
 * `mapared_puertos` MR-10/#946). Lee SOLO del espejo, nunca de `map_*` legado directo — el
 * comentario de MR-10 en la migración de `mapared_puertos` documenta que el espejo es
 * exactamente "la copia 1:1 del sistema legado para la migración de datos de MR-05/MR-15".
 *
 * Prohibido inventar datos: lo que no se puede deducir queda null y listado como
 * "no_convertible"; lo que se deduce pero no se puede persistir (falta una tabla destino,
 * p.ej. mapared_cables de MR-09/#945) queda "parcial" con el motivo explícito.
 */
class BackfillCommand extends Command
{
    protected $signature = 'mapared:backfill {--dry-run} {--zona=}';

    protected $description = 'MR-15: convierte cables/cajas de mapared_layers (espejo) al modelo nuevo (hilos/puertos)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $zonaFiltro = $this->option('zona') ? $this->normalizar($this->option('zona')) : null;

        $zonasConocidas = $this->zonasConocidas();

        $filasCables = $this->procesarCables($dryRun, $zonaFiltro, $zonasConocidas);
        $filasCajas = $this->procesarCajas($dryRun, $zonaFiltro, $zonasConocidas);

        $this->imprimirReporte('CABLES', $filasCables);
        $this->imprimirReporte('CAJAS (service_box / junction_box)', $filasCajas);
        $this->imprimirCoberturaPorZona(array_merge($filasCables, $filasCajas));

        if ($dryRun) {
            $this->warn('--dry-run: no se escribió nada en la base de datos.');
        }

        return self::SUCCESS;
    }

    /**
     * Nombres de zona conocidos: layers dialog='region' del espejo, ej. {"name":"TULTITLAN"}.
     */
    private function zonasConocidas(): array
    {
        return DB::table('mapared_layers')
            ->where('dialog', 'region')
            ->get(['data'])
            ->map(fn ($row) => $this->normalizar(json_decode($row->data, true)['name'] ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizar(string $texto): string
    {
        return trim(Str::of($texto)->ascii()->upper());
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

    /**
     * Deduce código de tipo de cable + cantidad de hilos por el nombre (FO96/FO288/FO24...).
     */
    private function deducirTipoCable(string $nombre): ?array
    {
        if (preg_match('/FO\s*-?\s*(\d+)/i', $nombre, $m)) {
            $hilos = (int) $m[1];

            return ['codigo_tipo' => 'FO' . $hilos, 'hilos_deducidos' => $hilos];
        }

        return null;
    }

    private function procesarCables(bool $dryRun, ?string $zonaFiltro, array $zonasConocidas): array
    {
        $rows = DB::table('mapared_layers')->where('dialog', 'route')->get();
        $filas = [];

        foreach ($rows as $row) {
            $data = json_decode($row->data, true) ?: [];
            $nombre = $data['name'] ?? $row->text ?? "(layer #{$row->id})";
            $zona = $this->detectarZona($nombre, $zonasConocidas);

            if ($zonaFiltro !== null && $zonaFiltro !== ($zona ?? '')) {
                continue;
            }

            $deducido = $this->deducirTipoCable($nombre);
            $fibersAmount = is_numeric($data['fibers_amount'] ?? null) ? (int) $data['fibers_amount'] : null;
            $numeroHilos = $fibersAmount ?? ($deducido['hilos_deducidos'] ?? null);
            $codigoTipo = $deducido['codigo_tipo'] ?? null;

            $fila = [
                'elemento' => 'cable',
                'origen_legacy_id' => $row->origen_legacy_id,
                'nombre' => $nombre,
                'zona' => $zona,
                'campo1' => 'tipo=' . ($codigoTipo ?? '?'),
                'campo2' => 'hilos=' . ($numeroHilos ?? '?'),
            ];

            if ($codigoTipo === null && $numeroHilos === null) {
                $fila['clasificacion'] = 'no_convertible';
                $fila['motivo'] = 'No se pudo deducir tipo de cable del nombre ni fibers_amount';
            } elseif (!Schema::hasTable('mapared_cables')) {
                $fila['clasificacion'] = 'parcial';
                $fila['motivo'] = 'Tipo/hilos deducidos pero no persistidos: falta mapared_cables (MR-09/#945 sin mergear a main)';
            } elseif ($numeroHilos === null) {
                $fila['clasificacion'] = 'parcial';
                $fila['motivo'] = 'Tipo de cable deducido, cantidad de hilos desconocida — no se instancian hilos sin ese dato';
            } elseif ($codigoTipo === null) {
                $fila['clasificacion'] = 'parcial';
                $fila['motivo'] = 'Cantidad de hilos conocida (fibers_amount), tipo de cable no se pudo deducir del nombre';
            } else {
                $cableId = $this->persistirCable($row, $nombre, $codigoTipo, $numeroHilos, $zona, $dryRun);
                $fila['clasificacion'] = 'convertido';
                $fila['motivo'] = $dryRun ? '(dry-run) se crearía cable id=? + ' . $numeroHilos . ' hilos' : "cable_id={$cableId} + {$numeroHilos} hilos";
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    private function persistirCable($row, string $nombre, string $codigoTipo, int $numeroHilos, ?string $zona, bool $dryRun): ?int
    {
        if ($dryRun) {
            return null;
        }

        $existente = DB::table('mapared_cables')->where('nombre', $nombre)->first();
        $cableId = $existente->id ?? null;

        if (!$cableId) {
            $cableId = DB::table('mapared_cables')->insertGetId([
                'nombre' => $nombre,
                'codigo_tipo' => $codigoTipo,
                'numero_hilos' => $numeroHilos,
                'zona' => $zona,
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($cableId && !MapaRedHilo::where('cable_id', $cableId)->exists()) {
            $this->instanciarHilos($cableId, $numeroHilos);
        }

        return $cableId;
    }

    private function instanciarHilos(int $cableId, int $numeroHilos, int $hilosPorBuffer = 12): void
    {
        $colores = config('fibers_color', []);
        $ahora = now();
        $filas = [];

        for ($i = 0; $i < $numeroHilos; $i++) {
            $buffer = intdiv($i, $hilosPorBuffer) + 1;
            $numero = ($i % $hilosPorBuffer) + 1;
            $filas[] = [
                'cable_id' => $cableId,
                'buffer' => $buffer,
                'numero' => $numero,
                'color' => $colores[$numero - 1] ?? null,
                'estado' => 'libre',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        MapaRedHilo::insert($filas);
    }

    /**
     * Cajas: service_box (NAP) tiene mapeo directo al rol nap_salida. junction_box (mufa) NO
     * tiene un rol equivalente en el enum de mapared_puertos — queda "parcial" (capacidad
     * conocida, sin poder crear el puerto sin ampliar el catálogo de roles).
     */
    private function procesarCajas(bool $dryRun, ?string $zonaFiltro, array $zonasConocidas): array
    {
        $rows = DB::table('mapared_layers')->whereIn('dialog', ['service_box', 'junction_box'])->get();
        $filas = [];

        foreach ($rows as $row) {
            $data = json_decode($row->data, true) ?: [];
            $nombre = $data['name'] ?? $row->text ?? "(layer #{$row->id})";
            $zona = $this->detectarZona($nombre, $zonasConocidas);

            if ($zonaFiltro !== null && $zonaFiltro !== ($zona ?? '')) {
                continue;
            }

            $capacidad = $row->inputs;

            $fila = [
                'elemento' => $row->dialog,
                'origen_legacy_id' => $row->origen_legacy_id,
                'nombre' => $nombre,
                'zona' => $zona,
                'campo1' => 'capacidad=' . $capacidad,
                'campo2' => '',
            ];

            if ($row->dialog === 'junction_box') {
                $fila['clasificacion'] = 'parcial';
                $fila['motivo'] = 'Capacidad conocida, pero el enum de rol de mapared_puertos no tiene valor para mufa (junction_box)';
                $filas[] = $fila;
                continue;
            }

            $yaTienePuertos = MapaRedPuerto::delDueno(MapaRedLayer::class, $row->id)->exists();
            if ($yaTienePuertos) {
                $fila['clasificacion'] = 'convertido';
                $fila['motivo'] = 'Ya tenía puertos (corrida previa del backfill)';
            } elseif ($dryRun) {
                $fila['clasificacion'] = 'convertido';
                $fila['motivo'] = "(dry-run) se crearían {$capacidad} puertos rol=nap_salida";
            } else {
                $layer = new MapaRedLayer();
                $layer->id = $row->id;
                MapaRedPuerto::generarParaSplitter(
                    $layer,
                    salidas: $capacidad,
                    entradas: 0,
                    rolSalida: MapaRedPuerto::ROL_NAP_SALIDA
                );
                $fila['clasificacion'] = 'convertido';
                $fila['motivo'] = "{$capacidad} puertos rol=nap_salida creados";
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    private function imprimirReporte(string $titulo, array $filas): void
    {
        $this->info("== {$titulo} ==");

        if (empty($filas)) {
            $this->line('  (0 elementos encontrados en el espejo mapared_layers)');

            return;
        }

        $this->table(
            ['Elemento', 'Nombre', 'Zona', 'Datos', 'Clasificación', 'Motivo'],
            array_map(fn ($f) => [
                $f['elemento'],
                Str::limit($f['nombre'], 40),
                $f['zona'] ?? '(sin zona)',
                trim($f['campo1'] . ' ' . $f['campo2']),
                $f['clasificacion'],
                $f['motivo'],
            ], $filas)
        );

        $resumen = collect($filas)->countBy('clasificacion');
        $this->line('  Convertido: ' . $resumen->get('convertido', 0)
            . ' | Parcial: ' . $resumen->get('parcial', 0)
            . ' | No convertible: ' . $resumen->get('no_convertible', 0));
    }

    private function imprimirCoberturaPorZona(array $todasLasFilas): void
    {
        $this->info('== COBERTURA POR ZONA ==');

        if (empty($todasLasFilas)) {
            $this->warn('0 elementos procesados en total — el espejo mapared_layers está vacío (MR-05/#941 sin completar la copia legado→espejo). Cobertura: 0%.');

            return;
        }

        $porZona = collect($todasLasFilas)->groupBy(fn ($f) => $f['zona'] ?? '(sin zona)');

        $filasTabla = [];
        foreach ($porZona as $zona => $filas) {
            $total = $filas->count();
            $convertido = $filas->where('clasificacion', 'convertido')->count();
            $pct = $total > 0 ? round(($convertido / $total) * 100, 1) : 0;
            $filasTabla[] = [$zona, $total, $convertido, "{$pct}%"];

            if (str_contains($zona, 'TULTITL') && $pct < 80) {
                $this->error("⚠ Zona '{$zona}' con cobertura {$pct}% (< 80%) — según el DoD de #951 esto exige crear un item de respuesta.");
            }
        }

        $this->table(['Zona', 'Total', 'Convertido', '% cobertura'], $filasTabla);
    }
}
