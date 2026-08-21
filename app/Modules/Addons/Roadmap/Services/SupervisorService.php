<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SUPERVISOR del circuito ("Thomas T") — VISTA de su actividad, #334. NO ejecuta desde la UI: es
 * read-only. DERIVA su feed de lo que ya ocurre (sin escribir en las rutas calientes):
 *  - Asignaciones: qué item está en manos de qué worker (roadmap_items.worker_sid + en_progreso).
 *  - Revisor: veredictos autoriza/escala (tabla circuito_revisiones).
 *  - Watchdog: recuperaciones (circuito_watchdog_log, vía WatchdogService).
 *  - Bandeja: lo que escaló a Irving (revisor.escala + brief/priorización).
 * Su "latido" = el de su maquinaria (scheduler + watchdog). Es el jefe del roster (arriba de los 6).
 */
class SupervisorService
{
    public const NOMBRE = 'Thomas T';

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
            ($sched !== null && $sched < WatchdogService::SCHEDULER_STALE_SEG)
            || ($wdSecs !== null && $wdSecs < WatchdogService::SCHEDULER_STALE_SEG)
        );

        $latidoSecs = collect([$sched, $wdSecs])->filter(fn ($v) => $v !== null)->min();

        $colis = $this->colisiones();

        return [
            'nombre'      => self::NOMBRE,
            'avatar_url'  => $this->circuito->avatarUrlWorker('supervisor'),   // #854
            'activo'      => $vivo,
            'pausado'    => $pausado,
            'latido_secs' => $latidoSecs,
            'scheduler_vivo' => $sched !== null && $sched < WatchdogService::SCHEDULER_STALE_SEG,
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
        return RoadmapItem::autoEjecutable()
            ->whereNull('archivado_at')
            ->whereNull('branch')
            ->ordered()
            ->limit($limite)
            ->get(['id', 'title', 'nivel_riesgo'])
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'title' => $r->title,
                'nivel' => $r->nivel_riesgo,
            ])->values()->all();
    }

    /** #934: total real detrás de {@see listosParaTerminal()}, para el pie "+N más" (nunca cortar en silencio). */
    public function listosParaTerminalTotal(): int
    {
        return RoadmapItem::autoEjecutable()
            ->whereNull('archivado_at')
            ->whereNull('branch')
            ->count();
    }

    /** El PROTOCOLO DE COORDINACIÓN que Thomas T arbitra (para la identidad/UI del supervisor). */
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
