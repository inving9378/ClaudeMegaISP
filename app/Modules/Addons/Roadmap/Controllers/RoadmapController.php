<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\CircuitoEjecucion;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\AutopilotService;
use App\Modules\Addons\Roadmap\Services\FronterasService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use App\Modules\Addons\Roadmap\Services\SessionTreeService;
use App\Modules\Addons\Roadmap\Services\SupervisorService;
use App\Modules\Addons\Roadmap\Services\JarvisService;
use App\Modules\Addons\Roadmap\Support\TorreControlCatalog;
use App\Modules\Addons\Roadmap\Services\WatchdogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Symfony\Component\Process\Process;

class RoadmapController extends Controller
{
    // #878 — `roadmap_items` tiene 96 columnas (22 TEXT/JSON, algunas de varios KB por fila:
    // `comentarios_claude`/`log`/`prompt`/`preguntas` suman >4 MB entre las 594 filas de dev).
    // `ORDER BY ... LIMIT N` con `SELECT *` sobre esa fila ancha revienta MySQL con
    // "Out of sort memory" (1038, `sort_buffer_size` de dev). Fix quirúrgico (opción 1
    // pre-aprobada): listas de columnas EXPLÍCITAS — solo lo que cada respuesta realmente usa —
    // en vez de `SELECT *`, para las 3 consultas ordenadas de la Torre/Hoja de ruta.
    private const COLUMNAS_BANDEJA = [
        'id', 'title', 'modulo', 'description', 'status', 'priority', 'urgente', 'nivel_riesgo',
        'estado_aprobacion', 'opcion_elegida', 'en_desarrollo_humano', 'esperando_merge_irving',
        'archivado_at', 'origen_bloqueo', 'motivo_bloqueo', 'branch', 'worker_sid', 'origen_item_id',
        'consulta_supervisor_at', 'consulta_resuelta_at', 'comentarios_claude', 'opciones',
        'preguntas', 'reporte_coloquial', 'enlace_revision', 'alcance_autorizado', 'fuera_de_alcance',
        'prompt', 'reanudaciones_timeout', 'frontera_valvula',
    ];

    private const COLUMNAS_ACTIVIDAD = [
        'id', 'title', 'nivel_riesgo', 'estado_aprobacion', 'status', 'archivado_at',
        'en_desarrollo_humano', 'esperando_merge_irving', 'origen_bloqueo', 'opcion_elegida',
        'branch', 'siguiente_accion', 'bloqueado_por_bucle', 'requiere_sesion_supervisada',
        'aprobado_por', 'comentarios_claude', 'revisado_at', 'updated_at',
    ];

    private const COLUMNAS_LISTADO = [
        'id', 'title', 'modulo', 'status', 'priority', 'urgente', 'nivel_riesgo',
        'estado_aprobacion', 'target_version', 'eta_minutos', 'eta_asignada_at',
        'automatizacion_override', 'subtasks', 'prompt', 'position', 'worker_sid', 'branch',
        'created_at', 'updated_at',
        // #675 (Pieza 4) — el veredicto de la válvula de nacimiento (`mencion`/`accion`/null) ya se
        // pintaba como badge en "Tu bandeja" (TorreControl.vue, desde el commit 96cf38f2) pero
        // desaparecía al pasar el item a "Hoja de ruta": un item ejecutado vía válvula-mención se
        // veía IGUAL que uno que nunca tocó la frontera. Columna varchar(16) indexada, no TEXT — no
        // reintroduce el problema de sort-memory de #878 (prompt, mucho más pesado, ya está arriba).
        'frontera_valvula',
    ];

    // #890 (Torre fase 6) — MISMA lista que `RoadmapItem::COLUMNAS_COMPACT` (la usa `$this->svc->
    // compact()` + sus accesors `estacion`/`estado_cola`/`tieneConsultaViva()`: si a una fila le
    // faltara una de esas columnas, el accessor la leería como `null` y calcularía mal, en
    // silencio) + `colision_pausada_por`/`position` (los lee `RoadmapItem::criteriosOrdenCola()`
    // para `explicarOrdenCola()`) + `eta_minutos` (lo pinta la tarjeta). Mismo motivo que las listas
    // de arriba: proyección explícita, nunca `SELECT *` en una consulta ORDENADA.
    private const COLUMNAS_COLA = [
        'id', 'title', 'modulo', 'status', 'priority', 'urgente', 'nivel_riesgo', 'estado_aprobacion',
        'worker_sid', 'origen_item_id', 'branch', 'archivado_at', 'en_desarrollo_humano',
        'esperando_merge_irving', 'origen_bloqueo', 'opcion_elegida',
        'consulta_supervisor_at', 'consulta_resuelta_at',
        'colision_pausada_por', 'position', 'eta_minutos',
    ];

    // #880 — Épica #874 Fase 2 ("por qué no avanza"): solo lo que `porQueNoAvanza()` y el mapeo de
    // abajo leen. Mismo motivo que las listas de arriba (#878/#864): `ORDER BY ... LIMIT` con
    // `SELECT *` sobre la fila ancha revienta "Out of sort memory".
    private const COLUMNAS_NO_AVANZA = [
        'id', 'title', 'modulo', 'nivel_riesgo', 'estado_aprobacion', 'status',
        'bloqueado_por_bucle', 'motivo_bloqueo', 'consulta_supervisor', 'consulta_supervisor_at',
        'consulta_resuelta_at', 'colision_pausada_por', 'esperando_merge_irving', 'reap_count',
        'en_desarrollo_humano', 'worker_sid', 'updated_at',
    ];

    /**
     * #878 — BLOQUES QUE FALLARON en la respuesta que se está armando. Se vacía por petición.
     * @var array<string,string>
     */
    private array $bloquesFallidos = [];

    /**
     * #878 — UN BLOQUE DE LA TORRE, aislado.
     *
     * `torre()` arma ~20 bloques independientes en una sola respuesta. Sin aislar, la excepción de
     * UNO tumbaba los veinte: eso fue exactamente lo que pasó — la consulta de la bandeja reventó
     * con 1038 durante 20 días y la pantalla entera se quedó muda, mostrando ceros que se leían
     * como "no hay trabajo" en vez de "no pude preguntar".
     *
     * El fallback NO es un cero disfrazado: el nombre del bloque viaja en `bloques_fallidos`, y el
     * front está obligado a distinguir "0" de "falló" (si no lo hace, vuelve el silencio).
     */
    private function bloque(string $nombre, callable $fn, mixed $fallback = null): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $this->bloquesFallidos[$nombre] = $e->getMessage();
            Log::error("torre(): el bloque «{$nombre}» falló y se devolvió vacío.", [
                'bloque'    => $nombre,
                'excepcion' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

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
    /**
     * ENTREGA 1 — GET la configuración vigente de la Torre + la matriz ya resuelta.
     *
     * ⚠️ **La UI nunca decide autorización.** Esto sólo MUESTRA lo que el servidor ya resolvió. Si
     * la decisión viviera en Vue, bastaría abrir DevTools para autoaprobarse un item nivel C.
     */
    public function torreConfig(): JsonResponse
    {
        $this->authorize('torre.config.view');

        $policy = app(\App\Modules\Addons\Roadmap\Services\TorreAutomationPolicy::class);

        return response()->json([
            'ok'          => true,
            'politica'    => $policy->panorama(),
            'motores'     => app(\App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService::class)->latidos(),
            'puede_editar' => auth()->user()?->can('torre.config.edit') ?? false,
            'guardrails'  => self::GUARDRAILS,
            // #943 — catálogo completo por actor (~70 controles del inventario), en sus 3
            // cubetas. Mientras #881 (catálogo de acciones) no aterrice, ningún control nuevo se
            // pinta editable aunque el plan lo clasifique como candidato verde — ver
            // `TorreControlCatalog::YA_EDITABLES`.
            'grupos_actor' => TorreControlCatalog::grupos(),
        ]);
    }

    /**
     * ENTREGA 1 — POST guarda la configuración. Exige `torre.config.edit`.
     *
     * Los rangos se validan AQUÍ, en el servidor. Los guardrails no tienen endpoint: no aparecen en
     * las reglas porque no hay forma de mandarlos.
     */
    public function torreConfigGuardar(Request $request): JsonResponse
    {
        $this->authorize('torre.config.edit');

        $data = $request->validate([
            'nivel_automatizacion'    => ['sometimes', 'string', Rule::in(\App\Modules\Addons\Roadmap\Models\TorreConfig::NIVELES)],
            'auditor_activo'          => ['sometimes', 'boolean'],
            'auditor_max_por_corrida' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'auditor_cooldown_min'    => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'auditor_slots_libres_min' => ['sometimes', 'integer', 'min:0', 'max:6'],
        ]);

        $diff = app(\App\Modules\Addons\Roadmap\Services\TorreConfigService::class)
            ->update($data, auth()->user());

        return response()->json([
            'ok'       => true,
            'cambios'  => $diff,
            'politica' => app(\App\Modules\Addons\Roadmap\Services\TorreAutomationPolicy::class)->panorama(),
        ]);
    }

    /**
     * #890 (Torre fase 6) — GET la cola ejecutable REAL (solo lectura): el orden exacto en que
     * `RoadmapCircuitoService::ejecutablesParalelo()` va a tomar el trabajo (`RoadmapItem::
     * despachable()->ordenCola()`, la MISMA consulta que usa el reclamo — no una copia), la frase
     * que lo explica (`explicarOrdenCola()`, generada del criterio real) y los aprobados que NO se
     * despachan, cada uno con su causa (`motivoNoDespachable()`).
     *
     * No toca el despacho ni el reparto: sólo los LEE. `torre.cola.ver` — informacion interna del
     * circuito, no de negocio, restringida a super-administrator + DESARROLLADOR (ver la migración).
     */
    public function torreCola(): JsonResponse
    {
        $this->authorize('torre.cola.ver');

        $this->bloquesFallidos = [];

        $cola = $this->bloque('cola_real', fn () => RoadmapItem::query()
            ->despachable()->ordenCola()->limit(50)->get(self::COLUMNAS_COLA), collect());

        $frase = $this->bloque('cola_real_frase', fn () => RoadmapItem::explicarOrdenCola($cola), '');

        // Excluidos = items que YA pasaron por una aprobación (irving/claude/revisor, o A en revisión)
        // pero que hoy NO califican en `despachable()` — el "¿por qué no está el mío?" real. La causa
        // sale de `motivoNoDespachable()`, la MISMA traducción que usa el guard de re-aprobación: no
        // hay una segunda lista de frenos que mantener sincronizada con ésta.
        $excluidos = $this->bloque('cola_excluidos', function () {
            $despachablesIds = RoadmapItem::query()->despachable()->pluck('id');

            $candidatosIds = RoadmapItem::query()
                ->whereNull('archivado_at')
                ->whereNotIn('status', ['done'])
                ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
                ->where(function ($w) {
                    $w->whereIn('estado_aprobacion', ['aprobado_irving', 'aprobado_claude', 'aprobado_revisor'])
                      ->orWhere(fn ($x) => $x->where('nivel_riesgo', 'A')->where('estado_aprobacion', 'pendiente_revision'));
                })
                ->whereNotIn('id', $despachablesIds)
                ->orderBy('id')
                ->limit(50)
                ->pluck('id');

            // #878 — misma cautela que `hidratarEnOrden`: ids primero (proyección angosta), fila
            // completa DESPUÉS sin `ORDER BY` (aquí `motivoNoDespachable()` necesita el modelo entero).
            return RoadmapItem::query()->whereIn('id', $candidatosIds)->get()
                ->sortBy('id')->values()
                ->map(function (RoadmapItem $i) {
                    $motivo = $i->motivoNoDespachable();

                    return [
                        'id'                => $i->id,
                        'title'             => $i->title,
                        'modulo'            => $i->modulo,
                        'nivel_riesgo'      => $i->nivel_riesgo,
                        'estado_aprobacion' => $i->estado_aprobacion,
                        'causa'             => $motivo['error'] ?? 'No calificó para el despacho automático.',
                        'accion'            => $motivo['accion'] ?? null,
                    ];
                });
        }, collect());

        return response()->json([
            'ok'             => true,
            'reparto_activo' => ! $this->svc->isPaused(),
            'cola'           => $cola->map(fn (RoadmapItem $i) => $this->svc->compact($i))->values(),
            'orden_frase'    => $frase,
            'excluidos'      => $excluidos->values(),
            'bloques_fallidos' => $this->bloquesFallidos,
        ]);
    }

    /**
     * #937 — Tablero "Items atorados" agrupado por CAUSA en Panorama. Depende de #935
     * (DiagnosticoItemService::para()), que aún no existe: mientras no exista, responde
     * `disponible=false` en vez de inventar una causa con lógica propia — la causa de un item
     * SOLO puede venir de ese mismo servicio (mismo criterio que la ficha, #936), para que ficha
     * y tablero nunca diverjan. El día que #935 se implemente, esta agrupación se activa sola.
     *
     * Cache corto (20s) + polling del front cada 30s (decisión registrada del item, opción B).
     */
    public function atorados(): JsonResponse
    {
        $this->authorize('roadmap_view');

        $servicio = \App\Modules\Addons\Roadmap\Services\DiagnosticoItemService::class;
        if (! class_exists($servicio)) {
            return response()->json([
                'ok'         => true,
                'disponible' => false,
                'motivo'     => 'Depende de #935 (DiagnosticoItemService), aún no implementado.',
                'grupos'     => [],
            ]);
        }

        $grupos = Cache::remember('roadmap_atorados_v1', 20, function () use ($servicio) {
            $items = RoadmapItem::query()
                ->whereIn('estado_aprobacion', ['aprobado_irving', 'requiere_irving'])
                ->get();

            $porCausa = $items->map(function (RoadmapItem $i) use ($servicio) {
                $diag = $servicio::para($i);
                $causa = is_array($diag) ? ($diag['causa'] ?? 'sin_causa') : ($diag->causa ?? 'sin_causa');
                return ['item' => $i, 'causa' => $causa ?: 'sin_causa'];
            })->groupBy('causa');

            return $porCausa->map(function ($fila, $causa) {
                $itemsGrupo = $fila->pluck('item');
                $masViejo = $itemsGrupo->sortBy(fn (RoadmapItem $i) => $i->claimed_at ?? $i->created_at)->first();
                return [
                    'causa'           => $causa,
                    'conteo'          => $itemsGrupo->count(),
                    'mas_viejo_id'    => $masViejo?->id,
                    'mas_viejo_desde' => $masViejo?->claimed_at ?? $masViejo?->created_at,
                    'items_ids'       => $itemsGrupo->pluck('id')->values(),
                ];
            })->values();
        });

        return response()->json([
            'ok'         => true,
            'disponible' => true,
            'grupos'     => $grupos,
        ]);
    }

    /**
     * ENTREGA 1 — override de automatización de UN item.
     *
     * Bajar (`manual`) no pide nada. **Subir (`auto`) exige confirmación explícita** —el front manda
     * `confirmado=true`— y queda registrado con usuario y fecha. El override es de un solo uso: se
     * consume al despachar.
     */
    public function itemOverride(Request $request, int $id): JsonResponse
    {
        $this->authorize('circuito.decidir');

        $data = $request->validate([
            'override'   => ['required', 'string', Rule::in(['hereda', 'manual', 'auto'])],
            'confirmado' => ['sometimes', 'boolean'],
        ]);

        $item = RoadmapItem::findOrFail($id);
        $previo = (string) ($item->automatizacion_override ?? 'hereda');

        $orden = ['manual' => 0, 'hereda' => 1, 'auto' => 2];
        $sube  = ($orden[$data['override']] ?? 1) > ($orden[$previo] ?? 1);

        if ($sube && ! ($data['confirmado'] ?? false)) {
            return response()->json([
                'ok'                     => false,
                'requiere_confirmacion'  => true,
                'mensaje'                => 'Subir la automatización de un item lo saca de la política base. '
                    . 'Los cuatro topes duros (producción · borrar datos · dinero · credenciales) siguen '
                    . 'vigentes y no se levantan con esto. El override se consume la primera vez que una '
                    . 'terminal toma el item.',
            ], 409);
        }

        $item->automatizacion_override = $data['override'];

        $log   = $item->log ?: [];
        $log[] = [
            'ts'         => now()->toIso8601String(),
            'por'        => 'irving:' . (auth()->user()?->login_user ?? auth()->id()),
            'estado'     => $item->estado_aprobacion,
            'decision'   => 'override_automatizacion',
            'comentario' => "Automatización del item: {$previo} → {$data['override']}"
                . ($sube ? ' (SUBIDA, confirmada explícitamente)' : ''),
        ];
        $item->log = $log;
        $item->save();

        Log::channel('torre_config')->{$sube ? 'warning' : 'info'}('override-item', [
            'item' => $item->id, 'de' => $previo, 'a' => $data['override'],
            'por'  => auth()->user()?->login_user ?? auth()->id(),
        ]);

        return response()->json(['ok' => true, 'override' => $item->automatizacion_override, 'subida' => $sube]);
    }

    /**
     * Los guardrails que el panel pinta con candado. **No existe endpoint que los modifique**: viven
     * aquí como texto porque un panel web capaz de apagar la separación dev/prod sería un control
     * remoto para apagarla.
     */
    private const GUARDRAILS = [
        ['icono' => '🔒', 'texto' => 'Solo ejecuta en dev · 192.168.105.11',                'donde' => 'fijo en código'],
        ['icono' => '🔒', 'texto' => 'Prod bloqueado · 192.168.105.108 · v1megaisp.com.mx', 'donde' => 'fijo en código'],
        ['icono' => '🔒', 'texto' => 'migrate:fresh prohibido',                             'donde' => 'fijo en código'],
        ['icono' => '🔒', 'texto' => 'git add -A prohibido',                                'donde' => 'fijo en código'],
        // #648 — ESTA LÍNEA DECÍA «no configurable» Y DEJÓ DE SER CIERTA. Los topes duros ahora se
        // gobiernan desde la pestaña «Configuración» → Fronteras (encender/apagar categorías, editar
        // sus términos, elegir su efecto), por decisión explícita de Irving. Lo que SIGUE sin tener
        // interruptor es la DETECCIÓN: es determinista y no la decide ningún modelo. Un guardrail
        // que promete un candado que ya no existe es peor que no listarlo.
        ['icono' => '🔓', 'texto' => 'Topes duros (producción · borrar datos · dinero · credenciales): la DETECCIÓN es determinista y no se apaga; la lista y su efecto SÍ se gobiernan desde Configuración → Fronteras, con bitácora de cada cambio', 'donde' => 'circuito_fronteras (antes: fijo en JarvisService)'],
        ['icono' => '🔒', 'texto' => 'Vía externa (Cowork/MCP): solo nivel A puede quedar aprobado_claude', 'donde' => 'guard() — sin endpoint'],
        // #943 — completa la cubeta roja de `plan-configuracion-torre.md` §1 (antes faltaban
        // estos 2 de los 6 ahí listados).
        ['icono' => '🔒', 'texto' => 'jarvis.automerge.rutas_sensibles / patrones_destructivos: lo que NUNCA se auto-mergea', 'donde' => 'config/circuito.php'],
        ['icono' => '🔒', 'texto' => 'Tokens y llaves (ROADMAP_*_TOKEN, CLAUDE_API_KEY, AMI_SECRET…) — se listan por nombre, nunca su valor', 'donde' => '.env'],
    ];

    /**
     * GET /api/roadmap/torre/historial-acciones — FASE 8 (#885, Épica #874): "quién hizo qué botón,
     * cuándo y con qué resultado" en la Torre. Depende de la Fase 3 (#881, catálogo de acciones), que
     * al día de hoy sigue sin validar — pero el patrón de auditoría que #881 iba a formalizar YA
     * existe de facto: ~15 endpoints de este controller (decidir/deshacer-decision/override/urgente/
     * cancelar-disparo/archivar/…) escriben cada acción en `roadmap_items.log` (json append-only,
     * {ts, por, decision|evento, estado, comentario|motivo}). Esta pantalla SOLO LEE ese log ya
     * existente — no crea tabla ni permiso nuevos: reusa `roadmap_view`, el mismo gate de `torre()`,
     * porque es una vista de solo lectura equivalente (ver la bandeja ya expone quién decidió qué).
     *
     * Scan acotado (no hay índice sobre el json): trae como mucho 300 items más recientes tocados
     * (o el item pedido si viene `item_id`), aplana sus entradas de log y ordena por fecha. A escala
     * dev (torre() completo ya mide 121ms con 100 items) esto es holgado; si el volumen crece habrá
     * que mover el log a una tabla propia — no es este paso.
     *
     * Query en DOS pasos a propósito: `ORDER BY updated_at` filesort combinado con la columna `log`
     * (json, hay filas con historiales grandes) revienta el sort buffer de MySQL («Out of sort
     * memory») — medido en dev, y pasa igual con un simple `WHERE log IS NOT NULL` en la MISMA query
     * que el ORDER BY, aunque `log` ni se seleccione (el optimizer igual la toca para evaluar el
     * WHERE). Paso 1 ordena por `updated_at` SIN tocar `log` en absoluto (sin WHERE sobre esa
     * columna); paso 2 trae `log` para esos IDs sin ORDER BY (sin filesort). Los items sin log o con
     * log vacío simplemente no aportan filas al aplanar — no hace falta filtrarlos en SQL.
     */
    /**
     * GET /api/roadmap/torre/salud-entorno (#891) — Fase 7 de la Épica #874: los seis indicadores
     * que hoy solo se ven entrando por SSH (certificado TLS, disco, migraciones pendientes, jobs
     * fallidos, último respaldo, errores 24h agrupados por firma). Solo lectura — cacheado 30s
     * (mismo patrón que `decisionesContadores`) para no repetir la lectura de logs/disco en cada
     * poll de la Torre.
     */
    public function saludEntorno(\App\Modules\Addons\Roadmap\Services\EnvironmentHealthService $salud): JsonResponse
    {
        $this->authorize('roadmap_view');

        $data = Cache::remember('roadmap:torre:salud-entorno', 30, fn () => $salud->resumen());

        // `puede_gestionar` NO se cachea con el resto (el resumen es compartido entre usuarios
        // por 30s; el permiso del usuario actual no lo es) — mismo patrón que `can_disparar` en
        // el endpoint de estado del circuito.
        return response()->json(['ok' => true, 'puede_gestionar' => (bool) auth()->user()?->can('torre.salud.manage')] + $data);
    }

    /**
     * POST /api/roadmap/torre/salud/reintentar-fallidos (#891) — botón declarado en el item:
     * reintenta TODOS los jobs de `failed_jobs` (`queue:retry all`). Gate `torre.salud.manage`
     * (solo super-administrator + DESARROLLADOR — ejecuta un comando real, no es lectura).
     */
    public function saludReintentarFallidos(\App\Modules\Addons\Roadmap\Services\EnvironmentHealthService $salud): JsonResponse
    {
        $this->authorize('torre.salud.manage');

        $r = $salud->reintentarTrabajosFallidos();
        Cache::forget('roadmap:torre:salud-entorno');

        return response()->json($r);
    }

    /**
     * POST /api/roadmap/torre/salud/recalentar-caches (#891) — botón declarado en el item:
     * view:clear + config:clear + route:clear + view:cache siempre; config:cache SOLO si
     * `incluir_config=true` Y `config:auditar-env` pasa limpio (#790/#794) — nunca por default.
     */
    public function saludRecalentarCaches(Request $request, \App\Modules\Addons\Roadmap\Services\EnvironmentHealthService $salud): JsonResponse
    {
        $this->authorize('torre.salud.manage');

        $data = $request->validate(['incluir_config' => ['sometimes', 'boolean']]);

        $r = $salud->recalentarCaches((bool) ($data['incluir_config'] ?? false));
        Cache::forget('roadmap:torre:salud-entorno');

        return response()->json($r);
    }

    /**
     * GET /api/roadmap/torre/semaforo (#946, Fase 1b hija de #875) — pestaña "Semáforo" de la
     * Torre: una fila por motor con icono 🟢/🟡/🔴/⚫, última corrida EXITOSA en lenguaje humano y
     * la frase de qué se pierde si está caído. Solo lectura, mismo permiso que Panorama
     * (`roadmap_view`, decisión de Irving en el brief del item — reutiliza el permiso existente en
     * vez de crear uno nuevo). Cacheado 5s (no 30s como salud-entorno): la pestaña hace polling
     * cada 5-10s (decisión del item) y aquí el costo real es leer `settings`, barato de repetir.
     */
    public function torreSemaforo(): JsonResponse
    {
        $this->authorize('roadmap_view');

        $data = Cache::remember('roadmap:torre:semaforo', 5, fn () => $this->svc->semaforoMotores());

        return response()->json(['ok' => true, 'generado_at' => now()->toIso8601String(), 'motores' => $data]);
    }

    /**
     * #947 (Fase 1c) — "Ver último error" de un motor: mensaje completo + cuántas veces se repitió
     * seguido. Sin cache propio (se consulta al toque, on-demand, no en cada poll del semáforo).
     */
    public function torreSemaforoFallo(Request $request): JsonResponse
    {
        $this->authorize('roadmap_view');

        $data = $request->validate(['comando' => ['required', 'string', 'max:80']]);

        return response()->json($this->svc->detalleFallo($data['comando']));
    }

    /**
     * GET /api/roadmap/torre/frontera-dura (#766, Pieza 1c hija de #672) — la KPI card «Frontera
     * dura» del dashboard: total de aperturas de la válvula + últimos 7 días + desglose por
     * categoría + listado detallado (item/término/categoría/veredicto/razón/cuándo/ejecutado).
     * Lee `torre_frontera_dura_eventos` (Pieza 1a, #764) vía {@see FronterasService::resumenTorreFronteraDura()}
     * — solo lectura, mismo gate que el resto del panorama de la Torre.
     */
    public function torreFronteraDura(FronterasService $fronteras): JsonResponse
    {
        $this->authorize('roadmap_view');

        $data = Cache::remember('roadmap:torre:frontera-dura', 30, fn () => $fronteras->resumenTorreFronteraDura());

        return response()->json(['ok' => true] + $data);
    }

    public function historialAcciones(Request $request): JsonResponse
    {
        $this->authorize('roadmap_view');

        $data = $request->validate([
            'item_id' => ['sometimes', 'integer', 'min:1'],
            'por'     => ['sometimes', 'nullable', 'string', 'max:64'],
            'limit'   => ['sometimes', 'integer', 'min:1', 'max:300'],
        ]);

        $limit = $data['limit'] ?? 100;

        if (! empty($data['item_id'])) {
            $items = RoadmapItem::query()->where('id', $data['item_id'])->get(['id', 'title', 'log']);
        } else {
            $ids   = RoadmapItem::query()->orderByDesc('updated_at')->limit(300)->pluck('id');
            $items = RoadmapItem::query()->whereIn('id', $ids)->get(['id', 'title', 'log']);
        }

        $acciones = [];
        foreach ($items as $item) {
            foreach ((array) $item->log as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $ts = $entry['ts'] ?? $entry['created_at'] ?? null;
                if (! $ts) {
                    continue;
                }
                $por = $entry['por'] ?? $entry['autor'] ?? null;
                if (! empty($data['por']) && stripos((string) $por, $data['por']) === false) {
                    continue;
                }
                $acciones[] = [
                    'item_id'    => $item->id,
                    'item_title' => $item->title,
                    'ts'         => $ts,
                    'por'        => $por,
                    'accion'     => $entry['decision'] ?? $entry['evento'] ?? 'evento',
                    'estado'     => $entry['estado'] ?? null,
                    'detalle'    => $entry['comentario'] ?? $entry['motivo'] ?? $entry['nota'] ?? null,
                ];
            }
        }

        usort($acciones, fn ($a, $b) => strcmp((string) $b['ts'], (string) $a['ts']));

        return response()->json([
            'ok'                     => true,
            'acciones'               => array_slice($acciones, 0, $limit),
            'total_items_escaneados' => $items->count(),
        ]);
    }

    public function torre(): JsonResponse
    {
        $this->authorize('roadmap_view');

        $this->bloquesFallidos = [];

        // #432 — la bandeja es TODA la estación de decisión (requiere_irving + C sin decidir +
        // [BLOCKED-/PARKED-]), no solo requiere_irving: el supervisor las enruta aquí y ninguna
        // decisión se queda perdida en la Hoja de ruta.
        // #507 sub-paso 4 — límite 20 → 100: con las bombitas por módulo al lado, una lista truncada
        // a 20 contra un contador de 71 se lee como un bug, y filtrar por módulo sobre una lista
        // recortada devolvía menos items de los que anuncia su bombita. Medido en dev: traer los 71
        // cuesta 16 ms y `torre()` completo 121 ms. Si algún día la bandeja pasa de 100, la UI avisa
        // que está mostrando N de M en vez de mentir.
        $cola = $this->bloque('bandeja', fn () => RoadmapItem::bandeja()
            ->ordered()->limit(100)->get(self::COLUMNAS_BANDEJA)
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
                // FASE 2A.3 — el freno deja de vivir en el título, así que la Torre necesita
                // pintarlo. Sin esto, limpiar los rótulos volvería el freno INVISIBLE: el badge
                // tiene que existir ANTES de quitar el texto del título.
                'origen_bloqueo'   => $i->origen_bloqueo,   // humano (FRENA) | clasificador (informa)
                'motivo_bloqueo'   => $i->motivo_bloqueo,
                // §5 — señal de producción. No bloquea; se ve.
                'toca_produccion'  => $i->tocaProduccion(),
                // Un item en su 2ª reanudación no es sólo un freno: es INFORMACIÓN — significa que
                // es más grande que una vuelta. Por eso se ve, en vez de vivir sólo en el log.
                'reanudaciones'    => (int) $i->reanudaciones_timeout,
                // Qué camino tomó en la puerta de nacimiento: `mencion` = la válvula lo despejó
                // (nació pendiente_revision, NUNCA aprobado_irving); `accion` = confirmó que toca.
                'frontera_valvula' => $i->frontera_valvula,
                // DISCREPANCIA nivel declarado vs calculado. Se computa del texto (que ya viene en
                // COLUMNAS_BANDEJA) y no del `log`: meter esa columna JSON en una consulta ORDENADA
                // es exactamente lo que reventaba con 1038. Un item que se declara A y salió C es la
                // señal de que el clasificador pudo equivocarse — se muestra, no se obedece.
                'nivel_declarado'  => ($d = app(\App\Modules\Addons\Roadmap\Services\RevisorService::class)
                    ->nivelDeclarado((string) $i->title . "\n" . (string) $i->description . "\n" . (string) $i->prompt)),
                'discrepancia_nivel' => $d !== null && $d !== $i->nivel_riesgo,
            ])), collect());

        // #348: cola EJECUTABLE — SOLO lo que el circuito AUTO-CORRE (A/B o ya aprobado por Irving),
        // NO los C/requiere_irving/negocio (esos esperan tu decisión y jamás los corre solo).
        // Ya ordenada 🔥→prioridad→antigüedad; aquí el 🔥 salta la fila y dispara vuelta.
        // #878 — `compact()` sólo lee campos ligeros: proyección explícita, nunca `SELECT *`
        // sobre una consulta ordenada (ver COLUMNAS_BANDEJA arriba).
        $colaEjecutable = $this->bloque('cola_ejecutable', fn () => RoadmapItem::autoEjecutable()
            ->ordered()->limit(25)->get(RoadmapItem::COLUMNAS_COMPACT)
            ->map(fn (RoadmapItem $i) => $this->svc->compact($i)), collect());

        // #348: resumen de la cola — cuántos auto-corre el circuito vs cuántos esperan tu decisión
        // (+ los sin clasificar que el circuito aún triará). Cuentan sobre TODA la cola, no el limit.
        // #878 — el fallback es `null` por contador, NO `0`: un cero aquí se lee como "no hay
        // trabajo" y es justo la mentira que ocultó el 1038 durante 20 días.
        $resumenCola = $this->bloque('resumen_cola', fn () => [
            'auto_ejecutables' => RoadmapItem::autoEjecutable()->count(),
            'espera_decision'  => RoadmapItem::bandeja()->count(),      // #432: toda la estación bandeja
            'sin_clasificar'   => RoadmapItem::backlog()->count(),       // #432: intake = lo que vive en la Hoja de ruta
            'intake'           => RoadmapItem::backlog()->count(),
        ], ['auto_ejecutables' => null, 'espera_decision' => null, 'sin_clasificar' => null, 'intake' => null]);

        // #958 (Fase 3 de #921) — "N items agendados" con su LISTA (título + fecha), para que dejen
        // de vivir invisibles en la BD. Usa `scopeAgendados()` (el mismo predicado que ya excluye
        // estos items del pool automático, ver comentario en `sqlElegibleParaPool` arriba) — un solo
        // punto de verdad de qué cuenta como "agendado". Fallback `count: null` (no `0`, por la misma
        // razón que `resumen_cola`: un cero aquí se leería como "nada agendado" cuando en realidad
        // no se pudo calcular).
        $agendados = $this->bloque('agendados', fn () => [
            'count' => RoadmapItem::agendados()->count(),
            'items' => RoadmapItem::agendados()->orderBy('agendado_para')->limit(50)
                ->get(['id', 'title', 'agendado_para'])
                ->map(fn (RoadmapItem $i) => [
                    'id'            => $i->id,
                    'title'         => $i->title,
                    'agendado_para' => optional($i->agendado_para)->toIso8601String(),
                ]),
        ], ['count' => null, 'items' => collect()]);

        // [BUG][UI/UX][TORRE] Cada evento de Actividad reciente se enriquece con la UBICACIÓN ACTUAL
        // REAL del item (no la del evento): status + estacion calculada (accessor) + etiqueta legible +
        // pestaña destino + siguiente acción. Así la tarjeta puede navegar a donde el item está AHORA.
        $actividad = $this->bloque('actividad_reciente', fn () => RoadmapItem::whereNotNull('comentarios_claude')
            ->orderByRaw('COALESCE(revisado_at, updated_at) DESC')
            ->limit(8)->get(self::COLUMNAS_ACTIVIDAD)
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
            }), collect());

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

        $ejecuciones = $this->bloque('ejecuciones', fn () => CircuitoEjecucion::orderByDesc('id')->limit(12)->get()
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
            ]), collect());

        // #880 — Épica #874 Fase 2: "por qué no avanza este item" — union de TODAS las señales
        // reales de estancamiento (bucle, consulta viva, colisión, merge pendiente, reap huérfano,
        // o el estancamiento por tiempo que antes vivía solo/mudo en 'estancados', #346). Umbral
        // fijo por ahora (política de N días queda para cuando Irving decida la regla dura/consejo;
        // esto es solo diagnóstico de LECTURA — Fase 2 no inventa mecanismos de destrabe nuevos).
        $noAvanza = $this->bloque('no_avanza', fn () => RoadmapItem::noAvanza(10)
            ->orderBy('updated_at')->limit(20)->get(self::COLUMNAS_NO_AVANZA)
            ->map(fn (RoadmapItem $i) => [
                'id'                => $i->id,
                'title'             => $i->title,
                'modulo'            => $i->modulo,
                'nivel_riesgo'      => $i->nivel_riesgo,
                'estado_aprobacion' => $i->estado_aprobacion,
                'worker_sid'        => $i->worker_sid,
                'updated_at'        => optional($i->updated_at)->toIso8601String(),
                'porque_no_avanza'  => $i->porQueNoAvanza(),
            ]), collect());

        $ultima = CircuitoEjecucion::orderByDesc('id')->first();

        return response()->json([
            'generated_at'         => now()->toIso8601String(),
            'circuito_pausado'     => $this->svc->isPaused(),
            // #343: salvaguarda de "pausa olvidada" — null si no está pausado.
            'circuito_pausado_info' => $this->svc->pausedInfo(),
            'circuito_modo'        => $this->svc->getModo(),
            'resumen'              => $this->bloque('resumen', fn () => $this->svc->resumen(), null),
            'cola_requiere_irving' => $cola,
            'cola_ejecutable'      => $colaEjecutable,   // #348: SOLO auto-ejecutables (A/B o aprobados) con 🔥
            'resumen_cola'         => $resumenCola,      // #348: N auto-ejecutables · M esperan tu decisión
            'agendados'            => $agendados,        // #958: N items agendados + su lista (título/fecha)
            'actividad_reciente'   => $actividad,
            // FASE 1 — "Cambios para que Irving pruebe": cambios seguros integrados esperando validación funcional.
            'cambios_validacion'   => $this->bloque('cambios_validacion', fn () => RoadmapItem::pendienteValidacion()->limit(30)->get()
                ->map(fn (RoadmapItem $i) => $this->validacionPayload($i)), collect()),
            'riesgos_auditoria'    => $riesgos,
            'auditoria_item_id'    => $audit?->id,
            'ejecuciones'          => $ejecuciones,
            // Estado EN VIVO de la vuelta (#335): corriendo/inactivo + heartbeat + próxima.
            'live'                 => $this->bloque('live', fn () => $this->svc->liveState(), null),
            // Visor "Trabajando ahora" (#349): sesiones (array listo-para-N, 1 hoy) con fases
            // y stepper + resumen de la última vuelta (CIRCUITO_META).
            'trabajando'           => $this->bloque('trabajando', fn () => $this->svc->trabajandoAhora(), collect()),
            'proxima_vuelta_at'    => $this->svc->proximaVueltaAt(),
            'ultima_vuelta_at'     => optional($ultima?->started_at)->toIso8601String(),
            'circuito_intervalo_min' => (int) config('circuito.interval_min', 30),
            // Pool continuo (#334): latido del scheduler → "cron vivo" aunque esté ocioso por falta de
            // trabajo seguro (evita el falso "cron detenido"). + cuántos auto-ejecutables hay en cola.
            'scheduler_beat_secs'  => $this->svc->schedulerBeatSecs(),
            'cron_vivo'            => ($s = $this->svc->schedulerBeatSecs()) !== null && $s < 180,
            'auto_ejecutables'     => $this->bloque('auto_ejecutables', fn () => RoadmapItem::autoEjecutable()->count(), null),
            // #507 sub-paso 4 — banner del autopilot: política vigente + qué decidió hoy.
            'autopilot'            => $this->bloque('autopilot', fn () => $this->autopilot->resumen(), null),
            // #791 — foto del último `circuito:digest` (mudas 7d / prod 24h / fallback), con la
            // referencia del "antes" para leer la tendencia sin repetir el barrido a mano.
            'digest'               => $this->bloque('digest', fn () => $this->svc->digestSnapshot(), null),
            // Watchdog del equipo (#334): salud por slot + alertas escaladas + bitácora de recuperación.
            'watchdog'             => $this->watchdog->estado(),
            'watchdog_bitacora'    => $this->watchdog->bitacora(15),
            // #880: reemplaza el antiguo 'estancados' (solo tiempo, nunca consumido por el front) —
            // ahora trae TODAS las señales de estancamiento + la frase legible de cada una.
            'no_avanza'            => $noAvanza,
            'no_avanza_count'      => RoadmapItem::noAvanza(10)->count(),
            'worker_nombres'       => $this->svc->nombresWorkers(),   // roster editable (#334)
            'supervisor'           => $this->supervisor->estado(),    // Jarvis T: jefe + su feed (#334)
            'can_disparar'         => (bool) auth()->user()?->can('circuito.disparar'),
            'voz_tts'              => $this->svc->getVozTts(),   // #424: voz guardada para 🔊 Escuchar (bandeja + Integración usan la misma)
            'rate_tts'             => $this->svc->getRateTts(),  // #424: velocidad guardada
            // #878 — CONTRATO CON EL FRONT: qué bloques no se pudieron calcular en ESTA respuesta.
            // Vacío = la foto está completa. Con entradas = esos números NO son datos, son huecos,
            // y la UI debe decir «no pude preguntar» en vez de pintar un cero.
            'bloques_fallidos'     => $this->bloquesFallidos,
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
            // #938: límite real de una vuelta — la Torre lo usa para el reloj de cada terminal
            // contra el límite (no un número inventado en el frontend).
            'vuelta_limite_segundos' => (int) config('circuito.vuelta_timeout_seg', 600),
            // Watchdog del equipo (#334): salud por slot + alertas para el polling en vivo.
            'watchdog'          => $this->watchdog->estado(),
            'supervisor'        => $this->supervisor->estado(),   // Jarvis T + su feed (#334)
            'can_disparar'      => (bool) auth()->user()?->can('circuito.disparar'),
            // #854: gate del ícono de cámara (subir avatar) — mismo payload del poll, sin llamada nueva.
            'puede_editar_avatar' => (bool) auth()->user()?->can('torre.terminales.editar_avatar'),
        ]);
    }

    /**
     * GET /api/roadmap/torre/decisiones-automaticas — LO QUE LA MÁQUINA DECIDIÓ POR TI.
     *
     * La contraparte de dejar que el circuito decida solo: no una espera previa, sino la
     * reversibilidad posterior. Una lista corta y legible en diez segundos —qué se decidió, sobre
     * qué item, por qué y hace cuánto— con el estado actual del item para saber si el deshacer
     * todavía es barato (aún no lo toma una terminal) o ya tiene trabajo encima.
     */
    public function decisionesAutomaticas(Request $request): JsonResponse
    {
        $this->authorize('roadmap_view');

        $limite = min(50, max(1, (int) $request->query('limit', 20)));

        $q = RoadmapItem::query()
            ->whereNotNull('aprobado_por')
            ->whereNull('archivado_at')
            ->where(function ($w) {
                // Definición ÚNICA en el modelo, junto al candado que la aplica (#878): si la
                // lista se bifurcara, esta pantalla y el guard dejarían de hablar del mismo grupo.
                foreach (RoadmapItem::ACTORES_AUTOMATICOS as $a) {
                    $w->orWhere('aprobado_por', 'like', $a . '%');
                }
            })
            ->orderByRaw('COALESCE(revisado_at, updated_at) DESC');

        // #878 — `log` es JSON: ordenar con `SELECT *` sobre esta tabla revienta MySQL (1038).
        // hidratarEnOrden() ordena sobre `id` y trae las filas anchas sin ORDER BY.
        $items = RoadmapItem::hidratarEnOrden($q, $limite);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'decisiones'   => $items->map(function (RoadmapItem $i) {
                $entrada = $this->ultimaEntradaAutomatica($i);
                $enCurso = ! empty($i->worker_sid) || ! empty($i->branch)
                    || $i->estado_aprobacion === 'en_progreso' || $i->status === 'in_progress';

                return [
                    'item_id'           => $i->id,
                    'title'             => $i->title,
                    'modulo'            => $i->modulo,
                    'nivel_riesgo'      => $i->nivel_riesgo,
                    'decidio'           => $i->aprobado_por,
                    'estado_aprobacion' => $i->estado_aprobacion,
                    'estacion'          => $i->estacion,
                    // Frase lista para leer: "decidí X sobre el #N porque Y".
                    'que_decidio'       => $entrada['decision'] ?? 'aprobar',
                    // Cada actor firma distinto: el autopilot escribe `motivo`, el revisor `razon`
                    // y el des-trabador sólo una `categoria`. Se toma el primero que exista en vez
                    // de exigirles un formato común — unificarlo es otro item, y mientras tanto la
                    // lista tiene que ser legible con lo que hay.
                    'porque'            => $entrada['motivo']
                        ?? $entrada['razon']
                        ?? (isset($entrada['categoria'])
                            ? 'Clasificado como «' . str_replace('_', ' ', (string) $entrada['categoria']) . '».'
                            : ($i->comentarios_claude
                                ? mb_strimwidth((string) $i->comentarios_claude, 0, 160, '…')
                                : 'Sin motivo registrado.')),
                    'confianza'         => $entrada['confianza'] ?? null,
                    'reversible'        => $entrada['reversible'] ?? null,
                    'cuando'            => optional($i->revisado_at ?? $i->updated_at)->toIso8601String(),
                    // ¿El deshacer todavía es barato?
                    'trabajo_en_curso'  => $enCurso,
                    'terminal'          => $i->worker_sid,
                    'rama'              => $i->branch,
                    // Ya deshecha antes: no ofrecer el botón otra vez.
                    'ya_deshecha'       => $this->tieneEventoLog($i, 'decision_automatica_deshecha'),
                    'puede_deshacer'    => ! $this->tieneEventoLog($i, 'decision_automatica_deshecha')
                        && $i->estado_aprobacion !== 'requiere_irving',
                ];
            })->values(),
        ]);
    }

    /** Última entrada del `log` firmada por un actor automático (o [] si no hay). */
    private function ultimaEntradaAutomatica(RoadmapItem $i): array
    {
        $encontrada = [];
        foreach ((array) ($i->log ?? []) as $e) {
            if (! is_array($e)) {
                continue;
            }
            $por = (string) ($e['decidido_por'] ?? $e['por'] ?? '');
            if ($por !== '' && RoadmapItem::firmaAutomatica($por)) {
                $encontrada = $e;   // sin break: nos quedamos con la MÁS RECIENTE
            }
        }

        return $encontrada;
    }

    private function tieneEventoLog(RoadmapItem $i, string $evento): bool
    {
        foreach ((array) ($i->log ?? []) as $e) {
            if (is_array($e) && ($e['evento'] ?? null) === $evento) {
                return true;
            }
        }

        return false;
    }

    /**
     * POST /api/roadmap/items/{id}/deshacer-decision — DESHACER lo que la máquina decidió.
     *
     * Devuelve el item a la bandeja de Irving y borra las respuestas que el actor automático
     * eligió por él. Sale del pool (`excluir_pool_automatico`) a propósito: sin eso, el mismo
     * autopilot que acaba de decidir volvería a decidir lo mismo en la siguiente vuelta y el
     * deshacer se desharía solo.
     *
     * Si una terminal YA está trabajando el item, el deshacer deja de ser gratis (hay rama o
     * trabajo en curso): se exige `confirmado` explícito en vez de resolverlo por su cuenta.
     */
    public function deshacerDecision(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        if (! RoadmapItem::firmaAutomatica((string) $item->aprobado_por)) {
            return response()->json([
                'message' => 'Este item no lo decidió un actor automático (lo firmó "'
                    . ($item->aprobado_por ?: 'nadie') . '"): no hay decisión automática que deshacer.',
            ], 422);
        }

        $enCurso = ! empty($item->worker_sid) || ! empty($item->branch)
            || $item->estado_aprobacion === 'en_progreso' || $item->status === 'in_progress';

        if ($enCurso && ! $request->boolean('confirmado')) {
            return response()->json([
                'requiere_confirmacion' => true,
                'mensaje' => 'Una terminal ya está trabajando este item'
                    . ($item->worker_sid ? " ({$item->worker_sid})" : '')
                    . ($item->branch ? " en la rama {$item->branch}" : '')
                    . '. Deshacer la decisión lo devuelve a tu bandeja, pero NO borra el trabajo ya '
                    . 'hecho ni la rama: eso lo decides tú aparte.',
            ], 409);
        }

        $estadoPrevio = $item->estado_aprobacion;

        // Borra las respuestas que eligió el actor automático (responderPregunta con null limpia).
        foreach ($item->preguntasNormalizadas() as $p) {
            $item->responderPregunta((string) $p['id'], null);
        }
        $item->opcion_elegida = null;

        $item->estado_aprobacion       = 'requiere_irving';
        $item->aprobado_por            = $this->actorLabel();
        $item->revisado_at             = now();
        $item->decision_resuelta       = false;
        $item->excluir_pool_automatico = true;

        $log = $item->log ?: [];
        $log[] = [
            'ts'             => now()->toIso8601String(),
            'por'            => $this->actorLabel(),
            'evento'         => 'decision_automatica_deshecha',
            'decidio_antes'  => $estadoPrevio,
            'estado'         => 'requiere_irving',
            'trabajo_en_curso' => $enCurso,
            'motivo'         => 'Irving deshizo la decisión automática desde la Torre.',
        ];
        $item->log = $log;
        $item->save();

        return response()->json([
            'ok'      => true,
            'item_id' => $item->id,
            'mensaje' => "Decisión deshecha: el #{$item->id} vuelve a tu bandeja y sale del pool "
                . 'automático hasta que lo decidas.'
                . ($enCurso ? ' Ojo: el trabajo que la terminal ya hizo sigue ahí.' : ''),
            'estado_aprobacion' => $item->estado_aprobacion,
        ]);
    }

    /**
     * POST /api/roadmap/items/{id}/liberar-reclamo — suelta el `worker_sid` de un item SIN tocar
     * su `estado_aprobacion` (#889, Torre fase 5 — Terminales).
     *
     * Caso real: un item termina (`status=done`) pero queda en `requiere_irving` (o cualquier
     * estado no terminal) esperando la decisión de Irving — el `worker_sid` que lo reclamó nunca
     * se limpia porque no hay más trabajo que hacer, y esa terminal queda "ocupada" por un item
     * que ya no se está ejecutando. Este botón SOLO libera la reserva de la terminal; el
     * `estado_aprobacion` del item queda exactamente igual (ni aprueba, ni rechaza, ni ejecuta).
     */
    public function liberarReclamo(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        if (empty($item->worker_sid)) {
            return response()->json([
                'message' => 'Este item no tiene ninguna terminal reclamándolo.',
            ], 422);
        }

        $sidLiberado = $item->worker_sid;
        $item->worker_sid = null;
        $item->claimed_at = null;   // el lease muere junto con el reclamo que lo sostenía

        $log = $item->log ?: [];
        $log[] = [
            'ts'       => now()->toIso8601String(),
            'por'      => $this->actorLabel(),
            'evento'   => 'reclamo_liberado',
            'terminal_liberada' => $sidLiberado,
            'estado_aprobacion' => $item->estado_aprobacion,   // sin cambio — solo deja rastro
            'motivo'   => 'Liberado desde la Torre (pestaña Terminales) — reclamo huérfano.',
        ];
        $item->log = $log;
        $item->save();

        return response()->json([
            'ok'      => true,
            'item_id' => $item->id,
            'mensaje' => "Reclamo liberado: la terminal {$sidLiberado} queda libre. El estado del item ({$item->estado_aprobacion}) no cambió.",
        ]);
    }

    /**
     * POST /api/roadmap/items/{id}/reasignar-reclamo — mueve el `worker_sid` de un item de UNA
     * terminal a OTRA (#972, hermano directo de `liberarReclamo` — Torre fase 5, Terminales).
     *
     * Mismo caso de `liberarReclamo` (reclamo huérfano: item ya `status=done` esperando la
     * decisión de Irving, reteniendo una terminal) pero en vez de soltar la reserva, la MUEVE a
     * otra terminal que esté libre ahora mismo. Misma frontera: SOLO toca `worker_sid`/`claimed_at`
     * — nunca `estado_aprobacion` (no aprueba, rechaza ni ejecuta nada).
     */
    public function reasignarReclamo(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $item = RoadmapItem::findOrFail($id);

        if (empty($item->worker_sid)) {
            return response()->json([
                'message' => 'Este item no tiene ninguna terminal reclamándolo.',
            ], 422);
        }

        $sidDestino = trim((string) $request->input('sid_destino'));
        if ($sidDestino === '') {
            return response()->json(['message' => 'Falta indicar la terminal destino.'], 422);
        }
        if ($sidDestino === $item->worker_sid) {
            return response()->json(['message' => 'Ya es esa misma terminal.'], 422);
        }

        // La terminal destino debe estar libre: que ningún OTRO item la tenga reclamada ahora.
        $ocupada = RoadmapItem::query()
            ->where('worker_sid', $sidDestino)
            ->where('id', '!=', $item->id)
            ->exists();
        if ($ocupada) {
            return response()->json([
                'message' => "La terminal {$sidDestino} ya está reclamada por otro item.",
            ], 422);
        }

        $sidAnterior = $item->worker_sid;
        $item->worker_sid = $sidDestino;
        // claimed_at se conserva: sigue siendo el mismo reclamo, solo cambia de terminal física.

        $log = $item->log ?: [];
        $log[] = [
            'ts'                => now()->toIso8601String(),
            'por'               => $this->actorLabel(),
            'evento'            => 'reclamo_reasignado',
            'terminal_anterior' => $sidAnterior,
            'terminal_nueva'    => $sidDestino,
            'estado_aprobacion' => $item->estado_aprobacion,   // sin cambio — solo deja rastro
            'motivo'            => 'Reasignado desde la Torre (pestaña Terminales) — reclamo huérfano.',
        ];
        $item->log = $log;
        $item->save();

        return response()->json([
            'ok'      => true,
            'item_id' => $item->id,
            'mensaje' => "Reclamo movido: de {$sidAnterior} a {$sidDestino}. El estado del item ({$item->estado_aprobacion}) no cambió.",
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

    /**
     * POST /api/roadmap/items/{id}/cancelar-disparo (#863) — ventana de deshacer de 15s en el
     * toast del aviso cuando un item nace `aprobado_irving` (entró directo a la cola, ver #566).
     * Gate `circuito.disparar` — mismo permiso que disparar/urgente (ya es "acción que decide si
     * el circuito ejecuta"), evita inventar un permiso nuevo para una acción hermana.
     *
     * ALCANCE (decisión registrada — ver `comentarios_claude` del item, preguntas q1-q3): solo
     * revierte mientras el scheduler NO haya reclamado el item (`worker_sid` sigue null). Si ya
     * quedó `en_progreso` con una terminal asignada, deshacerlo pelearía con el reclamo atómico
     * anti-colisión (#341) y el flock de `SchedulerCommand` — más riesgoso que el problema que
     * resuelve, así que NO se implementa: se informa que ya no se puede deshacer.
     */
    public function cancelarDisparo(int $id): JsonResponse
    {
        $this->authorize('circuito.disparar');

        $item = RoadmapItem::find($id);
        if (! $item) {
            return response()->json(['error' => 'Item no encontrado.'], 404);
        }

        $segundos = $item->created_at ? now()->diffInSeconds($item->created_at) : 999;
        if ($segundos > 15) {
            return response()->json(['error' => 'La ventana de deshacer (15s) ya expiró.'], 422);
        }

        if (! empty($item->worker_sid)) {
            return response()->json(['error' => 'Ya se lanzó a una terminal; no se puede deshacer.'], 409);
        }

        if ($item->estado_aprobacion !== 'aprobado_irving') {
            return response()->json(['error' => 'Este item ya no está en la cola de despacho.'], 422);
        }

        // Vuelve exactamente al estado de "recién creado, sin triar" (#456): estado_aprobacion Y
        // nivel_riesgo en null juntos, para que quede fuera de TODAS las vías de auto-reclamo del
        // scheduler (incluida la de nivel_riesgo='A'+pendiente_revision) y pase por triaje normal.
        $item->estado_aprobacion = 'pendiente_revision';
        $item->nivel_riesgo      = null;
        $item->aprobado_por      = null;
        $item->revisado_at       = null;
        $item->log = array_merge($item->log ?? [], [[
            'ts'     => now()->toIso8601String(),
            'por'    => $this->actorLabel(),
            'evento' => 'disparo_cancelado_undo',
            'via'    => 'torre',
        ]]);
        $item->save();

        try {
            $this->svc->cancelDisparoPendiente($item->id);
        } catch (\Throwable $e) {
            Log::warning('circuito.undo_disparo.limpieza_fallo', ['item_id' => $item->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'item'    => $item,
            'mensaje' => 'Deshecho: el item volvió a la bandeja de revisión, sin ejecutar.',
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

        // #507 / FASE 2A.3 — DESTRABE EXPLÍCITO. `forzar=true` limpia TODO el parqueo y devuelve el
        // item al pool. Antes, junto a esto vivía un guard que ENUMERABA los frenos
        // (`esperando_merge_irving || bloqueado_por_bucle`) para decidir si responder 422; esa lista
        // se quedó corta —no incluía el master switch `excluir_pool_automatico`— y aprobar un item
        // bloqueado por él respondía 200 sin moverlo. La detección ahora es una POST-CONDICIÓN
        // contra la condición real de despacho (al final del método): cubre cualquier freno,
        // presente o futuro, sin que nadie tenga que acordarse de actualizar una lista.
        $frenoQuitado = null;
        if ($data['accion'] === 'aprobar' && ($data['forzar'] ?? false)) {
            $frenoQuitado = [
                'origen_bloqueo' => $item->origen_bloqueo,
                'motivo'         => $item->motivo_bloqueo,
                'titulo_previo'  => $item->title,
            ];

            $item->esperando_merge_irving   = false;
            $item->bloqueado_por_bucle      = false;
            $item->excluir_pool_automatico  = false;
            $item->escalaciones_fingerprint = null;
            // FASE 2A.3 — el destrabe levanta también el freno HUMANO en columna...
            $item->origen_bloqueo           = null;
            $item->motivo_bloqueo           = 'destrabe-forzado-irving';
            // ...y el rótulo del título, que es el fallback legacy. Sin esto, «Quitar el freno y
            // aprobar» dejaría el item igual de frenado y el 422 volvería en el siguiente intento:
            // el guard mira AMBAS fuentes.
            $item->title = trim(preg_replace('/\[(BLOCKED|PARKED)-[^\]]*\]\s*/i', '', (string) $item->title));
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
            // FASE 2A.3 — qué freno se levantó y qué decía el título antes. El hook de 2A.4 traza
            // las banderas, pero no el título: sin esto, «Quitar el freno y aprobar» borraría el
            // rótulo sin dejar constancia de que existió.
            'freno_quitado'  => $frenoQuitado,
        ];
        $log[] = $entrada;
        $item->log = $log;
        $item->save();

        // Loop de aprendizaje del perfil (#351): captura la decisión como candidato crudo en
        // storage/app/circuito/pendientes-perfil-irving.md para revisión batch. No crítico: nunca debe tumbar la
        // decisión real de Irving si falla.
        app(\App\Modules\Addons\Roadmap\Services\PerfilAprendizajeService::class)->capturar($item, $entrada);

        Log::channel('roadmap_externo')->info('decision-irving', [
            'item'   => $item->id,
            'por'    => $autor,
            'accion' => $data['accion'],
            'estado' => $nuevoEstado,
        ]);

        // FASE 2A.3 — POST-CONDICIÓN: aprobar es una petición de DESPACHO, así que se verifica el
        // resultado, no la intención. La pregunta no es "¿tenía alguna de estas banderas?" sino
        // "¿quedó reclamable?", contra la MISMA condición que usa el scheduler
        // (`RoadmapItem::scopeDespachable`). Así cualquier freno futuro queda cubierto sin tocar
        // este código: la lista enumerada es precisamente lo que dejó pasar 941 decisiones mudas
        // (#186 acumuló 32 aprobaciones que devolvieron 200 y no movieron nada).
        //
        // La decisión SÍ queda escrita (ya se guardó arriba): el 422 no la revierte, informa que no
        // alcanza y cuál es la acción que de verdad mueve el item. El front ya pinta `data.error`
        // en el catch y conserva la selección para reintentar — el `aviso` del 200 que había antes
        // ni siquiera se leía en la UI.
        if ($data['accion'] === 'aprobar') {
            $bloqueo = $item->motivoNoDespachable();
            if ($bloqueo !== null) {
                Log::channel('roadmap_externo')->info('decision-sin-efecto', [
                    'item' => $item->id, 'por' => $autor, 'code' => $bloqueo['code'],
                ]);

                return response()->json([
                    'ok'             => false,
                    'code'           => $bloqueo['code'],
                    'error'          => $bloqueo['error'],
                    'accion_sugerida' => $bloqueo['accion'],
                    // FASE 2A.3 §3 — le dice a la Torre que este freno lo puso una persona y que
                    // quien ya tiene `circuito.decidir` puede levantarlo aquí mismo (reenviando con
                    // `forzar`), en vez de mandarlo a editar el título a mano.
                    'desbloqueable'   => (bool) ($bloqueo['desbloqueable'] ?? false),
                    'decision_registrada' => true,
                    'motivo_bloqueo' => $item->motivo_bloqueo,
                    'item'           => [
                        'id'                => $item->id,
                        'estado_aprobacion' => $item->estado_aprobacion,
                    ],
                ], 422);
            }
        }

        return response()->json([
            'ok'    => true,
            'aviso' => null,
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
            'diagnostico'        => $this->diagnosticoDe($i), // #982 — ver diagnosticoDe()
            'created_at'         => optional($i->created_at)->toIso8601String(),
            'updated_at'         => optional($i->updated_at)->toIso8601String(),
            'started_at'         => optional($i->started_at)->toIso8601String(),
            'completed_at'       => optional($i->completed_at)->toIso8601String(),
        ];
    }

    /**
     * #982 — `diagnostico` = {causa, explicacion, accion, procedencia} de
     * `DiagnosticoItemService::para($item)` (#935), para la FICHA (un solo item).
     *
     * El servicio aún no existe (#980/#981 siguen sin implementar): mientras no exista, este
     * método devuelve `null` sin romper nada — mismo guard `class_exists`+`method_exists` que ya
     * usa `atorados()` (#937), para que ficha y tablero compartan un único criterio de "¿existe el
     * servicio?" y se activen solos el día que #980/#981 aterricen. El front (`RoadmapItemDetalle
     * .vue`, #936) ya sabe pintar `item.diagnostico` cuando no es null.
     */
    private function diagnosticoDe(RoadmapItem $i): ?array
    {
        $servicio = \App\Modules\Addons\Roadmap\Services\DiagnosticoItemService::class;
        if (! class_exists($servicio) || ! method_exists($servicio, 'para')) {
            return null;
        }

        $diag = $servicio::para($i);

        return is_array($diag) ? $diag : (is_object($diag) ? (array) $diag : null);
    }

    /**
     * #982 — mapa `id => diagnostico` para TODOS los items de `$items` en UNA sola pasada BATCH
     * (`DiagnosticoItemService::paraLote()`), para la LISTA — evita disparar N consultas al armar
     * el tablero (mismo espíritu que `motivoNoDespachable($esDespachable)` ya preparado en #935:
     * quien puede calcular en lote lo hace una vez y reparte el resultado por item).
     *
     * Mismo guard que `diagnosticoDe()`: sin el servicio, devuelve `[]` (cada item cae a `null`).
     */
    private function diagnosticosLote(\Illuminate\Support\Collection $items): array
    {
        $servicio = \App\Modules\Addons\Roadmap\Services\DiagnosticoItemService::class;
        if ($items->isEmpty() || ! class_exists($servicio) || ! method_exists($servicio, 'paraLote')) {
            return [];
        }

        $lote = $servicio::paraLote($items);

        return is_array($lote) ? $lote : (is_iterable($lote) ? collect($lote)->all() : []);
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
            'frontera_control'  => $this->fronteraControlBadge($i),   // #675 (Pieza 4 de #646): control verificado vs autodeclaración
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
            // El TEXTO del diff ya no viaja aquí: lo sirve `integracionDiff()` cuando el visor lo
            // abre. `tiene_diff` es lo único que la lista necesita para decidir si ofrece el botón.
            'tiene_diff'        => $git['existe'] && ! empty($git['archivos']),
        ];
    }

    /**
     * #675 (Pieza 4 de #646) — LA TORRE DISTINGUE CONTROL VERIFICADO DE AUTODECLARACIÓN.
     *
     * `frontera_valvula` (columna ya sellada, hecho histórico) SOLO se llena al nacer el item si
     * el detector determinista disparó (ver `store()`: se asigna únicamente dentro del `else` de
     * `$frontera === null`). Cruzarlo con una RELECTURA en vivo del mismo detector
     * (`JarvisService::fronteraDuraDeItemDetalle()`, la misma fuente que `categoriaFronteraDura()`,
     * anclada a palabra, sin modelo) da las 3 combinaciones que pidió el item, más la anomalía de
     * un disparo sin sello (item de antes de que la válvula existiera, o categoría hoy en «avisar»):
     *
     *   sin_frontera       → el detector no encuentra nada: no hubo nada que autodeclarar.
     *   mencion            → SÍ disparó; la válvula (autodeclaración del modelo) lo dejó pasar/ablandó.
     *   accion             → SÍ disparó; la válvula NO lo abrió — pasó por Irving, sin atajo del modelo.
     *   avisar / disparo_sin_sello → dispara HOY pero el item no tiene veredicto de válvula guardado.
     *
     * Solo lectura: no cambia ningún flujo de decisión, es únicamente lo que la Torre muestra.
     *
     * @return array{estado:string, label:string, detalle:string}
     */
    private function fronteraControlBadge(RoadmapItem $i): array
    {
        $det = app(JarvisService::class)->fronteraDuraDeItemDetalle($i);

        if ($det['categoria_detectada'] === null) {
            return [
                'estado'  => 'sin_frontera',
                'label'   => 'Sin frontera',
                'detalle' => 'El detector determinista (DetectorTerminos, sin modelo) no encuentra ningún término de frontera dura en este item: no hubo nada que autodeclarar.',
            ];
        }

        $termino = $det['termino'] ? "«{$det['termino']}» ({$det['categoria_detectada']})" : $det['categoria_detectada'];

        if ($i->frontera_valvula === 'mencion') {
            return [
                'estado'  => 'mencion',
                'label'   => 'Disparó · autodeclaración',
                'detalle' => "El detector determinista encontró {$termino}; la válvula de nacimiento lo leyó como MENCIÓN (el modelo se autodeclaró reversible) y lo dejó avanzar por el camino normal.",
            ];
        }

        if ($i->frontera_valvula === 'accion') {
            return [
                'estado'  => 'accion',
                'label'   => 'Disparó · control verificado',
                'detalle' => "El detector determinista encontró {$termino}; la válvula confirmó que SÍ toca la frontera y lo retuvo — pasó por la decisión de Irving, sin atajo del modelo.",
            ];
        }

        if ($det['efecto'] === 'avisar') {
            return [
                'estado'  => 'avisar',
                'label'   => 'Disparó · solo avisar',
                'detalle' => "El detector determinista encontró {$termino}, pero esa categoría está configurada en modo «solo avisar»: se registra y no retiene a nadie.",
            ];
        }

        return [
            'estado'  => 'disparo_sin_sello',
            'label'   => 'Disparó · sin veredicto de válvula',
            'detalle' => "El detector determinista encuentra {$termino} en una relectura actual, pero este item no tiene un veredicto de válvula guardado (nació antes de que existiera, o no llegó a evaluarse).",
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

    /**
     * POST /api/roadmap/circuito/worker-avatar — sube/reemplaza el avatar de una terminal o del
     * supervisor (#854). Superficie de ataque (subida de archivos) tratada como tal:
     *  - Whitelist real por contenido (`getimagesize`, no extensión/Content-Type del navegador);
     *    un SVG o un archivo renombrado a `.jpg` que no es imagen fallan aquí (`getimagesize` los
     *    rechaza, no hace falta un caso especial para SVG).
     *  - Tamaño máximo de ENTRADA 512 KB (`max:512` en KB, regla de Laravel).
     *  - Re-encode SIEMPRE a webp vía Intervention/GD (elimina EXIF/metadatos/payload incrustado)
     *    + resize a 128×128 — nunca se persiste el archivo tal cual lo mandó el cliente.
     *  - Nombre de archivo generado por el servidor (UUID) — nunca el nombre del cliente.
     *  - Al reemplazar, borra el archivo anterior del disco.
     */
    public function workerAvatar(Request $request): JsonResponse
    {
        $this->authorize('torre.terminales.editar_avatar');
        $data = $request->validate([
            'sid'    => ['required', 'string', 'regex:/^(wt-\d+|supervisor)$/'],
            'avatar' => ['required', 'file', 'max:512', 'mimes:jpeg,png,webp'],
        ]);

        $file = $request->file('avatar');
        $info = @getimagesize($file->getRealPath());
        $mimesValidos = ['image/jpeg', 'image/png', 'image/webp'];
        if (! $info || ! in_array($info['mime'] ?? null, $mimesValidos, true)) {
            return response()->json(['error' => 'Archivo no válido: debe ser una imagen JPEG, PNG o WEBP real.'], 422);
        }

        $anterior = $this->svc->avatarWorker($data['sid']);

        $nombreArchivo = 'terminales/' . (string) Str::uuid() . '.webp';
        Storage::disk('public')->put($nombreArchivo, (string) Image::make($file->getRealPath())->fit(128, 128)->encode('webp', 82));

        $this->svc->setAvatarWorker($data['sid'], $nombreArchivo);

        if ($anterior && $anterior !== $nombreArchivo && Storage::disk('public')->exists($anterior)) {
            Storage::disk('public')->delete($anterior);
        }

        Log::channel('roadmap_externo')->info('worker-avatar', ['sid' => $data['sid'], 'por' => $this->actor()]);

        return response()->json(['ok' => true, 'sid' => $data['sid'], 'avatar_url' => $this->svc->avatarUrlWorker($data['sid'])]);
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

    /**
     * GET /api/roadmap/integracion/version-candidatos — #933 Fase 2: items integrados a main desde
     * el último tag (candidatos a entrar en la próxima versión), con su estado de marcado.
     */
    public function integracionVersionCandidatos(): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $items = $this->svc->itemsCandidatosVersion()->map(fn (RoadmapItem $i) => [
            'id' => $i->id,
            'title' => $i->title,
            'modulo' => $i->modulo,
            'branch' => $i->branch,
            'merge_commit' => $i->merge_commit,
            'marcado_version' => (bool) $i->marcado_version,
            'origen_item_id' => $i->origen_item_id,
        ])->values();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    /**
     * GET /api/roadmap/integracion/version-dependencias — #933 Fase 3: detector de dependencias/
     * colisiones de lo marcado ahora mismo, ANTES de construir la rama de versión (Fase 4, no
     * implementada aquí). Solo lectura.
     */
    public function integracionVersionDependencias(): JsonResponse
    {
        $this->authorize('circuito.decidir');

        return response()->json(['ok' => true, 'violaciones' => $this->svc->detectarDependenciasVersion()]);
    }

    /**
     * POST /api/roadmap/integracion/version-construir-rama — #966 Fase 4: construye la rama de
     * release por cherry-pick de lo marcado (`marcado_version=true`). Operación AISLADA e invocada
     * a demanda por Irving desde el modal de crear release; NO forma parte del pipeline de deploy
     * automático. `ignorar_avisos=true` permite continuar aunque `detectarDependenciasVersion()`
     * haya encontrado violaciones (bajo responsabilidad explícita de quien lo pide).
     */
    public function integracionVersionConstruirRama(Request $request): JsonResponse
    {
        $this->authorize('circuito.decidir');
        $data = $request->validate([
            'version'         => ['required', 'string', 'max:80'],
            'nombre_rama'     => ['nullable', 'string', 'max:120'],
            'ignorar_avisos'  => ['nullable', 'boolean'],
        ]);

        $version    = trim($data['version']);
        $nombreRama = trim((string) ($data['nombre_rama'] ?? ''));
        if ($nombreRama === '') {
            $nombreRama = 'release/' . preg_replace('/[^A-Za-z0-9_.\-]/', '-', $version);
        }

        $resultado = $this->svc->construirRamaVersion($nombreRama, $version, (bool) ($data['ignorar_avisos'] ?? false));

        Log::channel('roadmap_externo')->info('integracion-version-construir-rama', [
            'rama' => $nombreRama, 'version' => $version, 'ok' => $resultado['ok'] ?? false,
            'motivo' => $resultado['motivo'] ?? null, 'por' => $this->actor(),
        ]);

        return response()->json($resultado);
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
     *
     * #630 — si el item YA fue integrado (merge_commit no nulo), rechazarlo revierte el código real
     * en main con el MISMO `git revert -m1` del botón manual "Revertir" (integracionRevert), en vez
     * de solo limpiar metadata y dejar el código huérfano viviendo en main hasta un segundo clic
     * aparte (rama huérfana C2, jul-13, nunca mergeada — recuperada aquí). Aplica a ambas acciones:
     * "reciclar" también competiría con el próximo intento si el código viejo se queda. Falla-cerrado:
     * si el revert no se puede aplicar (árbol sucio/conflicto) NO se rechaza nada — se devuelve el
     * motivo para resolverlo a mano y reintentar.
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

        $mergeRevertido = null;
        $revertCommit   = null;
        if ($item->merge_commit) {
            $res = $this->revertirMergeCommit($item->merge_commit);
            if (! $res['ok']) {
                return response()->json(['error' => 'No se pudo revertir el merge ya integrado: ' . $res['error']], 409);
            }
            $mergeRevertido      = $item->merge_commit;
            $revertCommit        = $res['revert_commit'];
            $item->merge_commit  = null;
        }

        $item->comentarios_claude = (string) $item->comentarios_claude
            . "\n\n--- RECHAZADA ({$data['accion']}, " . now()->toDateTimeString() . ", {$por}) ---\n" . $comentario;
        $item->aprobado_por = $por;
        $item->revisado_at  = now();
        $log = $item->log ?: [];

        if ($mergeRevertido) {
            $log[] = ['ts' => now()->toIso8601String(), 'por' => $por, 'evento' => 'revert_merge',
                'revert_commit' => $revertCommit, 'merge_revertido' => $mergeRevertido,
                'motivo' => "rechazo ({$data['accion']})"];
        }

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

        Log::channel('roadmap_externo')->info('integracion-rechazo', ['item' => $item->id, 'accion' => $data['accion'], 'por' => $por, 'revert_commit' => $revertCommit]);

        return response()->json(['ok' => true, 'item' => ['id' => $item->id, 'estado_aprobacion' => $item->estado_aprobacion, 'accion' => $data['accion']], 'revert_commit' => $revertCommit]);
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

        $res = $this->revertirMergeCommit($item->merge_commit);
        if (! $res['ok']) {
            return response()->json(['error' => $res['error']], 409);
        }

        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => $this->actor(), 'evento' => 'revert_merge', 'revert_commit' => $res['revert_commit'], 'merge_revertido' => $item->merge_commit];
        $item->log = $log;
        $item->merge_commit = null;
        $item->estado_aprobacion = 'requiere_irving';
        $item->save();

        Log::channel('roadmap_externo')->info('integracion-revert', ['item' => $item->id, 'por' => $this->actor(), 'revert' => $res['revert_commit']]);

        return response()->json(['ok' => true, 'revert_commit' => $res['revert_commit']]);
    }

    /**
     * Revierte UN merge_commit en main con `git revert -m1` (mismo mecanismo para el botón manual
     * "Revertir" y para el rechazo automático de un item ya integrado, #630). Fail-closed: árbol
     * sucio o conflicto → aborta sin tocar nada y devuelve el motivo; nunca deja el revert a medias.
     *
     * @return array{ok: bool, revert_commit?: string, error?: string}
     */
    private function revertirMergeCommit(string $mergeCommit): array
    {
        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            return ['ok' => false, 'error' => 'El árbol de trabajo tiene cambios sin commitear'];
        }

        $this->git(['checkout', 'main']);
        $rev = $this->git(['revert', '--no-edit', '-m', '1', $mergeCommit]);
        if (! $rev->isSuccessful()) {
            $this->git(['revert', '--abort']);

            return ['ok' => false, 'error' => 'No se pudo revertir (conflicto): ' . $rev->getErrorOutput()];
        }

        return ['ok' => true, 'revert_commit' => trim($this->git(['rev-parse', 'HEAD'])->getOutput())];
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

        // #1003 — "Abrir y probar" debe abrir la pantalla REAL, no el ticket. Orden de fiabilidad:
        // 1) lo declaró el ejecutor al cerrar (enlace_revision, #432); 2) respaldo por módulo
        // (module_sidebar_config, ya alimentado desde module.json). Si ninguno resuelve, no hay
        // pantalla identificable: sin_ui=true, y el front debe dejar de ofrecer un botón que miente.
        $moduloUrl    = $this->moduloUrl($i->modulo);
        $declarado    = trim((string) $i->enlace_revision);
        $enlaceProbar = $declarado !== '' ? $declarado : $moduloUrl;

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
            'enlace_probar'      => $enlaceProbar,   // null → sin pantalla identificable (ver sin_ui)
            'sin_ui'             => $enlaceProbar === null,
            'modulo_url'         => $moduloUrl,
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
    /**
     * Diff de la rama de un item contra main (o del propio merge commit, si ya se integró).
     *
     * `$conDiff` — el TEXTO del diff sólo se calcula si alguien lo va a leer.
     *
     * Antes esto devolvía siempre el diff completo y `integracion()` lo pedía para **hasta 80
     * ramas** en cada carga de la pestaña: 80 `git diff` por request y hasta ~1,6 MB de JSON que
     * casi nadie miraba (el diff arranca colapsado). Ahora la lista pide sólo `--stat` y
     * `--name-only` —que es lo que necesita para clasificar `sin_contenido` y pintar los chips de
     * archivo— y el texto se sirve bajo demanda desde `integracionDiff()`, con un tope mucho más
     * alto: 20 000 caracteres cortaban un diff mediano justo cuando se quería revisar.
     */
    private function diffRama(RoadmapItem $i, bool $conDiff = false, int $tope = 20000): array
    {
        $vacio = ['existe' => false, 'stat' => '', 'archivos' => [], 'diff' => '', 'truncado' => false, 'bytes' => 0];

        if (! empty($i->merge_commit)) {
            if (! $this->git(['rev-parse', '--verify', $i->merge_commit])->isSuccessful()) {
                return $vacio;
            }
            $range = "{$i->merge_commit}^1..{$i->merge_commit}";
        } else {
            if (! $this->git(['rev-parse', '--verify', $i->branch])->isSuccessful()) {
                return $vacio;
            }
            $base  = trim($this->git(['merge-base', 'main', $i->branch])->getOutput());
            $range = "{$base}..{$i->branch}";
        }
        $stat  = trim($this->git(['diff', '--stat', $range])->getOutput());
        $files = array_values(array_filter(explode("\n", trim($this->git(['diff', '--name-only', $range])->getOutput()))));

        if (! $conDiff) {
            return ['existe' => true, 'stat' => $stat, 'archivos' => $files, 'diff' => '', 'truncado' => false, 'bytes' => 0];
        }

        $diff     = $this->git(['diff', $range])->getOutput();
        $bytes    = mb_strlen($diff);
        $truncado = $bytes > $tope;
        if ($truncado) {
            $diff = mb_substr($diff, 0, $tope);
        }

        return ['existe' => true, 'stat' => $stat, 'archivos' => $files,
            'diff' => $diff, 'truncado' => $truncado, 'bytes' => $bytes];
    }

    /**
     * GET /api/roadmap/integracion/diff?id=N — el diff COMPLETO de una rama, para el visor.
     *
     * Va aparte de `integracion()` a propósito: el visor lo pide cuando el usuario abre una rama,
     * no 80 veces por si acaso. Tope de 400 KB — por encima de eso ningún humano revisa en pantalla
     * y el navegador sufre; el flag `truncado` hace que el visor lo diga en vez de mentir.
     */
    public function integracionDiff(Request $request): JsonResponse
    {
        $this->authorize('roadmap_view');

        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = RoadmapItem::findOrFail($data['id']);

        abort_if(empty($item->branch), 404, 'El item no tiene rama.');

        $git = $this->diffRama($item, true, 400000);

        return response()->json([
            'ok'       => true,
            'id'       => $item->id,
            'branch'   => $item->branch,
            'existe'   => $git['existe'],
            'stat'     => $git['stat'],
            'archivos' => $git['archivos'],
            'diff'     => $git['diff'],
            'truncado' => $git['truncado'],
            'bytes'    => $git['bytes'],
        ]);
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

        // #878 — ver COLUMNAS_LISTADO arriba. El detalle completo (prompt largo, comentarios_claude,
        // log, etc.) sigue disponible sin restricción vía GET /api/roadmap/items/{id} (show()).
        $items = $q->get(self::COLUMNAS_LISTADO);

        // #982 — 'diagnostico' batch-computado (diagnosticosLote(), una sola pasada para los N
        // items visibles) adjuntado como atributo ad-hoc; no está en COLUMNAS_LISTADO porque no es
        // una columna de tabla. `setAttribute` en vez de mapear a array plano para no alterar el
        // formato de serialización del resto de columnas (fechas, casts, etc.).
        $diagnosticos = $this->diagnosticosLote($items);
        $items->each(fn (RoadmapItem $i) => $i->setAttribute('diagnostico', $diagnosticos[$i->id] ?? null));

        return response()->json($items);
    }

    // GET /api/roadmap/items/{id} — lectura puntual (#861: sondeo del desenlace de despacho
    // tras crear un item; `vista=backlog` lo saca de la lista en cuanto una terminal lo toma,
    // así que el sondeo necesita un fetch directo por id que no dependa de esa vista).
    public function show(int $id): JsonResponse
    {
        $this->authorize('roadmap_view');

        return response()->json(RoadmapItem::findOrFail($id));
    }

    // POST /api/roadmap/items
    public function store(Request $request): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'priority'        => 'nullable|in:alta,media,baja',
            'target_version'  => 'nullable|string|max:20',
            'prompt'          => 'nullable|string',
            'modulo'          => 'nullable|string|max:100',
            'nivel_riesgo'    => 'nullable|in:A,B,C',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        /*
         * #858 — doble clic (o un reintento de red) no debe crear dos items ni disparar dos
         * reclamos. Clave de idempotencia OPCIONAL generada por el cliente: si ya se usó en los
         * últimos 30s por este mismo actor, se devuelve el item ya creado en vez de duplicarlo.
         * Sin clave (llamadas viejas / API directa) el comportamiento es exactamente el de antes.
         */
        $idemKey = $data['idempotency_key'] ?? null;
        unset($data['idempotency_key']);
        $idemCacheKey = $idemKey ? 'roadmap:add-item:idem:' . $this->actorLabel() . ':' . $idemKey : null;
        if ($idemCacheKey && ($existingId = Cache::get($idemCacheKey))) {
            $existing = RoadmapItem::find($existingId);
            if ($existing) {
                return response()->json([
                    'item'        => $existing,
                    'aviso'       => 'Ya se había agregado (doble clic detectado) — no se creó otro.',
                    'idempotente' => true,
                ], 200);
            }
        }

        $data['status']   = 'pending';
        $data['position'] = RoadmapItem::where('status', 'pending')->max('position') + 1;

        // Si no se envían sub-tareas, sembrar las 5 por defecto
        if (! $request->has('subtasks')) {
            $data['subtasks'] = self::defaultSubtasks();
        }

        /*
         * #566 — CREAR ES APROBAR (solo por esta vía).
         *
         * Un item que Irving escribe a mano en la Torre nacía `pendiente_revision` y se quedaba
         * ahí hasta que alguien lo aprobara: él pedía el trabajo y después tenía que autorizarse
         * a sí mismo. Su creación YA es la aprobación, así que entra directo a la cola ejecutable
         * como `aprobado_irving` — el estado que el pool reconoce y que el tope del autopilot no
         * limita (la aprobación explícita siempre pasa).
         *
         * El candado de la máquina NO cambia: la vía externa (Cowork/auditor, RoadmapIntakeService)
         * sigue naciendo `pendiente_revision`. Esta ruta exige sesión + `roadmap_manage`, así que
         * "lo creó un humano autorizado en la UI" es exactamente lo que la distingue.
         */
        $jarvis = app(JarvisService::class);
        $texto  = trim(($data['title'] ?? '') . ' ' . ($data['description'] ?? '') . ' ' . ($data['prompt'] ?? ''));

        // EXCEPCIÓN: si el item declara algo de la frontera dura (prod / borrar datos / dinero /
        // credenciales), NO corre solo. Se queda esperando que él lo confirme a propósito — que
        // lo haya escrito no vuelve reversible un borrado de datos.
        // Se pide el DETALLE, no sólo la categoría: la válvula de más abajo necesita el término
        // exacto que disparó para poder juzgarlo. Antes recibía la categoría y la presentaba al
        // modelo como si fuera el término — ver `JarvisService::fronteraDuraDetalle()`.
        $fronteraDet = $jarvis->fronteraDuraDetalle($texto);
        $frontera    = $fronteraDet['categoria'];

        // #648 — EL EFECTO DE LA CATEGORÍA GOBIERNA AQUÍ. `avisar` es la posición más suave de la
        // perilla: la detección se registra y se cuenta, pero NO retiene el item. Se separa de
        // `$frontera` en vez de anularla para que la bitácora siga diciendo qué disparó — un item
        // que pasó sin freno tiene que decir por qué pasó.
        $fronteraSoloAvisa = $frontera !== null && ($fronteraDet['efecto'] ?? 'bandeja') === 'avisar';
        if ($fronteraSoloAvisa) {
            $frontera = null;
        }

        if ($frontera === null) {
            $data['estado_aprobacion'] = 'aprobado_irving';
            $data['aprobado_por']      = $this->actorLabel();
            $data['revisado_at']       = now();
        } else {
            // VÁLVULA DE NACIMIENTO (2026-08-20). El keyword pegó, pero pegar no es tocar: los items
            // #874-#877 quedaron retenidos aquí por citar una ruta de archivo y por su propio bloque
            // de guardrails. Se le pregunta al modelo si el término se USA o sólo se NOMBRA.
            //
            // ⚠️ AFLOJAR AQUÍ NO DA `aprobado_irving` — regla fija de Irving: ninguna válvula de
            // contexto puede hacer que un item NAZCA auto-ejecutable. Lo que cambia es que el
            // veredicto queda GUARDADO, y con eso el item deja de estar vetado por la frontera dura
            // en `TorreAutomationPolicy::estadoInicial()` —que lo forzaría a `requiere_irving` para
            // siempre, por delante de cualquier configuración— y entra al camino normal:
            // triaje → revisor → autopilot. El último control sigue puesto.
            $itemTmp = new RoadmapItem([
                'title'       => $data['title'] ?? '',
                'description' => $data['description'] ?? null,
                'prompt'      => $data['prompt'] ?? null,
                'modulo'      => $data['modulo'] ?? null,
            ]);
            $v = app(\App\Modules\Addons\Roadmap\Services\ValvulaContextoService::class)
                ->evaluarNacimiento($itemTmp, (string) $fronteraDet['termino'], $frontera);

            $data['frontera_valvula']    = $v['ok'] ? ($v['afloja'] ? 'mencion' : 'accion') : null;
            $data['frontera_valvula_at'] = $v['ok'] ? now() : null;
            $valvulaNacimiento           = $v;
        }

        // Footprint: un item sin `modulo` corre SOLO y bloquea a las 6 terminales (#432 B2), así
        // que se le asigna aquí mismo en vez de dejarlo para el barrido posterior.
        if (empty($data['modulo'])) {
            $data['modulo'] = $jarvis->clasificarModulo($texto);
        }

        $item = RoadmapItem::create($data);

        if ($idemCacheKey) {
            Cache::put($idemCacheKey, $item->id, 30);
        }

        /*
         * #480 — TOQUE AL SUPERVISOR + ETA EN EL ALTA.
         *
         * El item pedía que el botón "Agregar" avisara al supervisor y éste devolviera un tiempo
         * estimado para que quien lo agregó lo ponga en su temporizador. El "supervisor" del
         * circuito hoy es Jarvis (no un rol Spatie humano ni WhatsApp — eso es de antes de #566):
         * ya calcula un ETA determinista por nivel_riesgo/tamaño del spec (`sellarEsfuerzo`) y lo
         * sella en `eta_minutos`/`eta_asignada_at`, pero solo lo hacía en su barrido periódico
         * (`tick()`), así que el alta no lo veía hasta el siguiente minuto. Aquí se sella
         * SÍNCRONO — el "toque" ocurre en el mismo request, sin esperar al barrido.
         */
        if ($frontera === null) {
            $jarvis->sellarEsfuerzo($item);
        }

        /*
         * #858 — LANZAR DE INMEDIATO, no esperar el ciclo de sondeo.
         *
         * Antes de este cambio el item quedaba `aprobado_irving` (ejecutable) pero quieto hasta
         * que `circuito:scheduler` corriera en su minuto de cron — hasta 60s perdidos con una
         * terminal libre y el trabajo listo. Aquí se llama exactamente al mismo método que usa el
         * botón "Jalar trabajo ahora" (`RoadmapCircuitoService::requestDisparo`, ver
         * RoadmapController::disparar) — NO es una segunda ruta de despacho: solo pone la MISMA
         * bandera que el picker on-box (`circuito:disparo-check`, cron cada minuto, sondea cada
         * 3s) consume para adelantar una corrida de `circuito:scheduler`, el único despachador
         * real. Si no hay terminal libre, el scheduler simplemente no hace nada este ciclo — el
         * item queda en cola exactamente igual que hoy, solo que revisado en segundos, no en
         * hasta un minuto. Un fallo de requestDisparo() (p.ej. circuito en pausa) NUNCA revierte
         * la creación: el item ya quedó guardado arriba.
         */
        $disparo = null;
        if ($frontera === null) {
            try {
                $disparo = $this->svc->requestDisparo($this->actorLabel(), 'boton', $item->id);
            } catch (\Throwable $e) {
                Log::warning('circuito.agregar_item.disparo_fallo', ['item_id' => $item->id, 'error' => $e->getMessage()]);
            }
        }

        // ⚠️ Esta asignación era una SOBREESCRITURA (`$item->log = [[...]]`) y se comía en silencio
        // cualquier entrada escrita entre el `create()` de arriba y esta línea. Hoy nadie más escribe
        // en esa ventana —se auditó: `sellarEsfuerzo` sólo toca eta, `requestDisparo` no toca el log,
        // y los hooks del modelo no producen entradas en un alta— pero el modo de fallo era del tipo
        // que no avisa: el dato no se pierde con un error, se pierde y ya. Ahora ANEXA, así que la
        // clase entera de bug deja de ser posible aunque mañana alguien enganche algo ahí.
        // (`RoadmapIntakeService` hace lo mismo pero ANTES del save, sobre un modelo que aún no
        // existe: ahí no hay nada que pisar y el patrón es correcto.)
        $entradas = [[
            'ts'      => now()->toIso8601String(),
            'por'     => $this->actorLabel(),
            'evento'  => 'item_creado_ui',
            'via'     => 'torre',
            'directo_a_cola' => $frontera === null,
            'frontera'       => $frontera,
            'frontera_detectada' => $fronteraDet['categoria'],
            'frontera_termino'   => $fronteraDet['termino'],
            'frontera_efecto'    => $fronteraDet['efecto'] ?? null,
            'frontera_solo_avisa' => $fronteraSoloAvisa,
            'eta_minutos'    => $item->eta_minutos,
            'disparo'        => $disparo,
        ]];

        // Evento PROPIO (`valvula_nacimiento`), separado de `valvula_contexto`: Irving pidió poder
        // auditar por separado cuántas veces aflojó la puerta de nacimiento y si algo se coló, sin
        // mezclarlo con el corpus del triaje de nivel. Esa medición es la que dirá en un mes si
        // esto fue buena idea.
        if (isset($valvulaNacimiento)) {
            $entradas[] = [
                'ts'        => now()->toIso8601String(),
                'por'       => 'valvula:nacimiento',
                'evento'    => 'valvula_nacimiento',
                // #648 — antes aquí se guardaba la CATEGORÍA bajo la etiqueta `termino`, el mismo
                // error que tenía el prompt. Ahora van los dos campos, cada uno con su nombre.
                'termino'   => $fronteraDet['termino'],
                'categoria' => $fronteraDet['categoria'],
                'efecto'    => $fronteraDet['efecto'] ?? null,
                'guarda'    => $valvulaNacimiento['guarda'] ?? null,
                'veredicto' => $valvulaNacimiento['veredicto'],
                'aflojo'    => (bool) $valvulaNacimiento['afloja'],
                'ok'        => (bool) $valvulaNacimiento['ok'],
                'modelo'    => $valvulaNacimiento['modelo'],
                'motivo'    => $valvulaNacimiento['razon'],
                // Explícito para que nadie lo lea mal dentro de un año.
                'nota'      => 'Aflojar aquí NO da aprobado_irving: acerca el item al camino normal '
                             . '(triaje → revisor → autopilot), nunca salta el último control.',
            ];
        }

        // EFECTO `bloquear`: además de retenerlo, lo saca del pool automático. Es lo que distingue
        // «bloquear» de «bandeja» — el item no vuelve a la cola hasta que Irving lo suelte a mano.
        if ($frontera !== null && ($fronteraDet['efecto'] ?? 'bandeja') === 'bloquear') {
            $item->excluir_pool_automatico = true;
            $item->motivo_bloqueo = "Frontera dura «{$frontera}» en modo BLOQUEAR (disparó «{$fronteraDet['termino']}»). "
                . 'Suéltalo desde la Torre cuando lo hayas revisado.';
            $item->origen_bloqueo = 'frontera_dura';
        }

        $item->log = array_merge($item->log ?? [], $entradas);
        $item->save();

        if ($fronteraSoloAvisa) {
            $aviso = "Creado y aprobado. Ojo: dispara «{$fronteraDet['termino']}» ({$fronteraDet['categoria']}), "
                . 'pero esa categoría está en modo «sólo avisar» y no retiene. Queda registrado.';
        } elseif ($frontera !== null) {
            $aviso = "Creado, pero NO entra solo a la cola: disparó «{$fronteraDet['termino']}» "
                . "→ frontera «{$frontera}» (efecto: " . ($fronteraDet['efecto'] ?? 'bandeja') . '). '
                . 'Apruébalo desde la bandeja si es lo que quieres.';
        } elseif ($disparo['ok'] ?? false) {
            $aviso = "Creado y aprobado: entra directo a la cola y ya se disparó — una terminal libre lo toma en segundos (JARVIS lo estimó en ~{$item->eta_minutos} min).";
        } else {
            $aviso = "Creado y aprobado: entra directo a la cola. JARVIS lo estimó en ~{$item->eta_minutos} min — una terminal libre lo toma en el próximo ciclo (no se pudo adelantar el disparo: " . ($disparo['mensaje'] ?? 'circuito en pausa') . ').';
        }

        return response()->json([
            'item'    => $item,
            'disparo' => $disparo,
            'aviso'   => $aviso,
        ], 201);
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
