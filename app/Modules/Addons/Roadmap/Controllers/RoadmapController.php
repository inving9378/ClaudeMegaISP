<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\CircuitoEjecucion;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use App\Modules\Addons\Roadmap\Services\SessionTreeService;
use App\Modules\Addons\Roadmap\Services\SupervisorService;
use App\Modules\Addons\Roadmap\Services\WatchdogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RoadmapController extends Controller
{
    public function __construct(
        private RoadmapCircuitoService $svc,
        private WatchdogService $watchdog,
        private SupervisorService $supervisor,
        private SessionTreeService $sessionTree
    ) {
    }

    /**
     * GET /api/roadmap/torre — datos EN VIVO de la Torre de control del Circuito.
     * Conteos (estado/nivel + kill switch), cola requiere_irving, actividad reciente
     * (items con comentarios_claude) y riesgos de la última auditoría (log fase1_auditoria).
     */
    public function torre(): JsonResponse
    {
        $this->authorize('roadmap_view');

        $cola = RoadmapItem::where('estado_aprobacion', 'requiere_irving')
            ->ordered()->limit(20)->get()
            ->map(fn (RoadmapItem $i) => array_merge($this->svc->compact($i), [
                'recomendacion' => $i->comentarios_claude,   // texto completo del decisor (pregunta + recomendación)
                'opciones'      => $i->opciones,              // array de opciones | null (forks para elegir)
                'opcion_elegida' => $i->opcion_elegida,
                'resumen'       => $this->resumenItem($i),        // resumen corto para 🔊 Escuchar / tarjeta (mismo que Integración)
                'modulo_url'    => $this->moduloUrl($i->modulo),  // 🔎 Ver más → pantalla del módulo (null = fallback en la UI)
            ]));

        // #348: cola EJECUTABLE — SOLO lo que el circuito AUTO-CORRE (A/B o ya aprobado por Irving),
        // NO los C/requiere_irving/negocio (esos esperan tu decisión y jamás los corre solo).
        // Ya ordenada 🔥→prioridad→antigüedad; aquí el 🔥 salta la fila y dispara vuelta.
        $colaEjecutable = RoadmapItem::autoEjecutable()
            ->ordered()->limit(25)->get()
            ->map(fn (RoadmapItem $i) => $this->svc->compact($i));

        // #348: resumen de la cola — cuántos auto-corre el circuito vs cuántos esperan tu decisión
        // (+ los sin clasificar que el circuito aún triará). Cuentan sobre TODA la cola, no el limit.
        $resumenCola = [
            'auto_ejecutables' => RoadmapItem::autoEjecutable()->count(),
            'espera_decision'  => RoadmapItem::esperaDecision()->count(),
            'sin_clasificar'   => RoadmapItem::where('status', 'pending')->tomablePorCircuito()
                ->whereNull('nivel_riesgo')
                ->whereNotIn('estado_aprobacion', ['requiere_irving', 'rechazado', 'completado', 'cancelado'])
                ->count(),
        ];

        $actividad = RoadmapItem::whereNotNull('comentarios_claude')
            ->orderByRaw('COALESCE(revisado_at, updated_at) DESC')
            ->limit(8)->get()
            ->map(fn (RoadmapItem $i) => [
                'id'                => $i->id,
                'title'             => $i->title,
                'nivel_riesgo'      => $i->nivel_riesgo,
                'estado_aprobacion' => $i->estado_aprobacion,
                'aprobado_por'      => $i->aprobado_por,
                'comentario'        => mb_strimwidth((string) $i->comentarios_claude, 0, 220, '…'),
                'cuando'            => optional($i->revisado_at ?? $i->updated_at)->toIso8601String(),
            ]);

        // Riesgos de la última auditoría registrada en el log (fase1_auditoria).
        $riesgos = [];
        $audit = RoadmapItem::whereNotNull('log')->where('log', 'like', '%fase1_auditoria%')
            ->orderBy('id', 'desc')->first();
        if ($audit) {
            foreach (($audit->log ?? []) as $entry) {
                if (isset($entry['fase1_auditoria']['riesgos'])) {
                    $riesgos = $entry['fase1_auditoria']['riesgos'];
                }
            }
        }

        $ejecuciones = CircuitoEjecucion::orderByDesc('id')->limit(12)->get()
            ->map(fn (CircuitoEjecucion $e) => [
                'id'            => $e->id,
                'started_at'    => optional($e->started_at)->toIso8601String(),
                'duracion_seg'  => $e->duracion_seg,
                'modo'          => $e->modo,
                'pausado'       => $e->pausado,
                'rc'            => $e->rc,
                'items_tocados' => $e->items_tocados,
                'n_propuestas'  => $e->n_propuestas,
                'n_decisiones'  => $e->n_decisiones,
                'ejecuto'       => $e->ejecuto,
                'resumen'       => $e->resumen,
            ]);

        $ultima = CircuitoEjecucion::orderByDesc('id')->first();

        return response()->json([
            'generated_at'         => now()->toIso8601String(),
            'circuito_pausado'     => $this->svc->isPaused(),
            'circuito_modo'        => $this->svc->getModo(),
            'resumen'              => $this->svc->resumen(),
            'cola_requiere_irving' => $cola,
            'cola_ejecutable'      => $colaEjecutable,   // #348: SOLO auto-ejecutables (A/B o aprobados) con 🔥
            'resumen_cola'         => $resumenCola,      // #348: N auto-ejecutables · M esperan tu decisión
            'actividad_reciente'   => $actividad,
            'riesgos_auditoria'    => $riesgos,
            'auditoria_item_id'    => $audit?->id,
            'ejecuciones'          => $ejecuciones,
            // Estado EN VIVO de la vuelta (#335): corriendo/inactivo + heartbeat + próxima.
            'live'                 => $this->svc->liveState(),
            // Visor "Trabajando ahora" (#349): sesiones (array listo-para-N, 1 hoy) con fases
            // y stepper + resumen de la última vuelta (CIRCUITO_META).
            'trabajando'           => $this->svc->trabajandoAhora(),
            'proxima_vuelta_at'    => $this->svc->proximaVueltaAt(),
            'ultima_vuelta_at'     => optional($ultima?->started_at)->toIso8601String(),
            'circuito_intervalo_min' => (int) config('circuito.interval_min', 30),
            // Pool continuo (#334): latido del scheduler → "cron vivo" aunque esté ocioso por falta de
            // trabajo seguro (evita el falso "cron detenido"). + cuántos auto-ejecutables hay en cola.
            'scheduler_beat_secs'  => $this->svc->schedulerBeatSecs(),
            'cron_vivo'            => ($s = $this->svc->schedulerBeatSecs()) !== null && $s < 180,
            'auto_ejecutables'     => RoadmapItem::autoEjecutable()->count(),
            // Watchdog del equipo (#334): salud por slot + alertas escaladas + bitácora de recuperación.
            'watchdog'             => $this->watchdog->estado(),
            'watchdog_bitacora'    => $this->watchdog->bitacora(15),
            'worker_nombres'       => $this->svc->nombresWorkers(),   // roster editable (#334)
            'supervisor'           => $this->supervisor->estado(),    // Thomas T: jefe + su feed (#334)
            'can_disparar'         => (bool) auth()->user()?->can('circuito.disparar'),
            'voz_tts'              => $this->svc->getVozTts(),   // #424: voz guardada para 🔊 Escuchar (bandeja + Integración usan la misma)
        ]);
    }

    /**
     * GET /api/roadmap/circuito/estado — payload LIGERO para el polling en vivo de la Torre
     * (#335). Solo el estado de la vuelta actual (corriendo/inactivo/pausado + heartbeat),
     * próxima/última y el tail del log — sin recalcular todo el panorama cada pocos segundos.
     */
    public function estado(): JsonResponse
    {
        $this->authorize('roadmap_view');

        $ultima = CircuitoEjecucion::orderByDesc('id')->first();

        return response()->json([
            'generated_at'      => now()->toIso8601String(),
            'circuito_pausado'  => $this->svc->isPaused(),
            'circuito_modo'     => $this->svc->getModo(),
            'live'              => $this->svc->liveState(),
            'log_tail'          => $this->svc->liveLogTail(),
            // Visor "Trabajando ahora" (#349): stepper de fases por sesión + resumen de la vuelta.
            'trabajando'        => $this->svc->trabajandoAhora(),
            'proxima_vuelta_at' => $this->svc->proximaVueltaAt(),
            'ultima_vuelta_at'  => optional($ultima?->started_at)->toIso8601String(),
            'circuito_intervalo_min' => (int) config('circuito.interval_min', 30),
            // Watchdog del equipo (#334): salud por slot + alertas para el polling en vivo.
            'watchdog'          => $this->watchdog->estado(),
            'supervisor'        => $this->supervisor->estado(),   // Thomas T + su feed (#334)
            'can_disparar'      => (bool) auth()->user()?->can('circuito.disparar'),
        ]);
    }

    /**
     * GET /api/roadmap/circuito/sesiones — árbol de sesiones `claude` vivas en el box (#345):
     * SOLO LECTURA de sistema operativo (ps + /proc/{pid}/cwd), cruzado con el latido del
     * circuito para enriquecer las sesiones autónomas. Incluye el banner de colisión (2+
     * sesiones en el mismo cwd — el escenario del incidente 2026-07-11). Backend puro; el panel
     * Vue de la Torre que lo consume queda para una siguiente entrega.
     */
    public function sesiones(): JsonResponse
    {
        $this->authorize('roadmap_view');

        return response()->json($this->sessionTree->arbol());
    }

    /**
     * POST /api/roadmap/circuito/toggle — alterna el KILL SWITCH del Circuito.
     * Gateado por permiso propio (circuito.pause). El ejecutor lo respeta vía el flag
     * expuesto en resumen; el enforcement server-side vive en RoadmapCircuitoService::guard().
     */
    public function toggleCircuito(): JsonResponse
    {
        $this->authorize('circuito.pause');

        $nuevo = ! $this->svc->isPaused();
        $this->svc->setPaused($nuevo);

        return response()->json(['circuito_pausado' => $nuevo]);
    }

    /**
     * POST /api/roadmap/circuito/disparar (#337) — dispara una vuelta inmediata desde la Torre.
     * Gate circuito.disparar. En PAUSA se bloquea (decisión de Irving) → 423. El picker on-box
     * consume el flag en segundos y lanza vuelta.sh (que enciende el estado en vivo #335).
     */
    public function disparar(): JsonResponse
    {
        $this->authorize('circuito.disparar');

        $r = $this->svc->requestDisparo($this->actorLabel(), 'boton', null);

        return response()->json($r, ($r['ok'] ?? false) ? 200 : 423); // 423 Locked = en pausa
    }

    /**
     * POST /api/roadmap/items/{id}/urgente (#337/#348) — marca/desmarca un item como urgente.
     * Al marcar: sube prioridad a 'alta', sella urgente_at/by y sube el item al frente de
     * `ordered()`. DOS semánticas según dónde vive el item (#348):
     *   • Item EJECUTABLE (pendiente/aprobado) → 🔥 = "hazlo YA": salta la fila y DISPARA una
     *     vuelta inmediata (origen 'urgente'); el ejecutor lo atiende primero.
     *   • Item en la BANDEJA (estado_aprobacion=requiere_irving, espera decisión de Irving) →
     *     🔥 = "decisión urgente": solo lo sube al TOPE de la bandeja (ordered() urgentes-primero);
     *     NO dispara vuelta (el circuito no ejecuta lo que depende de Irving).
     * Gate circuito.disparar. Desmarcar solo limpia la bandera (no dispara).
     */
    public function urgente(Request $request, int $id): JsonResponse
    {
        $this->authorize('circuito.disparar');

        $data = $request->validate(['urgente' => ['sometimes', 'boolean']]);
        $marcar = array_key_exists('urgente', $data) ? (bool) $data['urgente'] : true;

        $item = RoadmapItem::find($id);
        if (! $item) {
            return response()->json(['error' => 'Item no encontrado'], 404);
        }

        $item->urgente = $marcar;
        if ($marcar) {
            $item->urgente_at = now();
            $item->urgente_by = $this->actorLabel();
            if ($item->priority !== 'alta') {
                $item->priority = 'alta';
            }
        } else {
            $item->urgente_at = null;
            $item->urgente_by = null;
        }
        $item->save();

        // #348: el 🔥 solo dispara una vuelta en items EJECUTABLES. Un item en la bandeja
        // (requiere_irving) espera la decisión de Irving → el circuito no lo ejecuta; ahí el 🔥
        // es "decisión urgente" y solo lo sube al tope de la bandeja (vía ordered()), sin disparar.
        $esDecisionIrving = $item->estado_aprobacion === 'requiere_irving';
        $disparo = ($marcar && ! $esDecisionIrving)
            ? $this->svc->requestDisparo($this->actorLabel(), 'urgente', $item->id)
            : null;

        return response()->json([
            'ok'       => true,
            'urgente'  => $item->urgente,
            'priority' => $item->priority,
            'modo'     => $esDecisionIrving ? 'bandeja' : 'ejecucion',
            'disparo'  => $disparo,
        ]);
    }

    /** Etiqueta del actor humano para auditoría (login/email/id). */
    private function actorLabel(): string
    {
        $u = auth()->user();

        return 'irving:' . ($u->login_user ?? $u->email ?? $u->id ?? '?');
    }

    /**
     * POST /api/roadmap/circuito/decidir — DECISIÓN HUMANA de Irving sobre un item
     * requiere_irving (bandeja de la Torre). Gateado por circuito.decidir. Escribe
     * aprobado_irving | rechazado (o solo comenta), la opción elegida, el comentario,
     * quién/cuándo, y audita. NO pasa por el guard externo (es la vía autenticada);
     * es la ÚNICA que puede fijar aprobado_irving. Funciona aun con el circuito en pausa
     * (el kill switch frena al ejecutor, no a Irving).
     */
    public function decidir(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');

        $data = $request->validate([
            'id'             => ['required', 'integer', 'min:1'],
            'accion'         => ['required', 'string', 'in:aprobar,rechazar,comentar,cerrar,cancelar'],
            'opcion_elegida' => ['sometimes', 'nullable', 'string', 'max:255'],
            'comentario'     => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $item = RoadmapItem::find($data['id']);
        if (! $item) {
            return response()->json(['error' => 'Item no encontrado'], 404);
        }

        $user  = auth()->user();
        $autor = 'irving:' . ($user->login_user ?? $user->email ?? $user->id);

        $nuevoEstado = match ($data['accion']) {
            'aprobar'  => 'aprobado_irving',
            'rechazar' => 'rechazado',
            'cerrar'   => 'completado',
            'cancelar' => 'cancelado',
            'comentar' => $item->estado_aprobacion, // sigue en su estado (normalmente requiere_irving)
        };

        // El status (pending/in_progress/done/cancelled) acompaña al cierre/cancelación.
        if ($data['accion'] === 'cerrar') {
            $item->status = 'done';
            $item->completed_at = now();
        } elseif ($data['accion'] === 'cancelar') {
            $item->status = 'cancelled';
        }

        if (! empty($data['opcion_elegida'])) {
            $item->opcion_elegida = $data['opcion_elegida'];
        }
        if (! empty($data['comentario'])) {
            $item->comentarios_claude = $data['comentario'];
        }
        $item->estado_aprobacion = $nuevoEstado;
        $item->aprobado_por      = $autor;
        $item->revisado_at       = now();

        $log = $item->log ?: [];
        $log[] = [
            'ts'             => now()->toIso8601String(),
            'por'            => $autor,
            'decision'       => $data['accion'],
            'estado'         => $nuevoEstado,
            'opcion_elegida' => $data['opcion_elegida'] ?? null,
            'comentario'     => $data['comentario'] ?? null,
        ];
        $item->log = $log;
        $item->save();

        Log::channel('roadmap_externo')->info('decision-irving', [
            'item'   => $item->id,
            'por'    => $autor,
            'accion' => $data['accion'],
            'estado' => $nuevoEstado,
        ]);

        return response()->json([
            'ok'   => true,
            'item' => [
                'id'                => $item->id,
                'estado_aprobacion' => $item->estado_aprobacion,
                'opcion_elegida'    => $item->opcion_elegida,
            ],
        ]);
    }

    /**
     * POST /api/roadmap/circuito/seguimiento — crea un item NUEVO vinculado (origen_item_id)
     * desde una decisión, y opcionalmente cierra el origen (completado). El seguimiento entra
     * en pendiente_revision para que el circuito lo triajee. Gateado por circuito.decidir.
     */
    public function seguimiento(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');

        $data = $request->validate([
            'origen_item_id' => ['required', 'integer', 'min:1'],
            'titulo'         => ['required', 'string', 'max:255'],
            'descripcion'    => ['sometimes', 'nullable', 'string', 'max:20000'],
            'nivel_riesgo'   => ['sometimes', 'nullable', 'string', 'in:' . implode(',', RoadmapItem::NIVELES_RIESGO)],
            'cerrar_origen'  => ['sometimes', 'boolean'],
            'comentario'     => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $origen = RoadmapItem::find($data['origen_item_id']);
        if (! $origen) {
            return response()->json(['error' => 'Item de origen no encontrado'], 404);
        }

        $autor = $this->actor();

        $nuevo = RoadmapItem::create([
            'title'             => $data['titulo'],
            'modulo'            => $origen->modulo,
            'description'       => $data['descripcion'] ?? null,
            'status'            => 'pending',
            'priority'          => $origen->priority ?: 'media',
            'nivel_riesgo'      => $data['nivel_riesgo'] ?? null,
            'estado_aprobacion' => 'pendiente_revision',
            'origen_item_id'    => $origen->id,
            'log'               => [[
                'ts' => now()->toIso8601String(), 'por' => $autor,
                'evento' => 'creado_como_seguimiento', 'origen' => $origen->id,
            ]],
        ]);

        if (! empty($data['cerrar_origen'])) {
            $origen->estado_aprobacion = 'completado';
            $origen->status = 'done';
            $origen->completed_at = now();
            $origen->aprobado_por = $autor;
            $origen->revisado_at = now();
            if (! empty($data['comentario'])) {
                $origen->comentarios_claude = $data['comentario'];
            }
            $log = $origen->log ?: [];
            $log[] = ['ts' => now()->toIso8601String(), 'por' => $autor, 'evento' => 'cerrado_con_seguimiento', 'seguimiento' => $nuevo->id, 'comentario' => $data['comentario'] ?? null];
            $origen->log = $log;
            $origen->save();
        }

        Log::channel('roadmap_externo')->info('seguimiento-creado', ['origen' => $origen->id, 'nuevo' => $nuevo->id, 'por' => $autor, 'cerro_origen' => (bool) ($data['cerrar_origen'] ?? false)]);

        return response()->json([
            'ok'             => true,
            'nuevo'          => ['id' => $nuevo->id, 'title' => $nuevo->title],
            'origen_cerrado' => (bool) ($data['cerrar_origen'] ?? false),
        ]);
    }

    /**
     * GET /api/roadmap/integracion — Vista de Integración (#315): ramas del circuito
     * (una por item con branch) con semáforo de verificación, archivos + diff y estado
     * de merge. Alimenta la revisión visual antes de mergear a dev.
     */
    public function integracion(): JsonResponse
    {
        $this->authorize('roadmap_view');

        // Radar ACTIVO (#334): solo NO-archivados. Lo backend/interno ya integrado se auto-archivó
        // en el merge → sale del radar (queda en Historial). Aquí quedan: lo UI-verificable (aunque
        // ya mergeado, esperando la revisión visual de Irving) + lo pendiente (en cola/escalado).
        $ramas = RoadmapItem::whereNotNull('branch')->noArchivado()->orderByDesc('id')->limit(80)->get()
            ->map(fn (RoadmapItem $i) => $this->ramaPayload($i));

        return response()->json([
            'generated_at'     => now()->toIso8601String(),
            'modo_integracion' => $this->svc->getModoIntegracion(),
            'auto_merge'       => $this->svc->autoMergeOn(),   // toggle ON/OFF (#334 F0-fix)
            'voz_tts'          => $this->svc->getVozTts(),      // voz elegida para 🔊 Escuchar (#424, null = automática)
            'ramas'            => $ramas,
            'archivadas_count' => RoadmapItem::whereNotNull('branch')->archivado()->count(),
        ]);
    }

    /** POST /api/roadmap/integracion/voz — guarda la voz (es-*) elegida por el administrador para 🔊 Escuchar (#424). */
    public function integracionVoz(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate(['voz' => ['nullable', 'string', 'max:200']]);
        $this->svc->setVozTts($data['voz'] ?? null);
        Log::channel('roadmap_externo')->info('integracion-voz', ['voz' => $data['voz'] ?? null, 'por' => $this->actor()]);
        return response()->json(['ok' => true, 'voz_tts' => $this->svc->getVozTts()]);
    }

    /** Historial de ramas ARCHIVADAS (#334) — fuera del radar, auditable y reversible ("quiero verlo"). */
    public function integracionHistorial(): JsonResponse
    {
        $this->authorize('roadmap_view');

        $ramas = RoadmapItem::whereNotNull('branch')->archivado()->orderByDesc('archivado_at')->limit(200)->get()
            ->map(fn (RoadmapItem $i) => $this->ramaPayload($i));

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'ramas'        => $ramas,
        ]);
    }

    /** Payload común de una rama para el radar y el historial. */
    /** Mapa normalizado module_key→sidebar_url (memoizado por request). */
    private ?array $moduloUrlMap = null;

    /**
     * Deriva al vuelo la ruta de la pantalla del módulo que tocó el item, sin migración.
     * `modulo` es texto libre ("Roadmap / Torre de control") → toma el segmento base y normaliza
     * (sin acentos, minúsculas, solo alfanumérico) para casar contra module_sidebar_config.module_key.
     * Devuelve null si no hay módulo o no mapea → la UI cae al fallback (la Torre).
     */
    private function moduloUrl(?string $modulo): ?string
    {
        if (! $modulo) {
            return null;
        }
        if ($this->moduloUrlMap === null) {
            $this->moduloUrlMap = [];
            $rows = \Illuminate\Support\Facades\DB::table('module_sidebar_config')
                ->whereNotNull('sidebar_url')->where('sidebar_url', '!=', '')
                ->get(['module_key', 'sidebar_url']);
            foreach ($rows as $row) {
                $k = $this->normalizeModulo((string) $row->module_key);
                if ($k !== '') {
                    $this->moduloUrlMap[$k] = $row->sidebar_url;
                }
            }
        }
        $base = trim(explode('/', $modulo)[0]);   // "Roadmap / Torre de control" → "Roadmap"

        return $this->moduloUrlMap[$this->normalizeModulo($base)] ?? null;
    }

    private function normalizeModulo(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(\Illuminate\Support\Str::ascii($s)));
    }

    /**
     * Resumen CORTO del item para Escuchar (🔊) y la cabecera de la tarjeta. Usa el reporte
     * coloquial si existe; si viene vacío (lo normal hoy), cae a la descripción recortada a ~40
     * palabras. Nunca el texto extenso del reporte del ejecutor.
     */
    private function resumenItem(RoadmapItem $i): string
    {
        $coloq = trim((string) $i->reporte_coloquial);
        if ($coloq !== '') {
            return $coloq;
        }
        $desc = trim((string) $i->description);
        if ($desc === '') {
            return '';
        }
        $palabras = preg_split('/\s+/', $desc);

        return count($palabras) > 40 ? implode(' ', array_slice($palabras, 0, 40)) . '…' : $desc;
    }

    private function ramaPayload(RoadmapItem $i): array
    {
        $git = $this->diffRama($i);
        return [
            'id'                => $i->id,
            'title'             => $i->title,
            'branch'            => $i->branch,
            'autor'             => $i->aprobado_por,
            'worker_sid'        => $i->worker_sid,   // firma del worker que lo ejecutó (#334 A)
            'worker_nombre'     => $i->worker_sid ? $this->svc->nombreWorker($i->worker_sid) : null,   // roster (#334)
            'nivel_riesgo'      => $i->nivel_riesgo,
            'estado_aprobacion' => $i->estado_aprobacion,
            'merged'            => ! empty($i->merge_commit),
            'merge_commit'      => $i->merge_commit,
            'merge_pending'     => $this->svc->isMergeQueued($i->id),   // en cola / procesando (#334)
            'merge_result'      => $this->svc->mergeResult($i->id),     // último intento (ok/error/escalado) → UI lo muestra
            'marcado_version'   => (bool) $i->marcado_version,
            'revision_ui'       => $i->revision_ui,   // true=verificable por UI · false=backend/interno · null=sin clasificar
            'ui_hint'           => $i->ui_hint,       // QUÉ cambió / DÓNDE mirarlo / QUÉ probar (solo UI)
            'archivado'         => ! empty($i->archivado_at),
            'archivado_at'      => optional($i->archivado_at)->toIso8601String(),
            'archivado_por'     => $i->archivado_por,
            'modulo'            => $i->modulo,
            'modulo_url'        => $this->moduloUrl($i->modulo),   // "Ver más" → pantalla del módulo (null = fallback en la UI)
            'verificacion'      => $this->semaforo($i),
            'reporte'           => $i->comentarios_claude,   // reporte del ejecutor (qué hace/cómo validar/verificación)
            'resumen'           => $this->resumenItem($i),   // resumen CORTO para Escuchar/tarjeta (coloquial → fallback descripción)
            'descripcion'       => $i->description,
            'existe_rama'       => $git['existe'],
            'stat'              => $git['stat'],
            'archivos'          => $git['archivos'],
            'diff'              => $git['diff'],
        ];
    }

    /**
     * POST /api/roadmap/integracion/archivar — saca una rama del radar → Historial (reversible).
     * Con `todos_mergeados=true`: archiva EN MASA todo lo ya mergeado y aún no archivado.
     */
    public function integracionArchivar(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate([
            'id'               => ['nullable', 'integer', 'min:1'],
            'todos_mergeados'  => ['nullable', 'boolean'],
        ]);

        // Modo masivo: "Archivar todo lo ya mergeado/validado".
        if (! empty($data['todos_mergeados'])) {
            $n = 0;
            RoadmapItem::whereNotNull('branch')->whereNotNull('merge_commit')->noArchivado()
                ->orderByDesc('id')->chunkById(100, function ($items) use (&$n) {
                    foreach ($items as $i) {
                        $this->sellarArchivo($i, $this->actor() . ' (masivo mergeados)');
                        $n++;
                    }
                });
            Log::channel('roadmap_externo')->info('integracion-archivar-masivo', ['n' => $n, 'por' => $this->actor()]);
            return response()->json(['ok' => true, 'archivadas' => $n]);
        }

        // Modo individual.
        if (empty($data['id'])) {
            return response()->json(['error' => 'Falta id (o todos_mergeados=true).'], 422);
        }
        $item = RoadmapItem::whereNotNull('branch')->find($data['id']);
        if (! $item) {
            return response()->json(['error' => 'Rama no encontrada'], 404);
        }
        $this->sellarArchivo($item, $this->actor());
        Log::channel('roadmap_externo')->info('integracion-archivar', ['item' => $item->id, 'por' => $this->actor()]);
        return response()->json(['ok' => true, 'archivado' => true]);
    }

    /** POST /api/roadmap/integracion/desarchivar — devuelve una rama al radar ("quiero verlo"). */
    public function integracionDesarchivar(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = RoadmapItem::whereNotNull('branch')->find($data['id']);
        if (! $item) {
            return response()->json(['error' => 'Rama no encontrada'], 404);
        }
        $item->archivado_at  = null;
        $item->archivado_por = null;
        // Si es backend y lo quiere ver, dejar constancia de que pidió verlo.
        if ($item->revision_ui === false) {
            $item->revision_ui = true;
            $item->ui_hint = trim(($item->ui_hint ? $item->ui_hint . ' · ' : '') . 'Traído al radar por decisión de Irving ("quiero verlo").');
        }
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => $this->actor(), 'evento' => 'desarchivado'];
        $item->log = $log;
        $item->save();
        Log::channel('roadmap_externo')->info('integracion-desarchivar', ['item' => $item->id, 'por' => $this->actor()]);
        return response()->json(['ok' => true, 'archivado' => false]);
    }

    /** POST /api/roadmap/circuito/worker-nombre — renombra un worker del roster (wt-K → nombre). */
    public function workerNombre(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate([
            'sid'    => ['required', 'string', 'regex:/^wt-\d+$/'],
            'nombre' => ['nullable', 'string', 'max:24'],
        ]);
        $this->svc->setNombreWorker($data['sid'], (string) ($data['nombre'] ?? ''));
        Log::channel('roadmap_externo')->info('worker-nombre', ['sid' => $data['sid'], 'nombre' => $data['nombre'] ?? '', 'por' => $this->actor()]);
        return response()->json(['ok' => true, 'nombres' => $this->svc->nombresWorkers()]);
    }

    /** Sella el archivo de una rama (idempotente): marca archivado_at/por + deja rastro en el log. */
    private function sellarArchivo(RoadmapItem $item, string $por): void
    {
        if ($item->archivado_at) {
            return;
        }
        $item->archivado_at  = now();
        $item->archivado_por = $por;
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => $por, 'evento' => 'archivado'];
        $item->log = $log;
        $item->save();
    }

    /** POST /api/roadmap/integracion/modo — cambia el modo de integración (auto-merge | revisar-y-mergear). */
    public function integracionModo(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate([
            'modo' => ['required', 'string', 'in:' . implode(',', \App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService::MODOS_INTEGRACION)],
        ]);
        $this->svc->setModoIntegracion($data['modo']);
        Log::channel('roadmap_externo')->info('integracion-modo', ['modo' => $data['modo'], 'por' => $this->actor()]);
        return response()->json(['ok' => true, 'modo_integracion' => $this->svc->getModoIntegracion()]);
    }

    /** POST /api/roadmap/integracion/marcar-version — marca/desmarca la rama para el armador de versiones (#312). */
    public function integracionMarcarVersion(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = RoadmapItem::find($data['id']);
        if (! $item) {
            return response()->json(['error' => 'Item no encontrado'], 404);
        }
        $item->marcado_version = ! $item->marcado_version;
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => $this->actor(), 'evento' => $item->marcado_version ? 'marcado_para_version' : 'desmarcado_version'];
        $item->log = $log;
        $item->save();
        Log::channel('roadmap_externo')->info('integracion-marcar-version', ['item' => $item->id, 'marcado' => $item->marcado_version, 'por' => $this->actor()]);
        return response()->json(['ok' => true, 'marcado_version' => $item->marcado_version]);
    }

    /** POST /api/roadmap/integracion/merge — Irving mergea la rama a dev (autoridad → --force). */
    public function integracionMerge(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = RoadmapItem::find($data['id']);
        if (! $item || ! $item->branch) {
            return response()->json(['error' => 'Item o rama no encontrada'], 404);
        }

        // El botón de Irving = autoridad de merge (incluye C) → ENCOLA con trigger 'boton'. El merge
        // REAL lo hace el runner on-box (meganet) en el checkout principal, porque www-data (esta
        // request) NO puede escribir `.git` (antes fallaba en silencio). La Torre hace polling de
        // `merge_result` y muestra éxito o el error/escalado. (#334 F0-fix)
        $this->svc->enqueueMerge($item->id, $this->actor(), 'boton');

        Log::channel('roadmap_externo')->info('integracion-merge-encolado', ['item' => $item->id, 'por' => $this->actor()]);

        return response()->json(['ok' => true, 'queued' => true, 'mensaje' => 'Merge encolado; se aplica en unos segundos.']);
    }

    /**
     * POST /api/roadmap/integracion/rechazar — rechaza la rama. Comentario OBLIGATORIO + elección:
     *   • accion=reciclar → vuelve al BACKLOG para un nuevo intento (pendiente_revision + status
     *     pending; se descarta el puntero a la rama —la rama queda en git— para que el circuito
     *     cree una nueva al re-tomarlo). El comentario le dice al próximo intento POR QUÉ se rechazó.
     *   • accion=borrar   → cancelado + archivado (fila CONSERVADA, NO hard-delete).
     */
    public function integracionRechazar(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate([
            'id'         => ['required', 'integer', 'min:1'],
            'comentario' => ['required', 'string', 'min:1', 'max:10000'],   // OBLIGATORIO
            'accion'     => ['required', 'in:reciclar,borrar'],
        ]);
        $item = RoadmapItem::find($data['id']);
        if (! $item) {
            return response()->json(['error' => 'Item no encontrado'], 404);
        }

        $por        = $this->actor();
        $comentario = trim($data['comentario']);
        $item->comentarios_claude = (string) $item->comentarios_claude
            . "\n\n--- RECHAZADA ({$data['accion']}, " . now()->toDateTimeString() . ", {$por}) ---\n" . $comentario;
        $item->aprobado_por = $por;
        $item->revisado_at  = now();
        $log = $item->log ?: [];

        if ($data['accion'] === 'reciclar') {
            $log[] = ['ts' => now()->toIso8601String(), 'por' => $por, 'evento' => 'rechazo_reciclar',
                'branch_descartada' => $item->branch, 'comentario' => $comentario];
            $item->log               = $log;
            $item->branch            = null;   // suelta la rama → reentra al backlog; el circuito hará una nueva
            $item->merge_commit      = null;
            $item->estado_aprobacion = 'pendiente_revision';
            $item->status            = 'pending';
            $item->save();
        } else { // borrar = cancelar + archivar (fila conservada)
            $log[] = ['ts' => now()->toIso8601String(), 'por' => $por, 'evento' => 'rechazo_borrar',
                'branch' => $item->branch, 'comentario' => $comentario];
            $item->log               = $log;
            $item->estado_aprobacion = 'cancelado';
            $item->status            = 'cancelled';
            $item->save();
            $this->sellarArchivo($item, $por . ' (rechazo/borrar)');
        }

        Log::channel('roadmap_externo')->info('integracion-rechazo', ['item' => $item->id, 'accion' => $data['accion'], 'por' => $por]);

        return response()->json(['ok' => true, 'item' => ['id' => $item->id, 'estado_aprobacion' => $item->estado_aprobacion, 'accion' => $data['accion']]]);
    }

    /** POST /api/roadmap/integracion/revert — revierte un merge ya integrado a dev. */
    public function integracionRevert(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = RoadmapItem::find($data['id']);
        if (! $item || ! $item->merge_commit) {
            return response()->json(['error' => 'No hay merge que revertir para este item'], 422);
        }

        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            return response()->json(['error' => 'El árbol de trabajo tiene cambios sin commitear'], 409);
        }

        $this->git(['checkout', 'main']);
        $rev = $this->git(['revert', '--no-edit', '-m', '1', $item->merge_commit]);
        if (! $rev->isSuccessful()) {
            $this->git(['revert', '--abort']);
            return response()->json(['error' => 'No se pudo revertir (conflicto): ' . $rev->getErrorOutput()], 409);
        }

        $sha = trim($this->git(['rev-parse', 'HEAD'])->getOutput());
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => $this->actor(), 'evento' => 'revert_merge', 'revert_commit' => $sha, 'merge_revertido' => $item->merge_commit];
        $item->log = $log;
        $item->merge_commit = null;
        $item->estado_aprobacion = 'requiere_irving';
        $item->save();

        Log::channel('roadmap_externo')->info('integracion-revert', ['item' => $item->id, 'por' => $this->actor(), 'revert' => $sha]);

        return response()->json(['ok' => true, 'revert_commit' => $sha]);
    }

    /** Semáforo de verificación derivado del estado/merge (detalle fino = mejora futura). */
    private function semaforo(RoadmapItem $i): array
    {
        if ($i->estado_aprobacion === 'rechazado') {
            return ['estado' => 'fail', 'detalle' => 'Rechazado.'];
        }
        if (! empty($i->merge_commit) || in_array($i->estado_aprobacion, ['completado', 'aprobado_claude', 'aprobado_irving'], true)) {
            return ['estado' => 'ok', 'detalle' => 'Verificado / aprobado (regresión cero registrada por el ejecutor).'];
        }
        return ['estado' => 'pending', 'detalle' => 'Pendiente de verificación/decisión.'];
    }

    /**
     * Diff que introdujo la rama del item. Si ya está mergeada, se toma del merge commit
     * (segundo padre: merge_commit^1..merge_commit), porque tras el merge el merge-base
     * coincide con la punta de la rama y el diff daría vacío. Si NO está mergeada, se toma
     * respecto al punto de fork con main (merge-base..branch).
     */
    private function diffRama(RoadmapItem $i): array
    {
        if (! empty($i->merge_commit)) {
            if (! $this->git(['rev-parse', '--verify', $i->merge_commit])->isSuccessful()) {
                return ['existe' => false, 'stat' => '', 'archivos' => [], 'diff' => ''];
            }
            $range = "{$i->merge_commit}^1..{$i->merge_commit}";
        } else {
            if (! $this->git(['rev-parse', '--verify', $i->branch])->isSuccessful()) {
                return ['existe' => false, 'stat' => '', 'archivos' => [], 'diff' => ''];
            }
            $base  = trim($this->git(['merge-base', 'main', $i->branch])->getOutput());
            $range = "{$base}..{$i->branch}";
        }
        $stat  = trim($this->git(['diff', '--stat', $range])->getOutput());
        $files = array_values(array_filter(explode("\n", trim($this->git(['diff', '--name-only', $range])->getOutput()))));
        $diff  = $this->git(['diff', $range])->getOutput();
        if (mb_strlen($diff) > 20000) {
            $diff = mb_substr($diff, 0, 20000) . "\n… (diff truncado; ver rama completa)";
        }
        return ['existe' => true, 'stat' => $stat, 'archivos' => $files, 'diff' => $diff];
    }

    private function actor(): string
    {
        $u = auth()->user();
        return 'irving:' . ($u->login_user ?? $u->email ?? $u->id);
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->run();
        return $p;
    }

    // GET /api/roadmap/items
    public function index(Request $request): JsonResponse
    {
        $this->authorize('roadmap_view');

        // Pipeline por estado (#): ?vista=backlog deja SOLO lo que aún no entró a otra pestaña
        // (Terminales/Integración/terminal). El default (sin vista) sigue devolviendo TODO —
        // no rompe otros consumidores.
        $q = $request->query('vista') === 'backlog'
            ? RoadmapItem::backlog($this->svc->idsEnCurso())->ordered()
            : RoadmapItem::ordered();

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('version')) {
            $q->where('target_version', $request->version);
        }

        return response()->json($q->get());
    }

    // POST /api/roadmap/items
    public function store(Request $request): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'priority'       => 'nullable|in:alta,media,baja',
            'target_version' => 'nullable|string|max:20',
            'prompt'         => 'nullable|string',
        ]);

        $data['status']   = 'pending';
        $data['position'] = RoadmapItem::where('status', 'pending')->max('position') + 1;

        // Si no se envían sub-tareas, sembrar las 5 por defecto
        if (! $request->has('subtasks')) {
            $data['subtasks'] = self::defaultSubtasks();
        }

        $item = RoadmapItem::create($data);

        return response()->json($item, 201);
    }

    // PATCH /api/roadmap/items/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        $data = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'priority'       => 'nullable|in:alta,media,baja',
            'target_version' => 'nullable|string|max:20',
            'prompt'         => 'nullable|string',
            'position'       => 'sometimes|integer',
        ]);

        $item->update($data);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/start
    public function start(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        if ($item->status === 'in_progress') {
            return response()->json($item);
        }

        $current = RoadmapItem::currentInProgress();
        if ($current && $current->id !== $id) {
            return response()->json([
                'message' => "Ya hay una tarea en progreso: \"{$current->title}\". Termínala antes de empezar otra.",
            ], 422);
        }

        $item->update([
            'status'     => 'in_progress',
            'started_at' => $item->started_at ?? now(),
        ]);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/complete
    public function complete(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);
        $item->update([
            'status'       => 'done',
            'completed_at' => $item->completed_at ?? now(),
        ]);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/cancel
    public function cancel(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);
        $item->update(['status' => 'cancelled']);

        return response()->json($item->fresh());
    }

    // DELETE /api/roadmap/items/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        RoadmapItem::findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    // PATCH /api/roadmap/items/{id}/subtasks — reemplaza la lista completa
    public function updateSubtasks(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        $data = $request->validate([
            'subtasks'                  => 'required|array',
            'subtasks.*.title'          => 'required|string|max:255',
            'subtasks.*.completed'      => 'required|boolean',
            'subtasks.*.completed_at'   => 'nullable|string',
        ]);

        $item->update(['subtasks' => $data['subtasks']]);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/subtasks/{index}/toggle
    public function toggleSubtask(int $id, int $index): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);
        $subtasks = $item->subtasks ?? [];

        if (! isset($subtasks[$index])) {
            return response()->json(['message' => 'Sub-tarea no encontrada.'], 404);
        }

        $subtasks[$index]['completed'] = ! $subtasks[$index]['completed'];
        $subtasks[$index]['completed_at'] = $subtasks[$index]['completed']
            ? now()->toIso8601String()
            : null;

        $item->update(['subtasks' => $subtasks]);

        return response()->json($item->fresh());
    }

    // POST /api/roadmap/items/{id}/log — agrega una entrada
    public function addLog(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $log = $item->log ?? [];
        $log[] = [
            'text'       => $data['text'],
            'created_at' => now()->toIso8601String(),
        ];

        $item->update(['log' => $log]);

        return response()->json($item->fresh());
    }

    private static function defaultSubtasks(): array
    {
        return [
            ['title' => 'Prompt enviado a Claude Code.',                            'completed' => false, 'completed_at' => null],
            ['title' => 'Reporte/plan recibido y aprobado.',                        'completed' => false, 'completed_at' => null],
            ['title' => 'Implementación reportada por Claude Code.',                'completed' => false, 'completed_at' => null],
            ['title' => 'Verificación server-side (Claude Code).',                  'completed' => false, 'completed_at' => null],
            ['title' => 'Verificación visual (admin, navegador, claro y oscuro).',  'completed' => false, 'completed_at' => null],
        ];
    }
}
