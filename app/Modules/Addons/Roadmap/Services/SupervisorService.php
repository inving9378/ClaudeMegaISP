<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SUPERVISOR del circuito ("Jarvis T") — VISTA de su actividad, #334. NO ejecuta desde la UI: es
 * read-only. DERIVA su feed de lo que ya ocurre (sin escribir en las rutas calientes):
 *  - Asignaciones: qué item está en manos de qué worker (roadmap_items.worker_sid + en_progreso).
 *  - Revisor: veredictos autoriza/escala (tabla circuito_revisiones).
 *  - Watchdog: recuperaciones (circuito_watchdog_log, vía WatchdogService).
 *  - Bandeja: lo que escaló a Irving (revisor.escala + brief/priorización).
 * Su "latido" = el de su maquinaria (scheduler + watchdog). Es el jefe del roster (arriba de los 6).
 */
class SupervisorService
{
    public const NOMBRE = 'Jarvis T';

    /**
     * #854 — clave de cache que `RevisarBacklogCommand` escribe mientras el revisor analiza UN
     * item (con TTL corto: si el comando muere a mitad de un item, el dato se auto-limpia solo,
     * sin depender de un `finally`). Vive aquí (no en el comando) porque este servicio es el
     * único lector/consumidor del dato — el comando solo lo produce.
     */
    public const ITEM_EN_CURSO_CACHE_KEY = 'supervisor:item_en_curso';
    public const ITEM_EN_CURSO_TTL_SEG = 90;

    public function __construct(
        private RoadmapCircuitoService $circuito,
        private WatchdogService $watchdog,
    ) {
    }

    /** Estado del supervisor para la Torre: nombre, si está activo, su latido y su feed de actividad. */
    public function estado(int $limite = 18): array
    {
        $sched = $this->circuito->schedulerBeatSecs();
        $wd    = $this->watchdog->estado();
        $wdSecs = $wd['watchdog_secs'] ?? null;

        // "Activo" si su maquinaria late (scheduler o watchdog) y el circuito no está pausado.
        $pausado = $this->circuito->isPaused();
        $vivo = ! $pausado && (
            ($sched !== null && $sched < config('circuito.watchdog.scheduler_stale_seg', 180))
            || ($wdSecs !== null && $wdSecs < config('circuito.watchdog.scheduler_stale_seg', 180))
        );

        $latidoSecs = collect([$sched, $wdSecs])->filter(fn ($v) => $v !== null)->min();

        $colis = $this->colisiones();

        return [
            'nombre'      => self::NOMBRE,
            'avatar_url'  => $this->circuito->avatarUrlWorker('supervisor'),   // #854
            'activo'      => $vivo,
            'pausado'    => $pausado,
            'latido_secs' => $latidoSecs,
            'scheduler_vivo' => $sched !== null && $sched < config('circuito.watchdog.scheduler_stale_seg', 180),
            'watchdog_vivo'  => (bool) ($wd['watchdog_vivo'] ?? false),
            'asignados'   => $this->asignadosAhora(),
            'protocolo'   => $this->protocolo(),        // reglas que arbitra (identidad del jefe)
            'colisiones'  => $colis['modulos_dobles'],  // 2 agentes en el mismo módulo (rule 6)
            'pingpong'    => $colis['pingpong'],         // uno arregla / otro revierte (rule 7 → a Irving)
            'actividad'   => $this->actividad($limite, $colis),
            // #475: "escritorio" del supervisor — lo recién resuelto + la cola lista para el próximo terminal.
            // #934: hasta 10 (antes 6) + total real, para el pie "+N más" (nunca truncar en silencio).
            'recien_resueltos'           => $this->recienResueltos(),
            'recien_resueltos_total'     => $this->recienResueltosTotal(),
            'listos_para_terminal'       => $this->listosParaTerminal(),
            'listos_para_terminal_total' => $this->listosParaTerminalTotal(),
            // #854: qué item está analizando AHORA (o por qué no hay ninguno en curso).
            'item_en_curso'        => $this->itemEnCurso(),
            // #9990977 (Fase 5a de #9990895): ociosidad real de las terminales + elegibles reales,
            // para poder ver la "alerta contradictoria" (terminal libre + trabajo elegible a la vez).
            'ociosas_minutos_hoy'        => $this->ociosasMinutosHoy(),
            'terminales_ociosas_ahora'   => $terminalesOciosasAhora = $this->terminalesOciosasAhora(),
            'elegibles_reales'           => $elegiblesReales = $this->elegiblesReales(),
            'alerta_contradictoria'      => count($terminalesOciosasAhora) > 0 && $elegiblesReales > 0,
        ];
    }

    /**
     * #854 — item que el revisor está analizando en este instante. Fuente: la cache que escribe
     * `RevisarBacklogCommand` en cada iteración de su bucle (sin tabla ni columna nueva: el "item
     * en curso" es un estado de segundos, no un dato que valga la pena persistir).
     *
     *   estado='revisando'  → hay un item bajo análisis ahora mismo (id + title).
     *   estado='sin_item'   → nadie lo está analizando, pero hay cola pendiente (entre pasadas del cron).
     *   estado='cola_vacia' → no hay nada pendiente de revisar.
     */
    public function itemEnCurso(): array
    {
        $actual = Cache::get(self::ITEM_EN_CURSO_CACHE_KEY);
        if (is_array($actual) && ! empty($actual['id'])) {
            return ['estado' => 'revisando', 'id' => (int) $actual['id'], 'title' => $actual['title'] ?? null];
        }

        // Misma forma de la cola que revisa `RevisarBacklogCommand` (rama B + rama de triaje NULL),
        // solo para saber si "no hay nada en curso" es porque no hay cola o porque está entre pasadas.
        $hayCola = RoadmapItem::whereNull('archivado_at')
            ->where('status', 'pending')
            ->where('estado_aprobacion', 'pendiente_revision')
            ->where(function ($w) {
                $w->where('nivel_riesgo', 'B')->orWhereNull('nivel_riesgo');
            })
            ->tomablePorCircuito()
            ->exists();

        return ['estado' => $hayCola ? 'sin_item' : 'cola_vacia', 'id' => null, 'title' => null];
    }

    /**
     * #9990977 — parsea (con cache 30s, para no reparsear en cada poll de 3s de la Torre) las
     * líneas "wt-K ociosa: ..." del log de HOY del canal `circuito_despacho`
     * (`persistirOciosidad()` en SchedulerCommand.php:370 escribe 1 línea por slot ocioso en CADA
     * ciclo del cron, que corre 1 vez por minuto). Devuelve las entradas crudas (sid + timestamp
     * de la línea, tomado del prefijo `[Y-m-d H:i:s]` que escribe Monolog) para que
     * {@see ociosasMinutosHoy()} y {@see terminalesOciosasAhora()} deriven de la MISMA lectura.
     * Archivo inexistente (aún no hubo ninguna ociosidad hoy) → [] sin excepción.
     *
     * @return array<int,array{sid:string,ts:\Illuminate\Support\Carbon}>
     */
    private function ociosidadHoyRaw(): array
    {
        return Cache::remember('supervisor:ociosidad_hoy', 30, function () {
            $path = storage_path('logs/circuito-despacho-' . now()->format('Y-m-d') . '.log');
            if (! is_file($path)) {
                return [];
            }

            $entradas = [];
            foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
                if (! preg_match('/^\[(?<fecha>[\d-]+ [\d:]+)].*\bwt-(?<slot>\d+) ociosa:/', $linea, $m)) {
                    continue;
                }
                try {
                    $entradas[] = ['sid' => "wt-{$m['slot']}", 'ts' => Carbon::parse($m['fecha'])];
                } catch (\Throwable $e) {
                    continue; // línea con fecha corrupta: se ignora, no tumba el resto del parseo.
                }
            }

            return $entradas;
        });
    }

    /** #9990977.1 — minutos ociosos de HOY por terminal (1 línea del log = 1 minuto = 1 ciclo del cron). */
    public function ociosasMinutosHoy(): array
    {
        $conteo = [];
        foreach ($this->ociosidadHoyRaw() as $e) {
            $conteo[$e['sid']] = ($conteo[$e['sid']] ?? 0) + 1;
        }

        return $conteo;
    }

    /**
     * #9990977.2 — sids que llevan >= `config('circuito.torre.ociosa_umbral_min')` minutos
     * CONSECUTIVOS ociosos AHORA MISMO. Detección de consecutividad: para cada sid, se toman los
     * minutos distintos con registro en el log de hoy; si el más reciente es de hace <= 1 minuto
     * (tolera el desfase del cron) y los `$umbral` minutos anteriores a ese, uno por uno, también
     * tienen registro (sin huecos), el sid cuenta como ocioso ahora. Se excluye cualquier sid que
     * ya aparezca en {@see asignadosAhora()} (la BD manda sobre el log: pudo reclamar un item en
     * el mismo minuto en que el log todavía trae su última línea ociosa).
     *
     * @return array<int,string>
     */
    public function terminalesOciosasAhora(): array
    {
        $umbral = (int) config('circuito.torre.ociosa_umbral_min', 3);
        $ocupados = collect($this->asignadosAhora())->pluck('sid')->all();

        $resultado = [];
        foreach (collect($this->ociosidadHoyRaw())->groupBy('sid') as $sid => $entradas) {
            if (in_array($sid, $ocupados, true)) {
                continue;
            }

            $minutos = $entradas->map(fn ($e) => $e['ts']->format('Y-m-d H:i'))->unique()->sort()->values();
            if ($minutos->count() < $umbral) {
                continue;
            }

            $ultimo = Carbon::createFromFormat('Y-m-d H:i', $minutos->last());
            if ($ultimo->diffInMinutes(now()) > 1) {
                continue; // el último registro es viejo: ya no está ocioso "ahora mismo".
            }

            $consecutivos = true;
            for ($i = 0; $i < $umbral; $i++) {
                if (! $minutos->contains($ultimo->copy()->subMinutes($i)->format('Y-m-d H:i'))) {
                    $consecutivos = false;
                    break;
                }
            }

            if ($consecutivos) {
                $resultado[] = $sid;
            }
        }

        sort($resultado);

        return $resultado;
    }

    /** #9990977.3 — cuántos items puede tomar el pool AHORA (mismo predicado que gobierna el despacho real). */
    public function elegiblesReales(): int
    {
        return RoadmapItem::despachable()->count();
    }

    /** #475: últimos items COMPLETADOS — alimenta la lista "Recién resueltos" del escritorio. */
    public function recienResueltos(int $limite = 10): array
    {
        return RoadmapItem::where('estado_aprobacion', 'completado')
            ->whereNull('archivado_at')
            ->orderByDesc('updated_at')
            ->limit($limite)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'title' => $r->title,
                'at'    => optional($r->updated_at)->toIso8601String(),
            ])->values()->all();
    }

    /** #934: total real detrás de {@see recienResueltos()}, para el pie "+N más" (nunca cortar en silencio). */
    public function recienResueltosTotal(): int
    {
        return RoadmapItem::where('estado_aprobacion', 'completado')
            ->whereNull('archivado_at')
            ->count();
    }

    /**
     * #475: items ya triados/ejecutables (estación "listo" — ver RoadmapItem::getEstacionAttribute)
     * esperando que un worker los reclame. Alimenta "Listos para terminal" del escritorio.
     */
    public function listosParaTerminal(int $limite = 10): array
    {
        // FASE 2A.5 — se quitaron dos `not like` del rótulo que estaban copiados aquí a mano:
        // `autoEjecutable()` ya pasa por `elegibleParaPool()` → `RoadmapItem::sqlElegibleParaPool()`,
        // que además honra `origen_bloqueo='humano'` (la copia de aquí NO lo hacía) y se retira sola
        // el día que el fallback legacy del rótulo se elimine. Candado: PoolGuardCoherenceTest.
        $q = RoadmapItem::autoEjecutable()
            ->whereNull('archivado_at')
            ->whereNull('branch')
            ->whereNull('motivo_espera')
            ->ordered();

        $columnas = ['id', 'title', 'nivel_riesgo', 'reap_count', 'veces_timeouteo', 'reanudaciones_timeout'];

        // #9990924 (Fase 3b) — heurística de TEXTO LIBRE (prompt/description) para bloqueos SIN
        // rótulo formal, gateada por feature flag (SUPERVISOR_HIDE_BLOCKED, default ON). Filtro
        // POST-fetch (regex no expresable en SQL): se amplía el tramo antes de filtrar para no
        // sub-reportar items elegibles que hubieran caído en el corte original (mismo patrón que
        // el filtro de dependencias cerradas).
        if (config('circuito.supervisor.hide_blocked_heuristic', true)) {
            $filas = $q->limit($limite * 3)
                ->get([...$columnas, 'prompt', 'description'])
                ->reject(fn ($r) => $r->tieneBloqueoDeclaradoEnTexto())
                ->values();
        } else {
            $filas = $q->limit($limite)->get($columnas);
        }

        return $filas->take($limite)->map(fn ($r) => [
            'id'                     => (int) $r->id,
            'title'                  => $r->title,
            'nivel'                  => $r->nivel_riesgo,
            // #9990925 — "vueltas quemadas": contadores YA existentes (reap_count/veces_timeouteo),
            // sin columna nueva. La Torre los pinta como badge solo cuando alguno es > 0.
            'reap_count'             => (int) $r->reap_count,
            'veces_timeouteo'        => (int) $r->veces_timeouteo,
            'reanudaciones_timeout'  => (int) $r->reanudaciones_timeout,
        ])->values()->all();
    }

    /** #934: total real detrás de {@see listosParaTerminal()}, para el pie "+N más" (nunca cortar en silencio). */
    public function listosParaTerminalTotal(): int
    {
        $q = RoadmapItem::autoEjecutable()
            ->whereNull('archivado_at')
            ->whereNull('branch')
            ->whereNull('motivo_espera');

        // #9990924 — mismo filtro de texto libre que listosParaTerminal(), para que el "+N más"
        // del pie no cuente items que la lista de arriba ya oculta por bloqueo heurístico.
        if (config('circuito.supervisor.hide_blocked_heuristic', true)) {
            return $q->get(['id', 'prompt', 'description'])
                ->reject(fn ($r) => $r->tieneBloqueoDeclaradoEnTexto())
                ->count();
        }

        return $q->count();
    }

    /** El PROTOCOLO DE COORDINACIÓN que Jarvis T arbitra (para la identidad/UI del supervisor). */
    public function protocolo(): array
    {
        return [
            'Un item = un dueño (reclamo atómico #341); lo reclamado no se toca.',
            'Cada agente solo en su worktree; nunca el principal ni el de otro.',
            'No deshacer/rehacer lo de otro; reparar solo ante regresión real verificada.',
            'Otro agente en el área → dejarlo; tomar otro item o esperar.',
            'Avisar START/END por item; al cerrar se libera el área.',
            'Merges en serie (merge-lock); footprints disjuntos, el que solapa espera.',
            'Anti-ping-pong: si dos pelean por lo mismo, el supervisor para y escala a Irving.',
        ];
    }

    /**
     * Detección BARATA (solo DB, sin git — corre en el poll) de colisiones:
     *  - modulos_dobles: >1 item en_progreso en el MISMO módulo (viola footprints disjuntos / #341).
     *  - pingpong: un módulo con un REVERT reciente + otro item tocándolo → posible pelea → a Irving.
     */
    public function colisiones(): array
    {
        // (a) Módulos con 2+ items en vuelo a la vez.
        $dobles = RoadmapItem::whereNotNull('modulo')->where('modulo', '!=', '')
            ->where('estado_aprobacion', 'en_progreso')
            ->selectRaw('modulo, count(*) n, group_concat(id) ids')
            ->groupBy('modulo')->havingRaw('count(*) > 1')->get()
            ->map(fn ($r) => ['modulo' => $r->modulo, 'n' => (int) $r->n, 'ids' => array_map('intval', explode(',', (string) $r->ids))])
            ->values()->all();

        // (b) Reverts REALES recientes por módulo: el evento estructurado 'revert_merge' del log
        //     (integracionRevert). NO la palabra "reversible"/"revertir" de un brief (falso positivo).
        $desde = now()->subHours(3);
        $reverts = RoadmapItem::whereNotNull('modulo')->where('modulo', '!=', '')
            ->where('updated_at', '>=', $desde)
            ->where('log', 'like', '%revert_merge%')
            ->get(['id', 'modulo']);

        $pingpong = [];
        foreach ($reverts->groupBy('modulo') as $modulo => $rows) {
            // ¿hay OTRO item tocando el mismo módulo (en vuelo o cambiado en la ventana), distinto de los revertidos?
            $revIds = $rows->pluck('id')->all();
            $otro = RoadmapItem::where('modulo', $modulo)->whereNotIn('id', $revIds)
                ->where('updated_at', '>=', $desde)
                ->whereIn('estado_aprobacion', ['en_progreso', 'aprobado_claude', 'aprobado_revisor', 'completado'])
                ->exists();
            if ($otro) {
                $pingpong[] = ['modulo' => $modulo, 'revert_ids' => array_map('intval', $revIds)];
            }
        }

        return ['modulos_dobles' => $dobles, 'pingpong' => $pingpong];
    }

    /** Quién trabaja qué AHORA: [{sid, nombre, item}] de los en_progreso con firma de worker. */
    private function asignadosAhora(): array
    {
        $rows = RoadmapItem::whereNotNull('worker_sid')
            ->where('estado_aprobacion', 'en_progreso')
            ->orderBy('worker_sid')
            ->get(['id', 'title', 'worker_sid']);

        return $rows->map(fn ($r) => [
            'sid'    => $r->worker_sid,
            'nombre' => $this->circuito->nombreWorker($r->worker_sid),
            'item'   => ['id' => (int) $r->id, 'title' => $r->title],
        ])->values()->all();
    }

    /** Feed unificado (colisiones/ping-pong + asignaciones + revisor + watchdog), orden cronológico desc. */
    private function actividad(int $limite, ?array $colis = null): array
    {
        $ev = [];

        // (0) ARBITRAJE: ping-pong (a Irving) y colisiones de módulo — arriba del feed.
        $colis ??= $this->colisiones();
        foreach ($colis['pingpong'] as $pp) {
            $ev[] = ['ts' => null, 'orden' => time() + 2, 'tipo' => 'pingpong',
                'texto' => "⚠ PING-PONG en «{$pp['modulo']}» (revert de #" . implode(',#', $pp['revert_ids']) . ") → PARO y escalo a Irving"];
        }
        foreach ($colis['modulos_dobles'] as $md) {
            $ev[] = ['ts' => null, 'orden' => time() + 1, 'tipo' => 'colision',
                'texto' => "⚠ Colisión: {$md['n']} agentes en «{$md['modulo']}» (#" . implode(',#', $md['ids']) . ") — footprints deben ser disjuntos"];
        }

        // (1) Asignaciones activas (qué item mandó a qué worker).
        foreach ($this->asignadosAhora() as $a) {
            $ev[] = [
                'ts'    => null,   // "ahora"; se ordena arriba
                'orden' => time(),
                'tipo'  => 'asigna',
                'texto' => "Asignó #{$a['item']['id']} a {$a['nombre']} · " . mb_strimwidth($a['item']['title'], 0, 46, '…'),
            ];
        }

        // (2) Veredictos del revisor (autoriza / escala a Irving).
        $rev = DB::table('circuito_revisiones')->orderByDesc('id')->limit($limite)->get();
        $titulos = $this->titulos($rev->pluck('roadmap_item_id')->all());
        foreach ($rev as $r) {
            $id = (int) $r->roadmap_item_id;
            $t  = $titulos[$id] ?? null;
            $at = $r->created_at ? Carbon::parse($r->created_at) : null;
            if (($r->veredicto ?? '') === 'autoriza') {
                $tipo = 'autoriza';
                $texto = "Revisor autorizó #{$id} → al pool" . ($t ? ' · ' . mb_strimwidth($t, 0, 42, '…') : '');
            } else {
                $tipo = 'escala';
                $cat = $r->categoria_escalada ? " ({$r->categoria_escalada})" : '';
                $texto = "Revisor escaló #{$id} a tu bandeja{$cat}" . ($t ? ' · ' . mb_strimwidth($t, 0, 38, '…') : '');
            }
            $ev[] = ['ts' => $at?->toIso8601String(), 'orden' => $at ? $at->timestamp : 0, 'tipo' => $tipo, 'texto' => $texto];
        }

        // (3) Acciones del watchdog (revivió scheduler/worker, escaladas).
        foreach ($this->watchdog->bitacora($limite) as $w) {
            $at = ! empty($w['at']) ? Carbon::parse($w['at']) : null;
            $ev[] = [
                'ts'    => $at?->toIso8601String(),
                'orden' => $at ? $at->timestamp : 0,
                'tipo'  => 'watchdog',
                'texto' => 'Watchdog: ' . mb_strimwidth((string) ($w['detalle'] ?? $w['tipo'] ?? ''), 0, 80, '…'),
            ];
        }

        usort($ev, fn ($a, $b) => $b['orden'] <=> $a['orden']);

        return array_slice($ev, 0, $limite);
    }

    /** Mapa id=>title para un set de ids. */
    private function titulos(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }

        return RoadmapItem::whereIn('id', $ids)->pluck('title', 'id')->toArray();
    }
}
