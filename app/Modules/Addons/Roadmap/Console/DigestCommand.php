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
    protected $signature = 'circuito:digest
                            {--dias=7 : ventana para el contador de decisiones mudas}
                            {--frenos : fuerza el recordatorio de frenos humanos aunque no toque hoy}';

    protected $description = 'Digest diario: despachos que tocan producción, decisiones mudas y dependencia del fallback (2A.3).';

    public const SETTING = 'circuito_digest_snapshot';

    /** Última vez que se resurfacearon los frenos humanos (2A.4): evita repetirlos a diario. */
    public const SETTING_FRENOS = 'circuito_digest_frenos_at';

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

            // 1) alerta de producción (últimas 24 h)
            foreach ($log as $e) {
                if (is_array($e) && ($e['decision'] ?? null) === 'alerta_prod'
                    && isset($e['ts']) && Carbon::parse($e['ts'])->gte($desde24h)) {
                    $prod[] = ['id' => $r->id, 'senal' => $e['senal_prod'] ?? '?',
                        'ts' => $e['ts'], 'title' => mb_substr((string) $r->title, 0, 50)];
                }
            }

            // 2) decisiones mudas de la ventana. La REGLA vive en `RoadmapItem::contarMudasEnLog`
            //    (2A.4): el digest y el re-triage cuentan lo mismo o el número deja de significar
            //    nada — es la misma lección de la deriva del predicado de despacho.
            if ($n = RoadmapItem::contarMudasEnLog($log, $desde)) {
                $mudas += $n;
                $mudasItems[$r->id] = $n;
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

        $frenos = $this->resurfacearFrenos();

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
                'frenos_humanos'  => count(\App\Modules\Addons\Roadmap\Console\RetriageFrenosCommand::frenosHumanos()),
                'frenos_top'      => array_slice(array_map(
                    fn ($f) => ['id' => $f['id'], 'dias' => $f['dias'], 'mudas' => $f['mudas'], 'rotulo' => $f['rotulo']],
                    $frenos), 0, 5),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * FASE 2A.4 §4 — «FRENOS QUE PUSISTE TÚ».
     *
     * El freno humano NO caduca: es una decisión de Irving y el sistema no la revoca por
     * antigüedad. Lo que sí hace es devolvérsela cada `retriage.resurface_dias` para que decida de
     * nuevo con la cabeza fresca. Los 33 no son items bloqueados por error — son decisiones que se
     * le olvidó haber tomado; un caducado automático se las quitaría a la mala.
     *
     * Orden: primero los que acumulan más APROBACIONES MUDAS. Un freno tuyo sobre un item que tú
     * sigues aprobando es la señal más limpia de un desacuerdo entre lo que decidiste y lo que
     * quieres — #65 lleva 48 aprobaciones contra su propio `[BLOCKED-NEGOCIO]`.
     *
     * No se repite a diario a propósito: repetido cada día se vuelve invisible, que es justo el
     * problema que viene a resolver.
     *
     * @return array<int,array> los frenos ya ordenados (para la foto en `settings`)
     */
    private function resurfacearFrenos(): array
    {
        $frenos = \App\Modules\Addons\Roadmap\Console\RetriageFrenosCommand::frenosHumanos();
        if (! $frenos) {
            return [];
        }

        $cada    = max(1, (int) config('circuito.retriage.resurface_dias', 7));
        $ultimo  = DB::table('settings')->where('key', self::SETTING_FRENOS)->value('value');
        $toca    = $this->option('frenos') || ! $ultimo || Carbon::parse($ultimo)->addDays($cada)->isPast();

        $this->newLine();
        $this->line('<options=bold>4. Frenos que pusiste TÚ y siguen en pie: ' . count($frenos) . '</>');

        if (! $toca) {
            $proxima = Carbon::parse($ultimo)->addDays($cada);
            $this->line('   (recordatorio cada ' . $cada . ' días — el próximo toca el '
                . $proxima->toDateString() . '; `--frenos` lo fuerza)');

            return $frenos;
        }

        $this->line('   <fg=yellow>Ninguno caduca solo: son decisiones tuyas. Esto es un recordatorio, no una revocación.</>');
        $this->table(
            ['item', 'desde', 'días', 'aprob. mudas', 'decía', 'título'],
            array_map(fn ($f) => [
                '#' . $f['id'],
                $f['desde'] ?? '?',
                $f['dias'] . ($f['exacto'] ? '' : '+'),
                $f['mudas'] ?: '—',
                $f['rotulo'],
                mb_strimwidth($f['title'], 0, 40, '…'),
            ], array_slice($frenos, 0, (int) config('circuito.retriage.resurface_top', 12)))
        );
        if (count($frenos) > (int) config('circuito.retriage.resurface_top', 12)) {
            $this->line('   … y ' . (count($frenos) - (int) config('circuito.retriage.resurface_top', 12))
                . ' más (lista completa: `php artisan circuito:re-triage`).');
        }
        $this->comment('   Arriba van los que acumulan más aprobaciones mudas: ahí decidiste una cosa y quieres otra.');
        $this->comment('   Para quitar uno: la Torre → «Quitar el freno y aprobar». El sistema no lo hará por ti.');

        DB::table('settings')->updateOrInsert(['key' => self::SETTING_FRENOS],
            ['value' => now()->toDateTimeString()]);

        return $frenos;
    }
}
