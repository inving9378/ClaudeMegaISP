<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\Log;

/**
 * AUTOPILOT del Circuito (#507 sub-paso 2).
 *
 * Corre DESPUÉS del brief del Revisor y ANTES de la bandeja: si el brief trae una recomendación con
 * respaldo suficiente (sub-paso 1: `recomendada` + `confianza` + `reversible`), la toma solo y manda
 * el item a la cola ejecutable. Si no, el item cae en la bandeja de Irving como siempre.
 *
 * PRINCIPIO: el autopilot solo actúa con DATO EXPLÍCITO. Todo lo que sea ausencia, ambigüedad o
 * error se resuelve mandando el item a Irving — nunca ejecutando. Por eso los items con briefs
 * viejos (sin `confianza`/`reversible`) siguen yendo todos a la bandeja.
 *
 * NO reinventa el flujo: reusa `responderPregunta` (lo mismo que hace "elegir-opción" de la Torre) y
 * los estados de aprobación que ya existen — A → `aprobado_claude`, B → `aprobado_revisor`, los dos
 * ya reconocidos por `ejecutablesParalelo`. Deja rastro en el `log` del item con
 * `decidido_por='autopilot'`, así que revertir es lo mismo que revertir una decisión humana.
 *
 * NO toca `guard()`: ese candado vive en la vía EXTERNA (Cowork/MCP) y debe seguir cerrado.
 */
class AutopilotService
{
    /** Orden de severidad de los niveles (A más seguro, C decisión de Irving). */
    private const NIVELES = ['A' => 1, 'B' => 2, 'C' => 3];

    /** Orden de la confianza declarada por el Revisor. */
    private const CONFIANZAS = ['baja' => 1, 'media' => 2, 'alta' => 3];

    public function __construct(private RoadmapCircuitoService $circuito)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('circuito.autopilot.enabled', true);
    }

    /**
     * Estado del autopilot para el banner de la Torre (#507 sub-paso 4): la política vigente + qué
     * decidió hoy. `auto_hoy` se cuenta por `aprobado_por='autopilot'` (lo sella `aplicar`), no
     * escarbando el JSON del log — es la misma información y no cuesta un scan.
     */
    public function resumen(): array
    {
        return [
            'enabled'             => $this->enabled(),
            'pausado'             => $this->circuito->isPaused(),
            'continuo'            => $this->circuito->esContinuo(),
            'max_nivel'           => strtoupper((string) config('circuito.autopilot.max_nivel', 'B')),
            'umbral_confianza'    => strtolower((string) config('circuito.autopilot.umbral_confianza', 'alta')),
            'requiere_reversible' => (bool) config('circuito.autopilot.requiere_reversible', true),
            'ventana_gracia'      => (int) config('circuito.autopilot.ventana_gracia', 0),
            'auto_hoy'            => RoadmapItem::where('aprobado_por', 'autopilot')
                                        ->whereDate('revisado_at', today())->count(),
        ];
    }

    /**
     * ¿Puede el autopilot decidir este item? EVALÚA SIN ESCRIBIR NADA (lo usa el --dry del comando
     * y la propia `aplicar`). Devuelve:
     *   ['auto' => bool, 'motivo' => 'slug', 'detalle' => 'texto para el log',
     *    'respuestas' => [preguntaId => claveOpcion], 'confianza' => 'alta', 'reversible' => bool]
     *
     * `$ignorarPausa` es SOLO para auditar en seco (--dry): con el circuito pausado toda evaluación
     * daría "kill switch" y la herramienta de auditoría quedaría inútil justo cuando más se usa
     * (antes de aflojar la política). Ninguna vía que ESCRIBA lo activa.
     */
    public function evaluar(RoadmapItem $item, bool $ignorarPausa = false, ?string $topeSimulado = null): array
    {
        $no = fn (string $motivo, string $detalle) => [
            'auto' => false, 'motivo' => $motivo, 'detalle' => $detalle,
            'respuestas' => [], 'confianza' => null, 'reversible' => null,
        ];

        if (! $this->enabled()) {
            return $no('autopilot_apagado', 'El autopilot está apagado (circuito.autopilot.enabled).');
        }

        // KILL SWITCH: el mismo de siempre. En pausa no se decide nada.
        if (! $ignorarPausa && $this->circuito->isPaused()) {
            return $no('circuito_pausado', 'Circuito en pausa (kill switch): el autopilot no decide.');
        }

        // Solo actúa sobre lo que está esperando decisión. Nunca sobre lo ya aprobado, en vuelo,
        // cerrado ni sobre lo que un humano tomó a mano.
        if ($item->estacion !== 'bandeja') {
            return $no('no_esta_en_bandeja', "El item no está esperando decisión (estación: {$item->estacion}).");
        }

        // FRONTERA DURA: negocio y producción jamás los toca el autopilot, aunque el brief venga
        // impecable. Es la misma frontera que ya respeta el Revisor.
        // FASE 2A.3 — punto único: freno HUMANO frena, el del clasificador sólo informa.
        if ($item->tieneFrenoHumano()) {
            return $no('frontera_dura', 'Item con freno humano vigente: es decisión de Irving por definición.');
        }

        $nivel = (string) $item->nivel_riesgo;
        if (! isset(self::NIVELES[$nivel])) {
            return $no('sin_nivel', 'El item no tiene nivel de riesgo asignado: sin triar no se ejecuta solo.');
        }

        // #648 — el tope se resuelve en UN solo lugar (`TorreAutomationPolicy::subTecho`), que es
        // quien sabe si manda la perilla de la pantalla (`torre_config.autopilot_max_nivel`) o la
        // config. Leerlo aquí a pelo dejaría la perilla a medio cablear: el panel diría `B` y esta
        // puerta seguiría dejando pasar `C`.
        $tope = $topeSimulado !== null && isset(self::NIVELES[$topeSimulado])
            ? $topeSimulado
            : (string) app(TorreAutomationPolicy::class)->subTecho('autopilot');
        $tope = isset(self::NIVELES[$tope]) ? $tope : 'B';   // valor raro → tope seguro, nunca el permisivo
        if (self::NIVELES[$nivel] > self::NIVELES[$tope]) {
            return $no('nivel_sobre_tope', "Nivel {$nivel} por encima del tope del autopilot ({$tope}): decide Irving.");
        }

        // Ventana de gracia (0 = decide de inmediato): tiempo mínimo desde que se escribió el brief.
        $gracia = (int) config('circuito.autopilot.ventana_gracia', 0);
        if ($gracia > 0 && $item->revisado_at && $item->revisado_at->addMinutes($gracia)->isFuture()) {
            return $no('en_ventana_gracia', "Dentro de la ventana de gracia de {$gracia} min desde el brief.");
        }

        $preguntas = $item->preguntasNormalizadas();
        if (empty($preguntas)) {
            return $no('sin_brief', 'El item aún no tiene brief con preguntas: no hay nada que decidir.');
        }

        $umbral = strtolower((string) config('circuito.autopilot.umbral_confianza', 'alta'));
        $umbral = isset(self::CONFIANZAS[$umbral]) ? $umbral : 'alta';
        $exigeReversible = (bool) config('circuito.autopilot.requiere_reversible', true);

        $respuestas = [];
        $confianzaMin = null;
        $reversibleTodas = true;

        foreach ($preguntas as $p) {
            // Una pregunta sin opciones no bloquea (mismo criterio que `preguntasPendientes`).
            if (empty($p['opciones'])) {
                continue;
            }

            // El Revisor puede marcar una pregunta como "esta no la puedo resolver yo".
            if (! empty($p['requiere_irving'])) {
                return $no('pregunta_requiere_irving',
                    "El Revisor marcó la pregunta [{$p['id']}] como decisión de Irving.");
            }

            // Si Irving ya respondió esta pregunta, se respeta su respuesta y no se sobrescribe.
            if (! empty($p['opcion_elegida'])) {
                continue;
            }

            $rec = null;
            foreach ($p['opciones'] as $o) {
                if (! empty($o['recomendada'])) {
                    $rec = $o;
                    break;
                }
            }
            if ($rec === null) {
                return $no('sin_recomendada', "La pregunta [{$p['id']}] no trae una opción recomendada.");
            }

            $conf = $rec['confianza'] ?? null;
            if ($conf === null || ! isset(self::CONFIANZAS[$conf])) {
                return $no('sin_confianza',
                    "La opción recomendada de [{$p['id']}] no declara confianza (brief viejo o dato ausente).");
            }
            if (self::CONFIANZAS[$conf] < self::CONFIANZAS[$umbral]) {
                return $no('confianza_insuficiente',
                    "Confianza '{$conf}' en [{$p['id']}] por debajo del umbral '{$umbral}'.");
            }

            // El nivel A es reversible POR DEFINICIÓN (aditivo, no toca dinero/permisos/auth/prod),
            // así que no se le exige el dato. B y C sí: sin un `reversible: true` explícito, no pasa.
            if ($nivel !== 'A' && $exigeReversible && ($rec['reversible'] ?? null) !== true) {
                return $no('no_reversible',
                    "La opción recomendada de [{$p['id']}] no está marcada como reversible (nivel {$nivel}).");
            }

            $respuestas[$p['id']] = $rec['clave'];
            $reversibleTodas = $reversibleTodas && (($rec['reversible'] ?? null) === true);
            if ($confianzaMin === null || self::CONFIANZAS[$conf] < self::CONFIANZAS[$confianzaMin]) {
                $confianzaMin = $conf;
            }
        }

        if (empty($respuestas)) {
            return $no('nada_que_responder', 'No quedan preguntas sin responder que el autopilot deba decidir.');
        }

        return [
            'auto'       => true,
            'motivo'     => 'recomendada_respaldada',
            'detalle'    => 'Recomendación con confianza ' . $confianzaMin
                . ($nivel === 'A' ? ' (nivel A: reversible por definición)' : ($reversibleTodas ? ' y reversible' : '')),
            'respuestas' => $respuestas,
            'confianza'  => $confianzaMin,
            'reversible' => $nivel === 'A' ? true : $reversibleTodas,
        ];
    }

    /**
     * ¿CALIFICA DE VERDAD? — la evaluación del brief **más** la política de la Torre, en una sola
     * respuesta. Éste es el punto único que deben usar tanto el dry-run como la aplicación real.
     *
     * POR QUÉ EXISTE (2026-08-27). Antes había dos veredictos y no coincidían:
     *
     *  · `--dry` llamaba sólo a `evaluar()`, que NO consulta la política → prometía items que la
     *    aplicación real rechazaba un segundo después. Medido en la bandeja de dev: el dry decía
     *    «2 de 109 calificarían» y al aplicar se movieron **cero**.
     *  · Las dos capas usan además el rótulo `frontera_dura` para COSAS DISTINTAS: `evaluar()` lo
     *    devuelve por `tieneFrenoHumano()` (freno puesto sobre el item), y la política por
     *    `tocaFronteraDura()` (el item TOCA dinero/producción/borrado). Un item podía pasar el
     *    primero y morir en el segundo sin que el reporte lo dijera.
     *
     * Con un solo veredicto, lo que promete el dry-run es exactamente lo que hace el real.
     */
    public function evaluarConPolitica(RoadmapItem $item, bool $ignorarPausa = false, ?string $topeSimulado = null): array
    {
        $v = $this->evaluar($item, $ignorarPausa, $topeSimulado);
        if (! $v['auto']) {
            return $v;
        }

        // La POLÍTICA DE LA TORRE: 4 topes duros → `manual` absoluto → override → matriz con
        // `min(politicaBase, autopilot.max_nivel)`. El gate de nivel de `evaluar()` es el mismo
        // número, pero los topes duros y el override sólo viven aquí.
        $policy = app(TorreAutomationPolicy::class);
        $estado = $policy->estadoInicial($item, 'autopilot', $topeSimulado);

        if ($estado === 'requiere_irving') {
            // Nombrar la frontera concreta (`borrar_datos`, `dinero`, …) en vez de un genérico:
            // «la política no autoriza» no le dice a nadie qué habría que cambiar, y en este caso
            // la respuesta correcta es que NO hay nada que cambiar — esa frontera no se levanta.
            $frontera = $policy->tocaFronteraDura($item);

            return array_merge($v, [
                'auto'    => false,
                'motivo'  => $frontera !== null ? 'frontera_dura_politica' : 'politica_torre',
                'detalle' => $frontera !== null
                    ? "Frontera dura ({$frontera}): no la levanta ninguna configuración, decide Irving."
                    : 'La política de la Torre no autoriza este item al autopilot (tope, `manual` u override).',
            ]);
        }

        return $v + ['estado_previsto' => $estado];
    }

    /**
     * Decide el item si `evaluarConPolitica` lo autoriza: responde cada pregunta con su recomendada,
     * lo pasa al estado aprobado que corresponde a su nivel y deja el rastro en el `log`.
     * Devuelve el mismo arreglo + ['aplicado' => bool, 'estado' => ?string].
     */
    public function aplicar(RoadmapItem $item): array
    {
        $v = $this->evaluarConPolitica($item);
        if (! $v['auto']) {
            return $v + ['aplicado' => false, 'estado' => null];
        }

        $estado = (string) $v['estado_previsto'];

        // ⚠️ LAS RESPUESTAS SE ESCRIBEN DESPUÉS DEL GATE, NUNCA ANTES (2026-08-27).
        // Antes este bucle iba arriba y la política se consultaba después: si rechazaba, el item se
        // quedaba con TODAS sus preguntas contestadas por el autopilot y sin aprobar. El brief se
        // veía «100 % contestado» —`JarvisService` ya advertía que eso no implica que lo contestara
        // un humano— y el item parecía decidido sin poder despachar nunca. Es la familia de las
        // aprobaciones mudas (#32, #186): no falla, se degrada a algo que parece una decisión.
        // `responderPregunta()` sólo muta en memoria, pero cualquier `save()` posterior del mismo
        // objeto persistía la mutación; el orden es lo que lo cierra de raíz.
        foreach ($v['respuestas'] as $pid => $clave) {
            $item->responderPregunta((string) $pid, (string) $clave);
        }

        $item->estado_aprobacion = $estado;
        $item->aprobado_por      = 'autopilot';
        $item->revisado_at       = now();

        $log = $item->log ?: [];
        $log[] = [
            'ts'           => now()->toIso8601String(),
            'por'          => 'autopilot',
            'decidido_por' => 'autopilot',   // discriminador explícito frente a las decisiones de Irving
            'decision'     => 'aprobar',
            'estado'       => $estado,
            'nivel'        => $item->nivel_riesgo,
            'respuestas'   => $v['respuestas'],
            'confianza'    => $v['confianza'],
            'reversible'   => $v['reversible'],
            'motivo'       => $v['detalle'],
            // Con qué política se decidió: si mañana se afloja el tope, el histórico sigue siendo
            // legible ("esto se auto-ejecutó cuando el tope era B").
            'politica'     => [
                'max_nivel'           => config('circuito.autopilot.max_nivel'),
                'umbral_confianza'    => config('circuito.autopilot.umbral_confianza'),
                'requiere_reversible' => (bool) config('circuito.autopilot.requiere_reversible'),
            ],
        ];
        $item->log = $log;
        $item->save();

        Log::channel('roadmap_externo')->info('autopilot: decisión automática', [
            'item' => $item->id, 'nivel' => $item->nivel_riesgo, 'estado' => $estado,
            'confianza' => $v['confianza'], 'reversible' => $v['reversible'],
        ]);

        return $v + ['aplicado' => true, 'estado' => $estado];
    }

    /**
     * Enganche best-effort para el momento en que se escribe un brief: intenta decidir y NUNCA
     * propaga un fallo (si el autopilot truena, el item simplemente se queda en la bandeja, que es
     * el comportamiento de siempre).
     */
    public function intentar(RoadmapItem $item): void
    {
        try {
            $this->aplicar($item);
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('autopilot: fallo al decidir (item queda en bandeja)', [
                'item' => $item->id, 'error' => mb_strimwidth($e->getMessage(), 0, 200, '…'),
            ]);
        }
    }

    /**
     * ¿CUÁNTOS ITEMS CALIFICARÍAN CON EL TECHO EN A, EN B Y EN C? (#648)
     *
     * La perilla del techo del autopilot es la que más consecuencia tiene de todo el panel, y hasta
     * hoy se movía a ciegas. El dato existía —Irving lo midió el 2026-08-27: **en A calificaban 0 de
     * 109**— pero había que pedirlo a mano. Aquí va junto a la perilla, que es donde sirve: antes de
     * moverla, no después.
     *
     * Usa `evaluarConPolitica()` con `$topeSimulado`, es decir **el mismo veredicto que aplica el
     * real**, no una cuenta paralela. Ésa fue la lección del `--dry` que prometía 2 y movía 0: dos
     * veredictos para lo mismo terminan discrepando, y el que se enseña en pantalla es el que menos
     * se verifica. `ignorarPausa = true` porque con el circuito pausado todo daría «kill switch» y
     * la herramienta quedaría inútil justo cuando más se usa — antes de aflojar la política.
     *
     * SIMULACIÓN PURA: no escribe nada y no toca el techo real.
     */
    public function simulacionTechos(): array
    {
        $niveles = ['A', 'B', 'C'];
        $policy  = app(TorreAutomationPolicy::class);

        $candidatos = [];
        RoadmapItem::query()
            ->whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
            ->chunkById(200, function ($items) use (&$candidatos) {
                foreach ($items as $item) {
                    if ($item->estacion === 'bandeja') {
                        $candidatos[] = $item;
                    }
                }
            });

        $porNivel = [];
        foreach ($niveles as $tope) {
            $califican = 0;
            $motivos   = [];
            foreach ($candidatos as $item) {
                $v = $this->evaluarConPolitica($item, true, $tope);
                if ($v['auto'] ?? false) {
                    $califican++;
                } else {
                    $m = (string) ($v['motivo'] ?? 'desconocido');
                    $motivos[$m] = ($motivos[$m] ?? 0) + 1;
                }
            }
            arsort($motivos);
            $porNivel[$tope] = [
                'califican'     => $califican,
                'de'            => count($candidatos),
                'top_motivos'   => array_slice($motivos, 0, 4, true),
            ];
        }

        $cfg = app(TorreConfigService::class)->get();

        return [
            'calculado_en'  => now()->toDateTimeString(),
            'candidatos'    => count($candidatos),
            'tope_vigente'  => $policy->subTecho('autopilot'),
            'fuente'        => $cfg->autopilotMaxNivelFuente(),
            'efectivo'      => $policy->nivelEfectivo('autopilot'),
            'politica_base' => $policy->politicaBase(),
            'por_nivel'     => $porNivel,
            'diagnostico'   => $this->diagnosticoDelCero($candidatos),
        ];
    }

    /**
     * POR QUÉ CALIFICAN 0 — el número solo no dice nada (#649).
     *
     * Un «0 califican» admite dos lecturas opuestas y la perilla se mueve distinto en cada una:
     *
     *   (a) el techo está mal puesto  → moverlo cambia algo;
     *   (b) la población muere ANTES de llegar al gate de nivel → moverlo no cambia nada, y el
     *       trabajo real está en otra parte.
     *
     * Medido el 2026-08-27, la respuesta es (b): 51 de 108 items de la bandeja no tienen brief, y de
     * las 162 preguntas que sí existen, 118 están marcadas como decisión de Irving y sin responder.
     * El autopilot lleva **0 decisiones en toda la historia** del roadmap. El techo está conectado
     * —el disparo existe y es `RevisorService::aplicarPreguntas()` → `intentar()`, que corre cada vez
     * que se escribe un brief— pero es irrelevante mientras la bandeja esté así.
     *
     * Esto se calcula EN VIVO, no se copia: si mañana la bandeja cambia, el diagnóstico cambia con
     * ella. Un texto fijo explicando un número variable es otra forma de mentir despacio.
     *
     * @param  array<int,RoadmapItem>  $candidatos
     */
    private function diagnosticoDelCero(array $candidatos): array
    {
        $conBrief = $sinBrief = 0;
        $sinBriefPorNivel = $conBriefPorNivel = [];
        $preguntas = $irvingPendientes = $todasRespondidas = 0;

        foreach ($candidatos as $item) {
            $nivel = $item->nivel_riesgo ?: '(sin nivel)';
            $p     = $item->preguntasNormalizadas();

            if (empty($p)) {
                $sinBrief++;
                $sinBriefPorNivel[$nivel] = ($sinBriefPorNivel[$nivel] ?? 0) + 1;
                continue;
            }

            $conBrief++;
            $conBriefPorNivel[$nivel] = ($conBriefPorNivel[$nivel] ?? 0) + 1;

            $pendientes = 0;
            foreach ($p as $q) {
                $preguntas++;
                if (empty($q['opcion_elegida'])) {
                    $pendientes++;
                    if (! empty($q['requiere_irving'])) {
                        $irvingPendientes++;
                    }
                }
            }
            if ($pendientes === 0) {
                $todasRespondidas++;
            }
        }

        ksort($sinBriefPorNivel);
        ksort($conBriefPorNivel);

        return [
            'con_brief'            => $conBrief,
            'sin_brief'            => $sinBrief,
            'sin_brief_por_nivel'  => $sinBriefPorNivel,
            'con_brief_por_nivel'  => $conBriefPorNivel,
            'preguntas'            => $preguntas,
            'irving_sin_responder' => $irvingPendientes,
            'todas_respondidas'    => $todasRespondidas,
            'decisiones_historicas' => $this->decisionesHistoricas(),
            'productores'          => $this->productoresDeBrief(),
        ];
    }

    /**
     * ¿Cuántas veces ha decidido el autopilot, alguna vez? Se cuenta sobre el rastro real de los
     * items (`log`), no sobre un contador aparte que podría estar mal.
     *
     * Es el número que distingue «el motor está afinado y hoy no hay trabajo para él» de «este
     * motor nunca ha arrancado». Al 2026-08-27 vale **0**.
     */
    private function decisionesHistoricas(): array
    {
        $n = 0;
        $items = [];
        $ultima = null;

        RoadmapItem::query()->whereNotNull('log')->select(['id', 'log'])
            ->chunkById(300, function ($chunk) use (&$n, &$items, &$ultima) {
                foreach ($chunk as $item) {
                    foreach ((array) $item->log as $e) {
                        if (! is_array($e)) {
                            continue;
                        }
                        $quien = (string) ($e['por'] ?? '') . '|' . (string) ($e['decidido_por'] ?? '');
                        if (! str_contains($quien, 'autopilot')) {
                            continue;
                        }
                        $n++;
                        $items[$item->id] = true;
                        $ts = (string) ($e['ts'] ?? '');
                        if ($ts !== '' && ($ultima === null || $ts > $ultima)) {
                            $ultima = $ts;
                        }
                    }
                }
            });

        return ['decisiones' => $n, 'items' => count($items), 'ultima' => $ultima];
    }

    /**
     * QUIÉN ESCRIBE EL BRIEF, y si ese productor está vivo.
     *
     * La pregunta importa porque «no hay brief» tiene dos causas muy distintas: que el productor
     * esté muerto (otro motor sin arranque, el patrón que ya mordió con `circuito:re-triage`), o
     * que esté vivo pero no cubra a esa población. Aquí es lo SEGUNDO, y por eso hay que decirlo:
     * `circuito:brief-c` late cada 10 minutos pero sólo mira items de **nivel C**, y el grueso de
     * los que no tienen brief son **B**.
     */
    private function productoresDeBrief(): array
    {
        $latidos = [];
        try {
            foreach ((array) app(RoadmapCircuitoService::class)->latidos() as $m) {
                if (is_array($m) && isset($m['comando'])) {
                    $latidos[$m['comando']] = $m;
                }
            }
        } catch (\Throwable) {
            $latidos = [];
        }

        $foto = function (string $comando) use ($latidos): array {
            $m = $latidos[$comando] ?? null;

            return [
                'ultima'   => $m['at'] ?? null,
                'cadencia' => $m['cadencia'] ?? null,
                'vencido'  => (bool) ($m['vencido'] ?? false),
                'agendado' => $m['agendado'] ?? null,
            ];
        };

        return [
            [
                'quien'   => 'circuito:brief-c',
                'escribe' => 'El brief de decisión (preguntas + opciones) de los items nivel C.',
                'cubre'   => 'SÓLO nivel C. Los B sin brief no los toca nadie automáticamente.',
                'estado'  => $foto('circuito:brief-c'),
            ],
            [
                'quien'   => 'circuito:revisar-backlog',
                'escribe' => 'Nada: emite veredictos sobre B en pendiente_revision. NO escribe briefs.',
                'cubre'   => 'B en pendiente_revision — no la bandeja.',
                'estado'  => $foto('circuito:revisar-backlog'),
            ],
            [
                'quien'   => 'RevisorService::aplicarPreguntas() → AutopilotService::intentar()',
                'escribe' => 'Es el DISPARO del autopilot, no un productor de briefs.',
                'cubre'   => 'Corre cada vez que se escribe un brief. Por eso el autopilot sí se ejecuta; '
                           . 'lo que no ha hecho nunca es calificar.',
                'estado'  => ['ultima' => null, 'cadencia' => 'en línea, al escribirse cada brief',
                    'vencido' => false, 'agendado' => null],
            ],
            [
                'quien'   => 'Irving',
                'escribe' => 'Las respuestas a las preguntas marcadas `requiere_irving`.',
                'cubre'   => 'Ningún motor puede sustituirlo: el Revisor las marcó como decisión suya.',
                'estado'  => ['ultima' => null, 'cadencia' => 'a mano, desde la bandeja',
                    'vencido' => false, 'agendado' => null],
            ],
        ];
    }
}
