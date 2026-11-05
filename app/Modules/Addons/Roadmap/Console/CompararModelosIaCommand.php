<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use App\Modules\Addons\Roadmap\Services\ValvulaContextoService;
use App\Modules\Addons\Roadmap\Support\IaSupervisor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #1076 — COMPARACIÓN REAL de modelos para una pieza del supervisor. CUESTA DINERO.
 *
 * Toma items ya decididos, vuelve a correr la pieza con cada modelo candidato SIN escribir nada
 * (cada item corre dentro de una transacción que se deshace) y mide cuánto coincide con el
 * veredicto de referencia. La referencia es el veredicto registrado en el item (log de la válvula,
 * `circuito_revisiones`); si no hay ninguno, el que da hoy el modelo de `--referencia`.
 *
 *   válvula  → ¿«mención» o «acción»? (mismo veredicto = acuerdo)
 *   revisor  → ¿autoriza o escala?
 *   briefs   → contra el brief estructurado que ya tiene el item: no hay acuerdo automático,
 *              se guarda lado a lado para juzgar si opciones y recomendación son equivalentes.
 *
 * Sin `--confirmar` solo muestra los items elegidos y el costo estimado: no llama a la API.
 * Topes duros: `--max-llamadas` y `--max-mxn` (se detiene antes de pasarse).
 */
class CompararModelosIaCommand extends Command
{
    protected $signature = 'circuito:comparar-modelos-ia
        {pieza : valvula | revisor | briefs}
        {--modelos=claude-haiku-5-5,claude-sonnet-5-5 : candidatos, separados por coma}
        {--referencia= : modelo que da el veredicto de referencia cuando el item no trae uno registrado}
        {--n=20 : cuántos items}
        {--items= : ids exactos, separados por coma}
        {--max-llamadas=80 : tope de llamadas a la API}
        {--max-mxn=30 : tope de gasto en pesos}
        {--confirmar : sin esto no llama a la API}
        {--salida= : JSON con el detalle (por defecto storage/app/circuito/comparacion-ia-<pieza>-<fecha>.json)}';

    protected $description = 'Compara modelos de una pieza del supervisor contra veredictos ya decididos (cuesta dinero; sin --confirmar solo planea).';

    private int $llamadas = 0;

    private float $gastoUsd = 0.0;

    public function handle(RevisorService $revisor, ValvulaContextoService $valvula): int
    {
        $pieza = (string) $this->argument('pieza');
        if (! in_array($pieza, ['valvula', 'revisor', 'briefs'], true)) {
            $this->error('Pieza: valvula | revisor | briefs');

            return self::FAILURE;
        }

        $modelos = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('modelos')))));
        $ref     = trim((string) $this->option('referencia')) ?: null;
        $items   = $this->elegir($pieza, $revisor);

        $this->info(count($items) . " item(s) para «{$pieza}» · candidatos: " . implode(', ', $modelos)
            . ($ref ? " · referencia si falta: {$ref}" : ''));
        foreach ($items as $it) {
            $this->line("  #{$it['item']->id} " . mb_strimwidth((string) $it['item']->title, 0, 70, '…')
                . ($it['termino'] ? "  [término: {$it['termino']}]" : '')
                . ($it['referencia'] !== null ? '  [ref. registrada: ' . $this->etiqueta($it['referencia']) . ']' : ''));
        }

        if (! $this->option('confirmar')) {
            $this->warn('Plan solamente (sin --confirmar no se llama a la API).');

            return self::SUCCESS;
        }

        $tc       = ClaudeApiClient::tipoCambioMxn();
        $maxLl    = max(1, (int) $this->option('max-llamadas'));
        $maxUsd   = max(0.01, (float) $this->option('max-mxn')) / $tc;
        $detalle  = [];

        foreach ($items as $it) {
            $porItem = count($modelos) + ($it['referencia'] === null && $ref ? 1 : 0);
            $promedio = $this->llamadas > 0 ? $this->gastoUsd / $this->llamadas : 0.0;
            if ($this->llamadas + $porItem > $maxLl || $this->gastoUsd + $promedio * $porItem > $maxUsd) {
                $this->warn('Tope de llamadas o de gasto alcanzado: se detiene antes de pasarse.');
                break;
            }

            $item = $it['item'];
            $fila = ['item' => $item->id, 'titulo' => $item->title, 'termino' => $it['termino'],
                'referencia' => $it['referencia'], 'referencia_origen' => $it['referencia'] !== null ? 'registrada' : null, 'resultados' => []];

            if ($fila['referencia'] === null && $ref) {
                $r = $this->correr($pieza, $ref, $item, $it['termino'], $revisor, $valvula);
                $fila['referencia'] = $r['veredicto'];
                $fila['referencia_origen'] = $ref;
                $fila['resultados'][$ref] = $r;
            }

            foreach ($modelos as $m) {
                $fila['resultados'][$m] = $this->correr($pieza, $m, $item, $it['termino'], $revisor, $valvula);
            }

            $detalle[] = $fila;
            $this->line("  #{$item->id} ref=" . $this->etiqueta($fila['referencia']) . ' · '
                . implode(' · ', array_map(fn ($m) => "{$m}=" . $this->etiqueta($fila['resultados'][$m]['veredicto'] ?? null), $modelos)));
        }

        $resumen = $this->resumir($pieza, $detalle, array_merge($modelos, $ref ? [$ref] : []), $tc);
        $this->newLine();
        $this->table(['Modelo', 'Items', 'Acuerdo', '% acuerdo', 'No concluyente', 'Entrada prom.', 'Salida prom.', 'MXN/llamada'],
            array_map(fn ($r) => [$r['modelo'], $r['items'], $r['acuerdos'], $r['pct'] === null ? '—' : $r['pct'] . ' %', $r['no_concluyente'],
                $r['entrada_prom'], $r['salida_prom'], number_format($r['mxn_por_llamada'], 4)], $resumen));
        $this->line(sprintf('Llamadas: %d · gasto USD %.4f · MXN %.2f', $this->llamadas, $this->gastoUsd, $this->gastoUsd * $tc));

        $salida = (string) ($this->option('salida') ?: storage_path('app/circuito/comparacion-ia-' . $pieza . '-' . now()->format('Ymd-His') . '.json'));
        @mkdir(dirname($salida), 0775, true);
        file_put_contents($salida, json_encode(['pieza' => $pieza, 'resumen' => $resumen, 'detalle' => $detalle,
            'llamadas' => $this->llamadas, 'usd' => round($this->gastoUsd, 6)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Detalle: {$salida}");

        return self::SUCCESS;
    }

    /**
     * Una corrida de la pieza con un modelo forzado, dentro de una transacción que se deshace:
     * nada de lo que la pieza intente escribir (auditoría de un fallo, etc.) queda en la base.
     */
    private function correr(string $pieza, string $modelo, RoadmapItem $item, ?string $termino, RevisorService $revisor, ValvulaContextoService $valvula): array
    {
        $clave = ['valvula' => IaSupervisor::VALVULA, 'revisor' => IaSupervisor::REVISOR, 'briefs' => IaSupervisor::BRIEFS][$pieza];
        IaSupervisor::forzar([$clave => $modelo]);
        $antes = $this->ultimoUsage();
        DB::beginTransaction();
        try {
            $t0 = microtime(true);
            if ($pieza === 'valvula') {
                $v = $valvula->evaluar($item, (string) $termino);
                $out = ['veredicto' => $v['veredicto'], 'ok' => $v['ok'], 'razon' => $v['razon']];
            } elseif ($pieza === 'revisor') {
                $r = $revisor->soloVeredicto($item, $item->comentarios_claude);
                $ok = ($r['v']['_ok'] ?? true) !== false;
                $out = ['veredicto' => $ok ? $r['v']['veredicto'] : null, 'ok' => $ok, 'confianza' => $r['v']['confianza'] ?? null,
                    'categoria' => $r['v']['categoria_escalada'] ?? null, 'razon' => $r['v']['razon'] ?? null];
            } else {
                $r = $revisor->proponerPreguntas($item, false);
                $out = ['veredicto' => $r['preguntas'] ?? [], 'ok' => (bool) ($r['ok'] ?? false), 'razon' => $r['error'] ?? ($r['motivo'] ?? null)];
            }
            $out['segundos'] = round(microtime(true) - $t0, 1);
        } catch (\Throwable $e) {
            $out = ['veredicto' => null, 'ok' => false, 'razon' => mb_strimwidth($e->getMessage(), 0, 200, '…')];
        } finally {
            DB::rollBack();
            IaSupervisor::forzar([]);
        }

        $usage = $this->ultimoUsage();
        $out['usd'] = 0.0;
        if ($usage !== null && $usage['linea'] !== ($antes['linea'] ?? -1)) {
            $this->llamadas++;
            if (is_array($usage['usage'])) {
                $usd = ClaudeApiClient::costoUsd($usage['usage'], $modelo);
                $this->gastoUsd += $usd;
                $out['usage'] = $usage['usage'];
                $out['usd'] = round($usd, 6);
            }
        }

        return $out;
    }

    /** Última línea «Claude API call» del log de hoy (para saber qué gastó la corrida). */
    private function ultimoUsage(): ?array
    {
        $ruta = storage_path('logs/claude-api-' . now()->format('Y-m-d') . '.log');
        if (! is_readable($ruta)) {
            return null;
        }
        $lineas = @file($ruta, FILE_IGNORE_NEW_LINES) ?: [];
        for ($i = count($lineas) - 1; $i >= 0; $i--) {
            if (str_contains($lineas[$i], 'Claude API call')) {
                $d = \App\Modules\Addons\Roadmap\Support\GastoIa::parsear($lineas[$i]);
                if ($d && is_array($d['usage'] ?? null)) {
                    return ['linea' => $i, 'usage' => $d['usage']];
                }

                return ['linea' => $i, 'usage' => null];
            }
        }

        return null;
    }

    /** @return array<int, array{item:RoadmapItem, termino:?string, referencia:mixed}> */
    private function elegir(string $pieza, RevisorService $revisor): array
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('items')))));
        $n   = max(1, (int) $this->option('n'));
        $q   = RoadmapItem::query()->orderByDesc('id');
        $base = $ids ? $q->whereIn('id', $ids)->get() : $q->limit(600)->get();

        $out = [];
        $porTermino = [];
        foreach ($base as $item) {
            if ($pieza === 'valvula') {
                $t = $revisor->triarNivelNull($item);
                $termino = $t['match'] ?? null;
                if (($t['nivel'] ?? null) !== 'C' || ! $termino || $termino === 'freno_humano'
                    || ! ValvulaContextoService::terminoPresente(ValvulaContextoService::textoVisible($item), $termino)) {
                    continue;
                }
                // Variedad: a lo más 3 items por término, salvo que se pidieran ids exactos.
                if (! $ids && ($porTermino[$termino] = ($porTermino[$termino] ?? 0) + 1) > 3) {
                    continue;
                }
                $out[] = ['item' => $item, 'termino' => $termino, 'referencia' => $this->valvulaRegistrada($item, $termino)];
            } elseif ($pieza === 'revisor') {
                if (! $ids && (($item->nivel_riesgo ?? null) !== 'B' || ! $revisor->enAlcance($item)['en_alcance'])) {
                    continue;
                }
                $out[] = ['item' => $item, 'termino' => null, 'referencia' => $this->revisorRegistrado($item)];
            } else {
                $pregs = (array) $item->preguntas;
                $conConfianza = collect($pregs)->flatMap(fn ($p) => (array) ($p['opciones'] ?? []))
                    ->contains(fn ($o) => is_array($o) && ! empty($o['confianza']));
                if (! $conConfianza) {
                    continue;
                }
                $out[] = ['item' => $item, 'termino' => null, 'referencia' => $pregs];
            }
            if (count($out) >= $n) {
                break;
            }
        }

        return $out;
    }

    private function valvulaRegistrada(RoadmapItem $item, string $termino): ?string
    {
        foreach (array_reverse((array) $item->log) as $e) {
            if (is_array($e) && in_array($e['evento'] ?? null, ['valvula_contexto', 'valvula_nacimiento'], true)
                && ! empty($e['ok']) && ($e['termino'] ?? null) === $termino && in_array($e['veredicto'] ?? null, ['accion', 'mencion'], true)) {
                return $e['veredicto'];
            }
        }

        return null;
    }

    private function revisorRegistrado(RoadmapItem $item): ?string
    {
        $v = DB::table('circuito_revisiones')->where('roadmap_item_id', $item->id)
            ->whereNotNull('modelo')->where('en_alcance', true)
            ->whereNotIn('categoria_escalada', RevisorService::CATEGORIAS_SIN_JUICIO)
            ->orderByDesc('id')->value('veredicto');

        return $v ?: null;
    }

    private function resumir(string $pieza, array $detalle, array $modelos, float $tc): array
    {
        $out = [];
        foreach (array_unique($modelos) as $m) {
            $items = $acuerdos = $nc = $entrada = $salida = 0;
            $usd = 0.0;
            $conUsage = 0;
            foreach ($detalle as $f) {
                $r = $f['resultados'][$m] ?? null;
                if ($r === null) {
                    continue;
                }
                $items++;
                if (! empty($r['usage'])) {
                    $conUsage++;
                    $entrada += (int) ($r['usage']['input_tokens'] ?? 0) + (int) ($r['usage']['cache_read_input_tokens'] ?? 0)
                        + (int) ($r['usage']['cache_creation_input_tokens'] ?? 0);
                    $salida += (int) ($r['usage']['output_tokens'] ?? 0);
                    $usd += (float) ($r['usd'] ?? 0);
                }
                if ($r['veredicto'] === null || $r['veredicto'] === []) {
                    $nc++;
                } elseif ($pieza !== 'briefs' && $f['referencia_origen'] !== $m && $r['veredicto'] === $f['referencia']) {
                    $acuerdos++;
                }
            }
            $esRef = $items > 0 && collect($detalle)->every(fn ($f) => ($f['referencia_origen'] ?? null) === $m);
            $comparables = collect($detalle)->filter(fn ($f) => isset($f['resultados'][$m]) && $f['referencia_origen'] !== $m && $f['referencia'] !== null)->count();
            $out[] = [
                'modelo'          => $m,
                'items'           => $items,
                'acuerdos'        => $acuerdos,
                'pct'             => ($pieza === 'briefs' || $esRef || $comparables === 0) ? null : round(100 * $acuerdos / $comparables, 1),
                'no_concluyente'  => $nc,
                'entrada_prom'    => $conUsage ? (int) round($entrada / $conUsage) : 0,
                'salida_prom'     => $conUsage ? (int) round($salida / $conUsage) : 0,
                'mxn_por_llamada' => $conUsage ? round($usd * $tc / $conUsage, 4) : 0.0,
            ];
        }

        return $out;
    }

    private function etiqueta(mixed $v): string
    {
        if (is_array($v)) {
            return count($v) . ' pregunta(s)';
        }

        return $v === null ? '—' : (string) $v;
    }
}
