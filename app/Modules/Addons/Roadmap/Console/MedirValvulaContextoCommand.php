<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * #902 — Mide, por término, cuánto dispara `TRIAJE_C_PLAIN`/`TRIAJE_C_WORD` y cuánto de eso la
 * válvula de contexto (#419/#902 previo) termina aflojando. READ-ONLY: no toca la lista ni el
 * clasificador — genera el dato con el que Irving decide podar/ampliar/dejar (eso es aparte, y
 * requiere su decisión explícita).
 *
 * Por qué esto y no un cálculo a mano: la válvula (`ValvulaContextoService`) entró en vivo el
 * 2026-08-20 16:01 — no hay 30 días de historia CON ella puesta todavía. En vez de fingir una
 * ventana que no existe, el comando mide lo que SÍ hay (desde el primer evento real) y lo dice
 * explícitamente en el reporte, para que la cifra no se lea como más sólida de lo que es. Se
 * re-ejecuta sin costo (no llama IA, solo lee `log`/`comentarios_claude` ya escritos) — la idea es
 * correrlo de nuevo dentro de unas semanas, cuando la ventana de 30 días sí exista completa.
 *
 * Fuentes (ambas ya vive en cada item, nada nuevo que instrumentar):
 *  - `log[].evento === 'valvula_contexto'` — un renglón por CADA vez que el keyword marcó C (la
 *    válvula se invoca siempre que eso pasa, afloje o no). Trae `termino` y `aflojo`.
 *  - `comentarios_claude` — el sello "--- TRIAJE NIVEL NULL (#419) <fecha> ---\nAsignado
 *    nivel_riesgo=X..." que escribe `RevisorService::aplicarTriajeNull()`, con el nivel YA final
 *    (post-válvula). De ahí sale la tasa de C sobre el total triado, no solo sobre lo que disparó.
 */
class MedirValvulaContextoCommand extends Command
{
    protected $signature = 'circuito:medir-valvula
        {--desde= : fecha/hora desde donde medir (Y-m-d H:i:s); default: hace 30 días}
        {--sid= : tu slot de terminal (wt-K), solo para el log}';

    protected $description = '#902 — mide disparos/aflojos por término de la válvula de contexto y la tasa de nivel C resultante; solo lectura.';

    private const SELLO_REGEX = '/--- TRIAJE NIVEL NULL \(#419\) (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) ---\n'
        . 'Asignado nivel_riesgo=([ABC]) \(origen interno\)\. Estado → ([a-z_]+)\./';

    /** Referencia histórica documentada en el docblock de ValvulaContextoService (163 items, 30d, ANTES de la válvula). */
    private const BASELINE_PRE_ARREGLOS = 0.72;

    private const BASELINE_POST_PATRONES = 0.52;

    public function handle(): int
    {
        $desde = $this->option('desde')
            ? Carbon::parse($this->option('desde'))
            : now()->subDays(30);

        $items = RoadmapItem::query()
            ->where(function ($q) {
                $q->whereNotNull('comentarios_claude')
                    ->where('comentarios_claude', 'like', '%TRIAJE NIVEL NULL%');
            })
            ->orWhereNotNull('log')
            ->get(['id', 'comentarios_claude', 'log']);

        // PASADA 1 — sello y eventos de válvula de CADA item, sin filtrar por fecha todavía: se
        // necesita conocer el primer evento real de válvula ANTES de saber la ventana efectiva
        // (si se filtrara ya por $desde aquí, una ventana de 30 días con la válvula viva solo unas
        // horas mezclaría triajes de ANTES de que existiera con los de después — justo lo que este
        // comando existe para no hacer).
        $sellos = [];          // [['item'=>id,'ts'=>Carbon,'nivel'=>'B'|'C'], ...]
        $eventos = [];         // [['item'=>id,'ts'=>Carbon,'termino'=>..,'aflojo'=>bool,'ok'=>bool], ...]
        $primerEvento = null;

        foreach ($items as $item) {
            if (is_string($item->comentarios_claude) && str_contains($item->comentarios_claude, 'TRIAJE NIVEL NULL')) {
                preg_match_all(self::SELLO_REGEX, $item->comentarios_claude, $m, PREG_SET_ORDER);
                foreach ($m as $match) {
                    $sellos[] = ['item' => $item->id, 'ts' => Carbon::parse($match[1]), 'nivel' => $match[2]];
                }
            }

            $log = $item->log;
            if (! is_array($log)) {
                continue; // fila con log mal formado (dato pre-existente) — se salta, no se repara aquí.
            }
            foreach ($log as $entrada) {
                if (! is_array($entrada) || ($entrada['evento'] ?? null) !== 'valvula_contexto') {
                    continue;
                }
                $ts = (string) ($entrada['ts'] ?? '');
                if ($ts === '') {
                    continue;
                }
                $tsCarbon = Carbon::parse($ts);
                if ($primerEvento === null || $tsCarbon->lt($primerEvento)) {
                    $primerEvento = $tsCarbon;
                }
                $eventos[] = [
                    'item'    => $item->id,
                    'ts'      => $tsCarbon,
                    'termino' => (string) ($entrada['termino'] ?? '?'),
                    'aflojo'  => (bool) ($entrada['aflojo'] ?? false),
                    'ok'      => (bool) ($entrada['ok'] ?? false),
                ];
            }
        }

        // Ventana EFECTIVA de "con la válvula activa": lo pedido, pero nunca antes de que la
        // válvula empezara a dejar rastro. Si no hay ningún evento de válvula todavía, se usa lo
        // pedido tal cual (no hay con qué acotar).
        $cobertura = $primerEvento !== null && $primerEvento->gt($desde) ? $primerEvento : $desde;

        $triajes = array_values(array_filter($sellos, fn ($t) => $t['ts']->gte($cobertura)));
        $eventosValvula = array_values(array_filter($eventos, fn ($e) => $e['ts']->gte($cobertura)));

        $totalTriajes = count($triajes);
        $totalC = count(array_filter($triajes, fn ($t) => $t['nivel'] === 'C'));
        $tasaC = $totalTriajes > 0 ? $totalC / $totalTriajes : null;

        $porTermino = [];
        foreach ($eventosValvula as $e) {
            $t = $e['termino'];
            $porTermino[$t]['disparos'] = ($porTermino[$t]['disparos'] ?? 0) + 1;
            if ($e['aflojo']) {
                $porTermino[$t]['aflojos'] = ($porTermino[$t]['aflojos'] ?? 0) + 1;
            }
            if (! $e['ok']) {
                $porTermino[$t]['fallos'] = ($porTermino[$t]['fallos'] ?? 0) + 1;
            }
        }
        ksort($porTermino);

        $md = $this->reporte($desde, $cobertura, $totalTriajes, $totalC, $tasaC, $porTermino, count($eventosValvula));

        $ruta = 'docs/circuito/medicion-valvula-contexto.md';
        file_put_contents(base_path($ruta), $md);

        $this->line($md);
        $this->info("Reporte escrito en {$ruta}.");

        return self::SUCCESS;
    }

    private function reporte(
        Carbon $desde,
        Carbon $cobertura,
        int $totalTriajes,
        int $totalC,
        ?float $tasaC,
        array $porTermino,
        int $totalEventos
    ): string {
        $ahora = now()->toDateTimeString();
        $tasaCTxt = $tasaC === null ? 's/d (sin triajes en la ventana)' : round($tasaC * 100, 1) . '%';
        $ventanaReal = $cobertura->gt($desde)
            ? "⚠️ La válvula solo tiene datos reales desde **{$cobertura->toDateTimeString()}** "
              . "(pedida desde {$desde->toDateTimeString()}) — la ventana con válvula activa es "
              . 'más corta que la solicitada; no hay 30 días completos todavía. Re-correr este '
              . 'comando más adelante para una cifra sobre ventana completa.'
            : "Ventana con datos completa desde {$desde->toDateTimeString()}.";

        $filas = '';
        foreach ($porTermino as $termino => $d) {
            $disparos = $d['disparos'] ?? 0;
            $aflojos = $d['aflojos'] ?? 0;
            $fallos = $d['fallos'] ?? 0;
            $pct = $disparos > 0 ? round($aflojos / $disparos * 100, 1) : 0;
            $muestra = $disparos < 5 ? ' (muestra chica)' : '';
            $sugerencia = match (true) {
                $disparos < 5 => 'insuficiente para opinar',
                $pct >= 85 => 'candidato a acotar — casi siempre es mención',
                $pct <= 15 => 'bien ancho — casi siempre es acción real',
                default => 'mixto — no concluyente',
            };
            $filas .= "| `{$termino}` | {$disparos}{$muestra} | {$aflojos} | {$fallos} | {$pct}% | {$sugerencia} |\n";
        }
        if ($filas === '') {
            $filas = "| _(sin disparos en la ventana)_ | — | — | — | — | — |\n";
        }

        $enPlain = implode(', ', RevisorService::TRIAJE_C_PLAIN);
        $enWord = implode(', ', RevisorService::TRIAJE_C_WORD);

        return <<<MD
# Medición de la lista TRIAJE_C_PLAIN/WORD — con la válvula de contexto activa (#902)

> Generado automáticamente por `php artisan circuito:medir-valvula` el {$ahora}. Read-only: no
> modifica la lista ni el clasificador. Re-ejecutar este comando pisa este archivo con la medición
> más reciente.

## Cobertura de la ventana

{$ventanaReal}

## Tasa de nivel C (antes/después)

| Momento | Tasa de nivel C | Base |
|---|---:|---|
| Antes del clasificador (histórico) | {$this->pct(self::BASELINE_PRE_ARREGLOS)} | 163 items / 30d (doc. en `ValvulaContextoService`) |
| Tras los arreglos de patrones (boilerplate/negación/límite de palabra), SIN válvula | {$this->pct(self::BASELINE_POST_PATRONES)} | 163 items / 30d (misma medición, doc. en `ValvulaContextoService`) |
| **Con la válvula de contexto activa (esta medición)** | **{$tasaCTxt}** | {$totalTriajes} items triados en la ventana, {$totalC} terminaron en C |

## Por término — disparos del keyword vs aflojes de la válvula

La válvula solo se invoca cuando el keyword ya marcó C, así que "disparos" = veces que ese término
encendió la frontera dura; "aflojos" = de esos, cuántos la válvula leyó como MENCIÓN (no acción) y
bajó a B/`pendiente_revision`. Un término que afloja casi siempre es ancho de más; uno que casi
nunca afloja está bien calibrado.

| Término | Disparos | Aflojos | Fallos válvula | % aflojo | Lectura |
|---|---:|---:|---:|---:|---|
{$filas}
Total de invocaciones de la válvula en la ventana: {$totalEventos}.

## Recomendación (dato, no decisión — el podar/ampliar/dejar es de Irving)

- Los términos marcados **"candidato a acotar"** arriba son los que, con muestra suficiente
  (≥5 disparos), la válvula terminó leyendo como mención casi siempre: son los primeros a revisar
  si se decide acotar la lista.
- Los marcados **"bien ancho"** están cumpliendo su función: cuando disparan, casi siempre es
  porque el trabajo SÍ toca el tema.
- Los de **muestra chica** no alcanzan para concluir nada todavía — necesitan más ventana, no un
  cambio ahora.
- **No se poda nada en esta pasada.** Esa decisión es la pregunta `q2` del item #902 y está marcada
  `requiere_irving` en su propio brief — este reporte es el insumo, no el veredicto.

## Listas medidas (referencia)

- `TRIAJE_C_PLAIN` (substring, {$this->cuenta(RevisorService::TRIAJE_C_PLAIN)} términos): {$enPlain}
- `TRIAJE_C_WORD` (palabra completa, {$this->cuenta(RevisorService::TRIAJE_C_WORD)} términos): {$enWord}
MD;
    }

    private function pct(float $frac): string
    {
        return round($frac * 100, 1) . '%';
    }

    private function cuenta(array $lista): int
    {
        return count($lista);
    }
}
