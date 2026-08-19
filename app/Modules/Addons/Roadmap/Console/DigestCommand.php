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

    /** Desde cuándo el fallback legacy del rótulo lleva 0 dependientes (2A.6). */
    public const SETTING_FALLBACK_CERO = 'circuito_fallback_rotulo_cero_desde';

    /** Días en 0 tras los cuales retirar el `LIKE` sobre `title` es seguro. */
    public const FALLBACK_DIAS_PARA_RETIRAR = 7;

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

        $liveness = $this->procesosProgramados();

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

        $escalaciones = $this->escalacionesPorMotivo($dias);

        $this->newLine();
        $this->line("<options=bold>3. Items que dependen del fallback legacy del título: {$fallback}</>");
        $this->rachaDelFallback($fallback);

        $frenos     = $this->resurfacearFrenos();
        $superficie = $this->superficieDeclarada();

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
                'sin_modelo'      => $escalaciones['sin_juicio'],
                'sin_modelo_dias' => $dias,
                'procesos_mudos'  => array_values(array_map(fn ($p) => $p['comando'],
                    array_filter($liveness, fn ($p) => $p['vencido']))),
                'superficie_pct'  => $superficie['pct'],
                'superficie'      => $superficie,
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
     * FASE 2B — SUPERFICIE DECLARADA: la métrica de convergencia del generador.
     *
     * Cuánto del sistema tiene contra qué medirse. **Mientras suba, el generador tiene trabajo.**
     * Cuando se acerque a su techo, el detector semántico sobre `screens[].steps/actions` ya tendrá
     * material y ahí sí valdrá la pena el juicio del modelo.
     *
     * Va al digest porque es el único número que dice si el ciclo está convergiendo o girando en
     * vacío — y porque una métrica que sólo se mira cuando alguien se acuerda no es una métrica.
     */
    private function superficieDeclarada(): array
    {
        $s = app(\App\Modules\Addons\Roadmap\Services\AuditorService::class)->superficieDeclarada();

        $prev = DB::table('settings')->where('key', self::SETTING)->value('value');
        $prev = $prev ? (json_decode($prev, true)['superficie_pct'] ?? null) : null;
        $delta = ($prev !== null && $prev != $s['pct'])
            ? sprintf(' (%+.1f pp desde el digest anterior)', $s['pct'] - $prev)
            : '';

        $this->newLine();
        $this->line("<options=bold>5. Superficie declarada: {$s['pct']} %</>{$delta}");
        $this->line("   {$s['declarados']} endpoints declarados de {$s['rutas_modulo']} rutas atribuibles a un módulo");
        $this->line("   {$s['modulos_con_spec']}/{$s['modulos']} módulos declaran algo en su `module.json`");
        $this->line("   ({$s['rutas_sin_modulo']} rutas de controllers legacy fuera de app/Modules NO cuentan:");
        $this->line('    no pertenecen a ningún manifiesto y no pueden declararse por esta vía — es el techo)');
        $this->comment('   Mientras este número suba, el generador tiene trabajo. Detalle: circuito:inventario-spec');

        return $s;
    }

    /**
     * FASE 2A.6 — LA RACHA SE MIDE, NO SE RECUERDA.
     *
     * El plan era "anota la fecha; si a los 7 días sigue en 0, retira el `LIKE` sobre `title`". Una
     * fecha anotada en un reporte es exactamente la clase de cosa que nadie vuelve a mirar — y un
     * fallback que ya no usa nadie es sólo una SEGUNDA DEFINICIÓN esperando a derivar, que es la
     * enfermedad que toda la fase 2A vino a cerrar. Así que el digest lleva la cuenta él: sella el
     * día que llegó a 0, la reinicia si vuelve a subir, y avisa solo cuando ya es seguro retirarlo.
     */
    private function rachaDelFallback(int $fallback): void
    {
        if ($fallback > 0) {
            DB::table('settings')->where('key', self::SETTING_FALLBACK_CERO)->delete();
            $this->line('   Todavía hay rótulos que la columna no cubre; NO retirar el LIKE.');
            $this->line('   (la racha de días en 0 se reinicia)');

            return;
        }

        $desde = DB::table('settings')->where('key', self::SETTING_FALLBACK_CERO)->value('value');
        if (! $desde) {
            $desde = now()->toDateTimeString();
            DB::table('settings')->updateOrInsert(['key' => self::SETTING_FALLBACK_CERO], ['value' => $desde]);
        }

        $dias = (int) Carbon::parse($desde)->diffInDays(now());
        $this->line("   <fg=green>Cero desde el " . substr($desde, 0, 10) . " ({$dias} día(s) seguidos).</>");

        if ($dias >= self::FALLBACK_DIAS_PARA_RETIRAR) {
            $this->line('   <fg=green;options=bold>YA ES SEGURO retirar el `LIKE` sobre `title` de '
                . 'RoadmapItem::sqlSinFrenoHumano()/sqlConFrenoHumano().</>');
            $this->line('   Un fallback que nadie usa es una segunda definición esperando a derivar.');
        } else {
            $this->line('   Faltan ' . (self::FALLBACK_DIAS_PARA_RETIRAR - $dias)
                . ' día(s) en 0 para poder retirar el `LIKE` sobre `title`.');
        }
    }

    /**
     * FASE 2A.7 (#808) — LO PRIMERO QUE SE LEE: qué proceso programado no está corriendo.
     *
     * Una regla implementada y no agendada es un no-op invisible. 2A.4 dejó el caducado del
     * clasificador escrito, probado y fail-closed, y sin su línea de cron no caduca nada — sin este
     * bloque eso se descubre en dos meses. Distingue las dos causas, porque piden cosas distintas:
     * **no agendado** (falta la línea de cron) vs **agendado pero sin latir** (corre y falla).
     *
     * @return array<int,array> el estado crudo, para la foto en `settings`
     */
    private function procesosProgramados(): array
    {
        $procesos = app(\App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService::class)->latidos();
        $malos    = array_values(array_filter($procesos, fn ($p) => $p['vencido']));

        if (! $malos) {
            $this->line('<options=bold>0. Procesos programados: ' . count($procesos) . '/' . count($procesos)
                . ' latiendo</> <fg=green>✔</>');
            $this->newLine();

            return $procesos;
        }

        $this->line('<options=bold;fg=red>0. ⚠ ' . count($malos) . ' proceso(s) programado(s) NO están corriendo</>');
        foreach ($malos as $p) {
            $causa = $p['agendado'] === false
                ? '<fg=red>NO ESTÁ AGENDADO en el crontab</>'
                : ($p['nunca']
                    ? 'agendado, pero NUNCA ha latido (¿falla al arrancar, o no ha tocado su horario desde que se instrumentó?)'
                    : "agendado, pero su último latido fue hace {$p['horas']} h (tope {$p['max_horas']} h)");
            $this->line("   <fg=yellow>{$p['comando']}</> — {$causa}");
            if ($p['si_no_corre'] !== '') {
                $this->line("      Se pierde: {$p['si_no_corre']}");
            }
            // #808 — si no está agendado y hay línea sugerida, se imprime lista para copiar/pegar
            // (`crontab -e` del usuario meganet): ningún ejecutor on-box puede escribirla por sí solo.
            if ($p['agendado'] === false && ($p['linea_cron'] ?? '') !== '') {
                $this->line("      Pegar en `crontab -e`: <fg=cyan>{$p['linea_cron']}</>");
            }
        }
        $this->newLine();

        return $procesos;
    }

    /**
     * FASE 2A.7 (#807) — POR QUÉ escaló el revisor: juicio, o ausencia de modelo.
     *
     * Es el hallazgo más incómodo de la fase: con la IA caída el circuito NO se cae — escala todo,
     * el autopilot deja de calificar, y el tablero cuenta una historia coherente ("está siendo
     * prudente") que nada contradice. `escala:sin_modelo > 0` es lo único que no se puede confundir
     * con prudencia. No se cambió la falla-segura: sólo dejó de ser anónima.
     *
     * @return array{autoriza:int,juicio:int,sin_juicio:int,por_categoria:array<string,int>}
     */
    private function escalacionesPorMotivo(int $dias): array
    {
        $desde = Carbon::now()->subDays($dias);
        $sinJuicio = \App\Modules\Addons\Roadmap\Services\RevisorService::CATEGORIAS_SIN_JUICIO;

        $filas = DB::table('circuito_revisiones')
            ->where('created_at', '>=', $desde)
            ->selectRaw('veredicto, categoria_escalada, COUNT(*) as n')
            ->groupBy('veredicto', 'categoria_escalada')
            ->get();

        $r = ['autoriza' => 0, 'juicio' => 0, 'sin_juicio' => 0, 'por_categoria' => []];
        foreach ($filas as $f) {
            $n = (int) $f->n;
            if ($f->veredicto === 'autoriza') {
                $r['autoriza'] += $n;
                continue;
            }
            $cat = (string) ($f->categoria_escalada ?? 'sin_categoria');
            $r['por_categoria'][$cat] = ($r['por_categoria'][$cat] ?? 0) + $n;
            if (in_array($cat, $sinJuicio, true)) {
                $r['sin_juicio'] += $n;
            } else {
                $r['juicio'] += $n;
            }
        }

        $this->newLine();
        $this->line("<options=bold>2-bis. Veredictos del revisor (últimos {$dias} días)</>");
        $this->line("   autoriza: {$r['autoriza']}   ·   escala POR JUICIO: {$r['juicio']}   ·   "
            . ($r['sin_juicio'] > 0
                ? "<fg=red;options=bold>escala SIN MODELO: {$r['sin_juicio']}</>"
                : "<fg=green>escala sin modelo: 0</>"));

        if ($r['sin_juicio'] > 0) {
            $this->line('   <fg=red>⚠ Eso NO es prudencia: el revisor no pudo emitir veredicto.</> Revisa la key de');
            $this->line('   Anthropic (Hub `api_integrations` → `env` → `marketing_settings`) y la red ANTES de leer');
            $this->line('   la bandeja llena como cautela del circuito.');
            foreach ($r['por_categoria'] as $cat => $n) {
                if (in_array($cat, $sinJuicio, true)) {
                    $this->line("      · {$cat}: {$n}");
                }
            }
        }

        return $r;
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
