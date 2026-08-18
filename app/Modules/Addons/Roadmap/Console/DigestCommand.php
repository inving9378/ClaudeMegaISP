<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FASE 2A.3 — DIGEST DIARIO del circuito.
 *
 * Reúne las tres señales que esta fase creó y que, sin un sitio donde mirarlas, se perderían:
 *
 *  1. DESPACHOS QUE TOCAN PRODUCCIÓN (§5). El clasificador no frena — decisión de Irving — así que
 *     la única defensa es que sea imposible enterarse tarde.
 *  2. DECISIONES MUDAS de los últimos 7 días. Hay un "antes" medido: 941 mudas históricas, 339
 *     repeticiones desperdiciadas en items aún vivos. Si 2A.3 funcionó, esto tiende a cero solo.
 *     Es la diferencia entre creer que el arreglo sirvió y saberlo.
 *  3. DEPENDENCIA DEL FALLBACK legacy (rótulo en el título que la columna no cubre). Cuando lleve
 *     una semana en 0, el `LIKE` sobre `title` puede retirarse.
 *
 * Guarda además una foto en `settings` para que la Torre la pinte sin recalcular.
 */
class DigestCommand extends Command
{
    protected $signature = 'circuito:digest {--dias=7 : ventana para el contador de decisiones mudas}';

    protected $description = 'Digest diario: despachos que tocan producción, decisiones mudas y dependencia del fallback (2A.3).';

    public const SETTING = 'circuito_digest_snapshot';

    /** Referencia del "antes" (barrido 2026-08-18) — la Torre la pinta junto al número en vivo (#791). */
    public const BASELINE_MUDAS_HISTORICO = 941;

    public const BASELINE_MUDAS_VIVOS = 339;

    public function handle(): int
    {
        $dias  = max(1, (int) $this->option('dias'));
        $desde = Carbon::now()->subDays($dias);
        $desde24h = Carbon::now()->subDay();

        $rows = DB::table('roadmap_items')->whereNotNull('log')->where('log', '!=', '[]')
            ->where('updated_at', '>=', $desde->copy()->subDays(2))   // margen: el log puede ser viejo
            ->get(['id', 'title', 'log']);

        $prod = [];
        $mudas = 0;
        $mudasItems = [];

        foreach ($rows as $r) {
            $log = json_decode($r->log ?? '[]', true);
            if (! is_array($log)) {
                continue;
            }

            $prev = null;
            foreach ($log as $e) {
                if (! is_array($e)) {
                    continue;
                }

                // 1) alerta de producción (últimas 24 h)
                if (($e['decision'] ?? null) === 'alerta_prod'
                    && isset($e['ts']) && Carbon::parse($e['ts'])->gte($desde24h)) {
                    $prod[] = ['id' => $r->id, 'senal' => $e['senal_prod'] ?? '?',
                        'ts' => $e['ts'], 'title' => mb_substr((string) $r->title, 0, 50)];
                }

                // 2) decisión muda: misma decisión, mismo actor, MISMO estado resultante.
                if (! isset($e['decision']) || ($e['decision'] === 'flags' || $e['decision'] === 'alerta_prod')) {
                    $prev = null;   // las entradas de traza no cuentan como decisión
                    continue;
                }
                $k = ($e['por'] ?? '?') . '|' . $e['decision'] . '|' . ($e['estado'] ?? '?');
                if ($prev !== null && $k === $prev
                    && isset($e['ts']) && Carbon::parse($e['ts'])->gte($desde)) {
                    $mudas++;
                    $mudasItems[$r->id] = ($mudasItems[$r->id] ?? 0) + 1;
                }
                $prev = $k;
            }
        }

        $fallback = RoadmapItem::contarFallbackRotulo();

        $this->newLine();
        $this->info('DIGEST DEL CIRCUITO — ' . now()->toDateTimeString());
        $this->newLine();

        $this->line("<options=bold>1. Despachos que tocan PRODUCCIÓN (últimas 24 h): " . count($prod) . '</>');
        if ($prod) {
            $this->table(['item', 'señal', 'cuándo', 'título'],
                array_map(fn ($p) => ['#' . $p['id'], $p['senal'], substr($p['ts'], 0, 16), $p['title']], $prod));
            $this->comment('  No se bloqueó ninguno (política advisory). Revisa que sea lo que esperabas.');
        } else {
            $this->line('  Ninguno.');
        }

        $this->newLine();
        $this->line("<options=bold>2. Decisiones MUDAS últimos {$dias} días: {$mudas}</> (en " . count($mudasItems) . ' item(s))');
        $this->line('   Referencia del "antes" (barrido 2026-08-18): ' . self::BASELINE_MUDAS_HISTORICO
            . ' históricas · ' . self::BASELINE_MUDAS_VIVOS . ' en items aún vivos.');
        if ($mudas === 0) {
            $this->line('   <fg=green>Cero. Ninguna decisión devolvió OK sin mover nada.</>');
        } else {
            arsort($mudasItems);
            foreach (array_slice($mudasItems, 0, 10, true) as $id => $n) {
                $this->line("   #{$id}: {$n}");
            }
            $this->comment('   Si esto no baja, algo quedó mudo: revisa qué freno no está reportándose.');
        }

        $this->newLine();
        $this->line("<options=bold>3. Items que dependen del fallback legacy del título: {$fallback}</>");
        $this->line($fallback === 0
            ? '   <fg=green>Cero. Una semana así y el LIKE sobre title puede retirarse.</>'
            : '   Todavía hay rótulos que la columna no cubre; NO retirar el LIKE.');

        DB::table('settings')->updateOrInsert([
            'key' => self::SETTING,
        ], [
            'value' => json_encode([
                'at'              => now()->toDateTimeString(),
                'prod_24h'        => count($prod),
                'prod_items'      => array_slice(array_column($prod, 'id'), 0, 20),
                'mudas_dias'      => $dias,
                'mudas'           => $mudas,
                'mudas_items'     => count($mudasItems),
                'fallback_rotulo' => $fallback,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->newLine();

        return self::SUCCESS;
    }
}
