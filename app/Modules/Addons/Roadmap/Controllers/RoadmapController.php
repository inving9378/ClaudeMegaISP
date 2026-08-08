<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\CircuitoEjecucion;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\AutopilotService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use App\Modules\Addons\Roadmap\Services\SessionTreeService;
use App\Modules\Addons\Roadmap\Services\SupervisorService;
use App\Modules\Addons\Roadmap\Services\WatchdogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RoadmapController extends Controller
{
    public function __construct(
        private RoadmapCircuitoService $svc,
        private WatchdogService $watchdog,
        private SupervisorService $supervisor,
        private SessionTreeService $sessionTree,
        private AutopilotService $autopilot
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

        // #432 — la bandeja es TODA la estación de decisión (requiere_irving + C sin decidir +
        // [BLOCKED-/PARKED-]), no solo requiere_irving: el supervisor las enruta aquí y ninguna
        // decisión se queda perdida en la Hoja de ruta.
        // #507 sub-paso 4 — límite 20 → 100: con las bombitas por módulo al lado, una lista truncada
        // a 20 contra un contador de 71 se lee como un bug, y filtrar por módulo sobre una lista
        // recortada devolvía menos items de los que anuncia su bombita. Medido en dev: traer los 71
        // cuesta 16 ms y `torre()` completo 121 ms. Si algún día la bandeja pasa de 100, la UI avisa
        // que está mostrando N de M en vez de mentir.
        $cola = RoadmapItem::bandeja()
            ->ordered()->limit(100)->get()
            ->map(fn (RoadmapItem $i) => array_merge($this->svc->compact($i), [
                'recomendacion' => $i->comentarios_claude,   // texto completo del decisor (pregunta + recomendación)
                'opciones'      => $i->opcionesDetalladas(),  // [{clave,texto,recomendada}] — legacy/fallback (#431)
                'opcion_elegida' => $i->opcion_elegida,       // clave estable de la opción marcada (o null)
                'preguntas'     => $i->preguntasNormalizadas(), // #432 Fase 3 — todas las preguntas juntas (multi o 1 fallback)
                'resumen'       => $this->resumenItem($i),        // resumen corto para 🔊 Escuchar / tarjeta (mismo que Integración)
                'modulo_url'    => $this->moduloUrl($i->modulo),  // 🔎 Ver más → pantalla del módulo (fallback)
                'enlace_revision' => $i->enlace_revision,          // #432 ADENDA B — deep-link REAL del cambio (preferente en "Ver")
                // #477 — desplegable "Ver descripción" de la tarjeta compacta (solo lectura, campos ya existentes).
                'description'      => $i->description,
                'alcance_autorizado' => $i->alcance_autorizado,
                'fuera_de_alcance'   => $i->fuera_de_alcance,
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
            'espera_decision'  => RoadmapItem::bandeja()->count(),      // #432: toda la estación bandeja
            'sin_clasificar'   => RoadmapItem::backlog()->count(),       // #432: intake = lo que vive en la Hoja de ruta
            'intake'           => RoadmapItem::backlog()->count(),
        ];

        // [BUG][UI/UX][TORRE] Cada evento de Actividad reciente se enriquece con la UBICACIÓN ACTUAL
        // REAL del item (no la del evento): status + estacion calculada (accessor) + etiqueta legible +
        // pestaña destino + siguiente acción. Así la tarjeta puede navegar a donde el item está AHORA.
        $actividad = RoadmapItem::whereNotNull('comentarios_claude')
            ->orderByRaw('COALESCE(revisado_at, updated_at) DESC')
            ->limit(8)->get()
            ->map(function (RoadmapItem $i) {
                $ub = $this->ubicacionActual($i);
                return [
                    'id'                => $i->id,
                    'title'             => $i->title,
                    'nivel_riesgo'      => $i->nivel_riesgo,
                    'estado_aprobacion' => $i->estado_aprobacion,
                    'status'            => $i->status,
                    'estacion'          => $i->estacion,          // accessor: done|terminal|bandeja|integracion|listo|intake
                    'ubicacion'         => $ub['label'],          // "⚑ Tu Bandeja", "🔍 Integración", …
                    'ubicacion_icono'   => $ub['icon'],
                    'ubicacion_tab'     => $ub['tab'],            // panorama|roadmap|terminales|integracion|historial
                    'siguiente_accion'  => $ub['siguiente_accion'],
                    'aprobado_por'      => $i->aprobado_por,
                    'comentario'        => mb_strimwidth((string) $i->comentarios_claude, 0, 220, '…'),
                    'cuando'            => optional($i->revisado_at ?? $i->updated_at)->toIso8601String(),
                ];
            });

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
                'modelo'        => $e->modelo,
                'pausado'       => $e->pausado,
                'rc'            => $e->rc,
                'items_tocados' => $e->items_tocados,
                'n_propuestas'  => $e->n_propuestas,
                'n_decisiones'  => $e->n_decisiones,
                'ejecuto'       => $e->ejecuto,
                'resumen'       => $e->resumen,
            ]);

        // #346 (punto 2): items "en progreso" sin actividad hace >10 días — aviso PASIVO en la
        // Torre (no auto-cancela). Umbral fijo por ahora (política de N días queda para cuando
        // Irving decida la regla dura/consejo; esto es solo detección, no la resuelve).
        $estancados = RoadmapItem::posibleEstancado(10)
            ->orderBy('updated_at')->limit(20)->get()
            ->map(fn (RoadmapItem $i) => [
                'id'                => $i->id,
                'title'             => $i->title,
                'estado_aprobacion' => $i->estado_aprobacion,
                'worker_sid'        => $i->worker_sid,
                'en_desarrollo_humano' => (bool) $i->en_desarrollo_humano,
                'updated_at'        => optional($i->updated_at)->toIso8601String(),
                'dias_sin_actividad' => (int) floor($i->updated_at->diffInDays(now())),
            ]);

        $ultima = CircuitoEjecucion::orderByDesc('id')->first();

        return response()->json([
            'generated_at'         => now()->toIso8601String(),
            'circuito_pausado'     => $this->svc->isPaused(),
            // #343: salvaguarda de "pausa olvidada" — null si no está pausado.
            'circuito_pausado_info' => $this->svc->pausedInfo(),
            'circuito_modo'        => $this->svc->getModo(),
            'resumen'              => $this->svc->resumen(),
            'cola_requiere_irving' => $cola,
            'cola_ejecutable'      => $colaEjecutable,   // #348: SOLO auto-ejecutables (A/B o aprobados) con 🔥
            'resumen_cola'         => $resumenCola,      // #348: N auto-ejecutables · M esperan tu decisión
            'actividad_reciente'   => $actividad,
            // FASE 1 — "Cambios para que Irving pruebe": cambios seguros integrados esperando validación funcional.
            'cambios_validacion'   => RoadmapItem::pendienteValidacion()->limit(30)->get()
                ->map(fn (RoadmapItem $i) => $this->validacionPayload($i)),
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
            // #507 sub-paso 4 — banner del autopilot: política vigente + qué decidió hoy.
            'autopilot'            => $this->autopilot->resumen(),
            // Watchdog del equipo (#334): salud por slot + alertas escaladas + bitácora de recuperación.
            'watchdog'             => $this->watchdog->estado(),
            'watchdog_bitacora'    => $this->watchdog->bitacora(15),
            'estancados'           => $estancados,   // #346: items en_progreso sin actividad >10 días (aviso pasivo)
            'estancados_count'     => RoadmapItem::posibleEstancado(10)->count(),
            'worker_nombres'       => $this->svc->nombresWorkers(),   // roster editable (#334)
            'supervisor'           => $this->supervisor->estado(),    // Thomas T: jefe + su feed (#334)
            'can_disparar'         => (bool) auth()->user()?->can('circuito.disparar'),
            'voz_tts'              => $this->svc->getVozTts(),   // #424: voz guardada para 🔊 Escuchar (bandeja + Integración usan la misma)
            'rate_tts'             => $this->svc->getRateTts(),  // #424: velocidad guardada
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
            // #343: salvaguarda de "pausa olvidada" en el polling ligero (el banner se actualiza solo).
            'circuito_pausado_info' => $this->svc->pausedInfo(),
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
     * GET /api/roadmap/torre/decisiones/contadores — cuántas decisiones te esperan, POR MÓDULO.
     * (#507 sub-paso 5) Alimenta las "bombitas" del sidebar interno de la Torre.
     *
     * Cuenta EXACTAMENTE lo mismo que la bandeja (`RoadmapItem::bandeja()`) — si los números no
     * cuadran con la tarjeta "Requiere tu decisión", es un bug, no una diferencia de criterio.
     *
     * Normaliza `modulo` con la MISMA `normalizeModulo()`/`moduloUrl()` que usa el resto de la
     * Torre, para que las claves casen con `module_sidebar_config`. Ojo: `modulo` es texto libre
     * escrito por el ejecutor, así que dos variantes del mismo módulo pueden no colapsar (deuda de
     * *drift* registrada en la Hoja de ruta) — por eso `sin_clasificar` va aparte y visible, en vez
     * de repartirse en silencio.
     *
     * Caché corta (45 s): la bandeja no cambia de un segundo a otro y el sidebar la pide seguido.
     */
    public function decisionesContadores(): JsonResponse
    {
        $this->authorize('roadmap_view');

        return response()->json(Cache::remember('roadmap:torre:decisiones-contadores', 45, function () {
            $items = RoadmapItem::bandeja()->get(['id', 'modulo', 'urgente', 'nivel_riesgo']);

            $grupos = [];
            $sinClasificar = 0;

            foreach ($items as $i) {
                $modulo = trim((string) $i->modulo);
                // Mismo criterio de "footprint desconocido" que usa el despachador.
                if ($modulo === '' || strcasecmp($modulo, RoadmapCircuitoService::MODULO_DESCONOCIDO) === 0) {
                    $sinClasificar++;
                    continue;
                }

                $base  = trim(explode('/', $modulo)[0]);   // "Roadmap / Torre de control" → "Roadmap"
                $clave = $this->normalizeModulo($base);
                if ($clave === '') {
                    $sinClasificar++;
                    continue;
                }

                if (! isset($grupos[$clave])) {
                    $grupos[$clave] = [
                        'clave'    => $clave,
                        'modulo'   => $base,                      // etiqueta legible (la primera que aparece)
                        'url'      => $this->moduloUrl($modulo),  // null si no mapea a una pantalla
                        'n'        => 0,
                        'urgentes' => 0,
                        'niveles'  => ['A' => 0, 'B' => 0, 'C' => 0],
                    ];
                }
                $grupos[$clave]['n']++;
                if ($i->urgente) {
                    $grupos[$clave]['urgentes']++;
                }
                if (isset($grupos[$clave]['niveles'][(string) $i->nivel_riesgo])) {
                    $grupos[$clave]['niveles'][(string) $i->nivel_riesgo]++;
                }
            }

            // Más decisiones primero; a igualdad, alfabético (orden estable para la UI).
            $porModulo = array_values($grupos);
            usort($porModulo, fn ($a, $b) => [$b['n'], $a['modulo']] <=> [$a['n'], $b['modulo']]);

            // Mapa listo para pintar la bombita junto a su entrada del sidebar: se indexa por
            // `sidebar_url` (lo que la UI ya tiene a la mano), y solo con los que SÍ mapean.
            $mapa = [];
            foreach ($porModulo as $g) {
                if ($g['url']) {
                    $mapa[$g['url']] = ($mapa[$g['url']] ?? 0) + $g['n'];
                }
            }

            return [
                'generated_at'   => now()->toIso8601String(),
                'total'          => $items->count(),
                'urgentes'       => $items->where('urgente', true)->count(),
                'por_modulo'     => $porModulo,
                'mapa'           => $mapa,
                'sin_clasificar' => $sinClasificar,
            ];
        }));
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
            // #431 Fase 1 — SIN cap de 255: aceptamos clave/prosa/índice y SIEMPRE persistimos la
            // clave estable (16 chars). El 2000 es solo un tope de sanidad para la prosa entrante.
            'opcion_elegida' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // #432 Fase 3 — respuestas multi-pregunta: {preguntaId: opcion(clave/prosa/índice)}.
            'respuestas'     => ['sometimes', 'array'],
            'respuestas.*'   => ['nullable', 'string', 'max:2000'],
            'comentario'     => ['sometimes', 'nullable', 'string', 'max:10000'],
            // #507 — destrabe explícito: re-aprobar un item PARQUEADO (espera-merge / anti-bucle)
            // limpiando su parqueo. Sin esto, aprobarlo otra vez sería un no-op que solo reabriría
            // el ciclo de re-despacho.
            'forzar'         => ['sometimes', 'boolean'],
        ]);

        $item = RoadmapItem::find($data['id']);
        if (! $item) {
            return response()->json(['error' => 'Item no encontrado'], 404);
        }

        $user  = auth()->user();
        $autor = 'irving:' . ($user->login_user ?? $user->email ?? $user->id);

        // #432 Fase 3 — aplicar respuestas (multi-pregunta y/o legacy). `responderPregunta` resuelve
        // cada entrada a la CLAVE estable de SU pregunta y muta el item (se persiste en el save()).
        // Nunca guarda prosa; auto-sana valores viejos truncados por el bug de max:255 (#431).
        if (! empty($data['respuestas']) && is_array($data['respuestas'])) {
            foreach ($data['respuestas'] as $pid => $op) {
                $item->responderPregunta((string) $pid, $op !== null ? (string) $op : null);
            }
        }
        if (array_key_exists('opcion_elegida', $data) && $data['opcion_elegida'] !== null && $data['opcion_elegida'] !== '') {
            $primera = $item->preguntasNormalizadas()[0]['id'] ?? 'q1';
            $item->responderPregunta($primera, (string) $data['opcion_elegida']);
        }

        // Guard (#431/#432): aprobar sin responder TODAS las preguntas que exigen decisión → aviso
        // claro, NUNCA falla en silencio.
        if ($data['accion'] === 'aprobar' && $item->exigeOpcion()) {
            $pend = $item->preguntasPendientes();
            if (! empty($pend)) {
                return response()->json([
                    'ok'    => false,
                    'code'  => 'preguntas_pendientes',
                    'error' => count($pend) === 1
                        ? 'Elige una opción antes de aprobar.'
                        : ('Responde las ' . count($pend) . ' preguntas antes de aprobar.'),
                    'preguntas_pendientes' => $pend,
                ], 422);
            }
        }

        // #507 — GUARD ANTI-RE-APROBACIÓN. Un item PARQUEADO (trabajo terminado esperando merge, o
        // sacado del pool por el anti-bucle) ya no se destraba aprobándolo: aprobarlo otra vez es
        // justo lo que alimentaba el ciclo (#117: 13 aprobaciones idénticas, ninguna cambió nada).
        // Se responde qué acción SÍ lo mueve. `forzar=true` limpia el parqueo a propósito.
        $parqueado = (bool) $item->esperando_merge_irving || (bool) $item->bloqueado_por_bucle;
        if ($data['accion'] === 'aprobar' && $parqueado) {
            if (! ($data['forzar'] ?? false)) {
                return response()->json([
                    'ok'    => false,
                    'code'  => $item->esperando_merge_irving ? 'esperando_merge' : 'bloqueado_por_bucle',
                    'error' => $item->esperando_merge_irving
                        ? 'Este item YA está terminado y solo espera tu merge — aprobarlo otra vez no lo mueve. Usa "Mergear" (o circuito:integrar --force).'
                        : ('Item fuera del pool por anti-bucle: ' . ($item->motivo_bloqueo ?: 'se re-escaló por la misma causa varias veces')
                            . ' Aprobarlo igual reabre el ciclo; cambia algo material (decisión, alcance, rama) o vuelve a enviarlo con forzar=true.'),
                    'motivo_bloqueo' => $item->motivo_bloqueo,
                ], 422);
            }
            // Destrabe explícito de Irving: limpia el parqueo y lo devuelve al pool.
            $item->esperando_merge_irving   = false;
            $item->bloqueado_por_bucle      = false;
            $item->excluir_pool_automatico  = false;
            $item->escalaciones_fingerprint = null;
            $item->motivo_bloqueo           = null;
        }

        // #507 — el cierre/cancelación MANUAL de Irving se respeta tal cual (el guard del modelo no
        // debe reruteárselo a "esperando merge": él decidió cerrarlo sin merge).
        if (in_array($data['accion'], ['cerrar', 'cancelar'], true)) {
            $item->cierreManualIrving = true;
        }

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

        if (! empty($data['comentario'])) {
            $item->comentarios_claude = $data['comentario'];
        }
        $item->estado_aprobacion = $nuevoEstado;
        $item->aprobado_por      = $autor;
        $item->revisado_at       = now();

        $log = $item->log ?: [];
        $entrada = [
            'ts'             => now()->toIso8601String(),
            'por'            => $autor,
            'decision'       => $data['accion'],
            'estado'         => $nuevoEstado,
            'opcion_elegida' => $item->opcion_elegida,
            'respuestas'     => $data['respuestas'] ?? null,
            'comentario'     => $data['comentario'] ?? null,
        ];
        $log[] = $entrada;
        $item->log = $log;
        $item->save();

        // Loop de aprendizaje del perfil (#351): captura la decisión como candidato crudo en
        // docs/pendientes-perfil-irving.md para revisión batch. No crítico: nunca debe tumbar la
        // decisión real de Irving si falla.
        app(\App\Modules\Addons\Roadmap\Services\PerfilAprendizajeService::class)->capturar($item, $entrada);

        Log::channel('roadmap_externo')->info('decision-irving', [
            'item'   => $item->id,
            'por'    => $autor,
            'accion' => $data['accion'],
            'estado' => $nuevoEstado,
        ]);

        // #507 — aprobar un item ROTULADO no lo despacha (frontera dura: el pool excluye
        // [BLOCKED-…]/[PARKED-…]). Se aprueba igual —la decisión queda registrada— pero se avisa cuál
        // es la acción que de verdad lo destraba: QUITAR el rótulo del título.
        $aviso = null;
        if ($data['accion'] === 'aprobar' && $item->tieneRotuloBloqueo()) {
            $aviso = 'Aprobado, pero el título lleva rótulo [BLOCKED-…]/[PARKED-…]: el circuito NO lo va a tomar. '
                . 'Para desbloquearlo, quítale el rótulo al título.';
        }

        return response()->json([
            'ok'    => true,
            'aviso' => $aviso,
            'item'  => [
                'id'                => $item->id,
                'estado_aprobacion' => $item->estado_aprobacion,
                'opcion_elegida'    => $item->opcion_elegida,
            ],
        ]);
    }

    /**
     * POST /api/roadmap/circuito/elegir-opcion — persiste SOLO la opción marcada por Irving en la
     * tarjeta (opcion_elegida), SIN cambiar el estado ni ejecutar nada. Así la elección sobrevive
     * al recargar antes de tomar la acción final (aprobar/etc.). Gateado por circuito.decidir.
     */
    public function elegirOpcion(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate([
            'id'          => ['required', 'integer', 'min:1'],
            // #432 — a qué pregunta responde (multi-pregunta). Si falta, la primera (compat).
            'pregunta_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            // #431 — sin cap de 255; se persiste la clave estable resuelta, nunca la prosa.
            'opcion'      => ['nullable', 'string', 'max:2000'],
        ]);
        $item = RoadmapItem::find($data['id']);
        if (! $item) {
            return response()->json(['error' => 'Item no encontrado'], 404);
        }
        // #432 — responde LA pregunta indicada (o la primera): resuelve clave/prosa/índice → clave
        // estable de esa pregunta; null deselecciona. Persiste en `preguntas` (+ espejo legacy).
        $pid = $data['pregunta_id'] ?? ($item->preguntasNormalizadas()[0]['id'] ?? 'q1');
        $item->responderPregunta((string) $pid, ! empty($data['opcion']) ? (string) $data['opcion'] : null);
        $item->save();
        Log::channel('roadmap_externo')->info('elegir-opcion', ['item' => $item->id, 'pregunta' => $pid, 'opcion' => $item->opcion_elegida, 'por' => $this->actor()]);

        return response()->json(['ok' => true, 'item' => [
            'id'             => $item->id,
            'pregunta_id'    => $pid,
            'opcion_elegida' => $item->opcion_elegida,
            'preguntas'      => $item->preguntasNormalizadas(),
        ]]);
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
            'rate_tts'         => $this->svc->getRateTts(),     // velocidad de 🔊 Escuchar (#424)
            'ramas'            => $ramas,
            'archivadas_count' => RoadmapItem::whereNotNull('branch')->archivado()->count(),
        ]);
    }

    /** POST /api/roadmap/integracion/voz — guarda la voz (es-*) elegida por el administrador para 🔊 Escuchar (#424). */
    public function integracionVoz(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate([
            'voz'  => ['sometimes', 'nullable', 'string', 'max:200'],
            'rate' => ['sometimes', 'nullable', 'numeric', 'between:0.5,2'],
        ]);
        if ($request->has('voz')) {
            $this->svc->setVozTts($data['voz'] ?? null);
        }
        if ($request->has('rate') && $data['rate'] !== null) {
            $this->svc->setRateTts((float) $data['rate']);
        }
        Log::channel('roadmap_externo')->info('integracion-voz', ['voz' => $data['voz'] ?? null, 'rate' => $data['rate'] ?? null, 'por' => $this->actor()]);
        return response()->json(['ok' => true, 'voz_tts' => $this->svc->getVozTts(), 'rate_tts' => $this->svc->getRateTts()]);
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

    /**
     * GET /roadmap/item/{id} — página de detalle read-only de un item de la Hoja de Ruta (#426).
     * Destino real de «Ver más» en Integración/Ramas cuando el item no mapea a la pantalla de
     * ningún módulo (antes caía a /releases sin contexto del item). Abre en pestaña nueva.
     */
    public function itemDetalle(int $id)
    {
        $this->authorize('roadmap_view');

        $item = RoadmapItem::findOrFail($id);

        return view('meganet.module.roadmap.item', [
            'item' => $this->itemDetallePayload($item),
        ]);
    }

    /** Payload completo (read-only) para la página de detalle de un item. */
    private function itemDetallePayload(RoadmapItem $i): array
    {
        return [
            'id'                 => $i->id,
            'title'              => $i->title,
            'modulo'             => $i->modulo,
            'resumen'            => $this->resumenItem($i),
            'descripcion'        => $i->description,
            'reporte_tecnico'    => $i->reporte_tecnico,
            'reporte_coloquial'  => $i->reporte_coloquial,
            'reporte'            => $i->comentarios_claude,
            'opciones'           => $i->opcionesDetalladas(),   // [{clave,texto,recomendada}] legacy (#431)
            'opcion_elegida'     => $i->opcion_elegida,          // clave estable
            'preguntas'          => $i->preguntasNormalizadas(), // #432 Fase 3
            'nivel_riesgo'       => $i->nivel_riesgo,
            'estado_aprobacion'  => $i->estado_aprobacion,
            'status'             => $i->status,
            'priority'           => $i->priority,
            'branch'             => $i->branch,
            'merge_commit'       => $i->merge_commit,
            'target_version'     => $i->target_version,
            'subtasks'           => $i->subtasks,
            'log'                => $i->log,
            'worker_sid'         => $i->worker_sid,
            'worker_nombre'      => $i->worker_sid ? $this->svc->nombreWorker($i->worker_sid) : null,
            'created_at'         => optional($i->created_at)->toIso8601String(),
            'updated_at'         => optional($i->updated_at)->toIso8601String(),
            'started_at'         => optional($i->started_at)->toIso8601String(),
            'completed_at'       => optional($i->completed_at)->toIso8601String(),
        ];
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

        // [BUG][CIRCUITO] Una rama SIN diff funcional y SIN merge NO está "lista para merge":
        // branch existe + merge_commit NULL + 0 archivos ⇒ RAMA_SIN_CONTENIDO (el trabajo no se
        // implementó, o ya está cubierto en main por otra vía). Antes salía con semáforo/botón de
        // merge como si estuviera lista; y como el tip de esas ramas suele ser un commit de main,
        // mergearla la marcaba "integrada" en falso, ocultando que no hay código. Se clasifica aparte.
        $sinContenido  = empty($i->merge_commit) && $git['existe'] && empty($git['archivos']);
        $clasificacion = ! empty($i->merge_commit) ? 'mergeado'
            : (! $git['existe'] ? 'rama_perdida'
            : ($sinContenido ? 'sin_contenido' : 'listo_para_merge'));
        $verificacion  = $sinContenido
            ? ['estado' => 'sin_contenido', 'detalle' => 'La rama existe pero no aporta cambios sobre main (diff vacío) y no está mergeada. Requiere revisión: el trabajo no se implementó o ya está cubierto en main por otra vía.']
            : $this->semaforo($i);

        return [
            'id'                => $i->id,
            'title'             => $i->title,
            'branch'            => $i->branch,
            'autor'             => $i->aprobado_por,
            'worker_sid'        => $i->worker_sid,   // firma del worker que lo ejecutó (#334 A)
            'worker_nombre'     => $i->worker_sid ? $this->svc->nombreWorker($i->worker_sid) : null,   // roster (#334)
            'nivel_riesgo'      => $i->nivel_riesgo,
            'estado_aprobacion' => $i->estado_aprobacion,
            'clasificacion'     => $clasificacion,   // [BUG][CIRCUITO] sin_contenido | listo_para_merge | mergeado | rama_perdida
            'sin_contenido'     => $sinContenido,     // atajo UI: oculta el botón "Mergear" y marca "requiere revisión"
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
            'modulo_url'        => $this->moduloUrl($i->modulo),   // "Ver más" → pantalla del módulo (fallback)
            'enlace_revision'   => $i->enlace_revision,            // #432 ADENDA B — deep-link REAL (preferente en "Ver")
            'verificacion'      => $verificacion,   // [BUG][CIRCUITO] degradado a 'sin_contenido' cuando la rama no aporta diff
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

    /**
     * FASE 1 — GET /api/roadmap/validacion — "Cambios para que Irving pruebe".
     * Cambios seguros ya integrados que esperan su validación FUNCIONAL (revisa el resultado, no el código).
     */
    public function validacionPendiente(): JsonResponse
    {
        $this->authorize('roadmap_view');
        $cambios = RoadmapItem::pendienteValidacion()->limit(30)->get()
            ->map(fn (RoadmapItem $i) => $this->validacionPayload($i));

        return response()->json(['generated_at' => now()->toIso8601String(), 'cambios' => $cambios]);
    }

    /** Tarjeta de validación funcional (muestra RESULTADO, no código). */
    private function validacionPayload(RoadmapItem $i): array
    {
        $b = is_array($i->validacion_brief) ? $i->validacion_brief : [];

        return [
            'id'                 => $i->id,
            'title'              => $i->title,
            'modulo'             => $i->modulo,
            'nivel_riesgo'       => $i->nivel_riesgo,
            'que_se_pidio'       => $b['que_se_pidio']       ?? $i->description,
            'que_se_hizo'        => $b['que_se_hizo']        ?? $i->comentarios_claude,
            'como_probar'        => $b['como_probar']        ?? null,
            'resultado_esperado' => $b['resultado_esperado'] ?? null,
            'que_no_se_toco'     => $b['que_no_se_toco']     ?? $i->fuera_de_alcance,
            'riesgo'             => $b['riesgo']             ?? ('nivel ' . ($i->nivel_riesgo ?: '—')),
            'integrado_at'       => $b['integrado_at']       ?? optional($i->updated_at)->toIso8601String(),
            'merge_commit'       => $i->merge_commit,
            'enlace_probar'      => '/roadmap/item/' . $i->id,   // "Abrir y probar" → detalle real read-only
            'modulo_url'         => $this->moduloUrl($i->modulo),
        ];
    }

    /**
     * FASE 1 — POST /api/roadmap/validacion/aprobar — Irving: "✓ Funciona correctamente".
     * validado_por_irving=true, pendiente=false, completado + archivado. Conserva TODO el historial.
     */
    public function validacionAprobar(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = RoadmapItem::find($data['id']);
        if (! $item || ! $item->pendiente_validacion_irving) {
            return response()->json(['error' => 'Este item no está esperando validación.'], 422);
        }

        $item->validado_por_irving = true;
        $item->pendiente_validacion_irving = false;
        $item->validado_at = now();
        $item->validado_por = $this->actor();
        $item->estado_aprobacion = 'completado';   // el guard #420 sincroniza status=done + completed_at
        $item->archivado_at = now();
        $item->archivado_por = 'validacion-irving';
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => $this->actor(), 'evento' => 'validacion_funcional_ok', 'merge_commit' => $item->merge_commit];
        $item->log = $log;
        $item->save();

        Log::channel('roadmap_externo')->info('validacion-ok', ['item' => $item->id, 'por' => $this->actor()]);

        return response()->json(['ok' => true, 'id' => $item->id, 'estado' => 'completado']);
    }

    /**
     * FASE 1 — POST /api/roadmap/validacion/reportar — Irving: "⚠ Reportar problema".
     * NO revierte (auto-revert = FASE 3). Guarda el comentario y manda a revisión técnica.
     * Conserva rama, commits, merge_commit, historial y evidencia.
     */
    public function validacionReportar(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate([
            'id'         => ['required', 'integer', 'min:1'],
            'comentario' => ['required', 'string', 'max:2000'],
        ]);
        $item = RoadmapItem::find($data['id']);
        if (! $item || ! $item->pendiente_validacion_irving) {
            return response()->json(['error' => 'Este item no está esperando validación.'], 422);
        }

        $item->pendiente_validacion_irving = false;
        $item->validado_por_irving = false;
        $item->revision_tecnica = true;                 // equivalente a requiere_revision_tecnica (sin tocar el enum)
        $item->comentario_validacion = $data['comentario'];
        $item->estado_aprobacion = 'requiere_irving';   // vuelve a revisión humana; NO se revierte código (FASE 3)
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => $this->actor(), 'evento' => 'validacion_problema_reportado',
            'comentario' => $data['comentario'], 'merge_commit' => $item->merge_commit,
            'nota' => 'NO se revirtió (FASE 3 pendiente). Rama/commits/merge_commit/historial conservados.'];
        $item->log = $log;
        $item->save();

        // Loop de aprendizaje del perfil (#351/#545): un "Reportar problema" es señal de aprendizaje
        // tan real como un rechazo en bandeja. No crítico: nunca debe tumbar el reporte real.
        app(\App\Modules\Addons\Roadmap\Services\PerfilAprendizajeService::class)
            ->capturarProblema($item, $data['comentario']);

        Log::channel('roadmap_externo')->info('validacion-problema', ['item' => $item->id, 'por' => $this->actor()]);

        return response()->json(['ok' => true, 'id' => $item->id, 'estado' => 'revision_tecnica']);
    }

    /**
     * [BUG][UI/UX][TORRE] Ubicación ACTUAL real del item para Actividad reciente: mapea la `estacion`
     * (accessor, precedencia done>terminal>bandeja>integracion>listo>intake) + los flags nuevos a una
     * etiqueta legible, la pestaña destino de la Torre (/releases) y la siguiente acción. No usa la
     * estación histórica del evento — lee el estado vivo del item.
     */
    private function ubicacionActual(RoadmapItem $i): array
    {
        $sig = $i->siguiente_accion ?: null;   // columna explícita si el circuito la fijó; si no, se deriva

        switch ($i->estacion) {
            case 'bandeja':
                $tag = (bool) $i->bloqueado_por_bucle ? ' (Bloqueado por bucle)'
                    : ((bool) $i->requiere_sesion_supervisada ? ' (Sesión supervisada)' : '');
                return ['label' => '⚑ Tu Bandeja' . $tag, 'icon' => '⚑', 'tab' => 'panorama',
                    'siguiente_accion' => $sig ?: 'Requiere tu decisión'];
            case 'integracion':
                $tag = (bool) $i->esperando_merge_irving ? ' (esperando tu merge)' : '';
                return ['label' => '🔍 Integración' . $tag, 'icon' => '🔍', 'tab' => 'integracion',
                    'siguiente_accion' => $sig ?: 'Revisar y mergear la rama'];
            case 'terminal':
                return ['label' => '🛠 En desarrollo', 'icon' => '🛠', 'tab' => 'terminales',
                    'siguiente_accion' => $sig ?: 'En ejecución por un worker'];
            case 'done':
                return ['label' => '📦 Historial', 'icon' => '📦', 'tab' => 'historial',
                    'siguiente_accion' => $sig ?: 'Completado — ver detalle'];
            case 'listo':
                return ['label' => '📋 Hoja de ruta (en cola)', 'icon' => '📋', 'tab' => 'roadmap',
                    'siguiente_accion' => $sig ?: 'En cola de ejecución'];
            default: // intake
                return ['label' => '📋 Hoja de ruta', 'icon' => '📋', 'tab' => 'roadmap',
                    'siguiente_accion' => $sig ?: 'Triaje pendiente'];
        }
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
