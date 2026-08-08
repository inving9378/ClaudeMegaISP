<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\Log;

/**
 * TORRE V2 — THOMAS, la autoridad intermedia que faltaba.
 *
 * ANTES: la única salida de una terminal que dudaba era `estado_aprobacion = requiere_irving`. No
 * existía nadie entre las seis terminales e Irving, así que cualquier titubeo —incluso sobre algo
 * rutinario y reversible— despertaba al humano y el item se quedaba parado esperando confirmación
 * en vez de avanzar sobre la opción recomendada.
 *
 * AHORA: la terminal le pregunta a Thomas y Thomas responde EN EL ACTO con la política fija
 * (`config/circuito.thomas`). Solo lo irreversible de alto impacto —prod, borrar datos, gastar
 * dinero, credenciales/seguridad— y el spec contradictorio llegan a Irving.
 *
 * DETERMINISTA A PROPÓSITO: la resolución es coincidencia de términos, sin llamada a IA. Así la
 * terminal no se bloquea esperando un turno del loop, la respuesta es reproducible y auditable, y
 * la política se cambia editando config en vez de re-prompteando a un modelo.
 *
 * QUÉ **NO** ES: Thomas no reparte trabajo por su cuenta. El reparto (slots, módulo-disjunto,
 * reclamo atómico, lease) ya lo hace `circuito:scheduler`, que es el único despachador desde #432
 * B1; duplicarlo aquí sería crear una segunda verdad sobre quién trabaja qué. Thomas es el JUICIO
 * que le faltaba a esa maquinaria: resuelve dudas, estima esfuerzo y verifica el cierre.
 */
class ThomasService
{
    public const NOMBRE = 'thomas';

    public function __construct(
        private RoadmapReportService $reportes,
        private RoadmapCircuitoService $circuito,
    ) {
    }

    // =================================================================
    // 1. RESOLUCIÓN DE CONSULTAS (el corazón)
    // =================================================================

    /**
     * Resuelve una duda de una terminal. Devuelve:
     *   ['decision' => 'procede'|'escalado', 'respuesta' => string, 'motivo' => string,
     *    'categoria' => string|null]
     *
     * @param  array  $opciones  [{texto, recomendada?, confianza?, reversible?}] — mismo contrato
     *                           de brief que ya usa el autopilot.
     */
    public function resolverConsulta(RoadmapItem $item, string $pregunta, array $opciones, string $sid): array
    {
        $pregunta = trim($pregunta);

        // Se sella la consulta ANTES de decidir: si algo truena a media resolución, queda el rastro
        // de que la terminal preguntó (y el loop la puede retomar) en vez de perderse.
        $item->forceFill([
            'consulta_supervisor'     => $pregunta,
            'consulta_supervisor_sid' => mb_substr($sid, 0, 16),
            'consulta_supervisor_at'  => now(),
            'consulta_opciones'       => $opciones ?: null,
        ])->save();

        $this->reportes->append($item, $sid, 'consulta', mb_substr($pregunta, 0, 500), null, [
            'opciones' => $opciones,
        ]);

        $veredicto = $this->evaluar($item, $pregunta, $opciones);

        if ($veredicto['decision'] === 'escalado') {
            $this->escalar($item, $veredicto, $sid);
        } else {
            $this->responder($item, $veredicto, $sid);
        }

        Log::channel('roadmap_externo')->info('thomas-consulta', [
            'item' => $item->id, 'sid' => $sid, 'decision' => $veredicto['decision'],
            'categoria' => $veredicto['categoria'],
        ]);

        return $veredicto;
    }

    /**
     * LA POLÍTICA. Pura (no escribe nada) para poder auditarla en seco.
     *
     * Orden de evaluación, del corte más duro al más permisivo:
     *   1. ¿Cae en el conjunto de escalamiento? → Irving.
     *   2. ¿La terminal declara el spec contradictorio/imposible? → Irving.
     *   3. ¿Hay opción recomendada? → esa, y sigue.
     *   4. Sin recomendada: la primera reversible → esa, y sigue.
     *   5. Ninguna reversible (o sin opciones) → Irving (la irreversibilidad es la señal).
     */
    public function evaluar(RoadmapItem $item, string $pregunta, array $opciones): array
    {
        // (1) Frontera dura. Se mira la pregunta MÁS el contexto del item: un item titulado
        // "cobros" cuya pregunta suena inocente sigue siendo territorio de dinero.
        $heno = mb_strtolower($pregunta . ' ' . $item->title . ' ' . (string) $item->modulo);
        foreach ((array) config('circuito.thomas.escalamiento', []) as $categoria => $terminos) {
            foreach ((array) $terminos as $t) {
                if ($t !== '' && str_contains($heno, mb_strtolower($t))) {
                    return [
                        'decision'  => 'escalado',
                        'categoria' => $categoria,
                        'motivo'    => "Cae en la frontera dura «{$categoria}» (término detectado: «{$t}»): "
                            . 'irreversible y de alto impacto, es decisión de Irving.',
                        'respuesta' => 'DETENTE. Esto sale del alcance que Thomas puede autorizar. '
                            . 'El item queda en la bandeja de Irving con tu pregunta registrada. '
                            . 'No lo ejecutes ni lo dejes a medias: libera el área y termina.',
                    ];
                }
            }
        }

        // (2) Spec contradictorio: lo declara la terminal, no se adivina.
        if ($this->declaraContradiccion($pregunta)) {
            return [
                'decision'  => 'escalado',
                'categoria' => 'spec_contradictorio',
                'motivo'    => 'La terminal reporta que el spec del item se contradice a un grado que impide avanzar.',
                'respuesta' => 'DETENTE. El spec necesita que Irving lo desempate. Queda en su bandeja '
                    . 'con tu pregunta y las opciones que planteaste.',
            ];
        }

        // (3) Opción recomendada: el caso normal y el que más veces se va a dar.
        foreach ($opciones as $o) {
            if (is_array($o) && RoadmapItem::boolEstricto($o, 'recomendada') === true) {
                return [
                    'decision'  => 'procede',
                    'categoria' => null,
                    'motivo'    => 'Opción recomendada por la propia terminal; fuera del conjunto de escalamiento.',
                    'respuesta' => 'PROCEDE con la opción recomendada: ' . trim((string) ($o['texto'] ?? '')),
                ];
            }
        }

        // (4) Sin recomendada → la primera reversible.
        if (config('circuito.thomas.exige_reversible_sin_recomendada', true)) {
            foreach ($opciones as $o) {
                if (is_array($o) && RoadmapItem::boolEstricto($o, 'reversible') === true) {
                    return [
                        'decision'  => 'procede',
                        'categoria' => null,
                        'motivo'    => 'Ninguna opción venía marcada como recomendada; se toma la primera '
                            . 'declarada reversible, que es la que se puede deshacer si sale mal.',
                        'respuesta' => 'PROCEDE con la opción reversible: ' . trim((string) ($o['texto'] ?? '')),
                    ];
                }
            }
        } elseif ($opciones) {
            return [
                'decision'  => 'procede',
                'categoria' => null,
                'motivo'    => 'Sin recomendada y sin exigencia de reversibilidad: se toma la primera opción.',
                'respuesta' => 'PROCEDE con: ' . trim((string) ($opciones[0]['texto'] ?? '')),
            ];
        }

        // (5) Ni recomendada ni reversible: la ausencia del dato ES la señal de riesgo.
        return [
            'decision'  => 'escalado',
            'categoria' => 'sin_opcion_segura',
            'motivo'    => $opciones
                ? 'Ninguna de las opciones planteadas se declara reversible: no hay camino que Thomas pueda deshacer si sale mal.'
                : 'La consulta llegó sin opciones que evaluar.',
            'respuesta' => 'DETENTE. Plantea al menos una opción reversible, o queda para Irving. '
                . 'El item ya está en su bandeja con tu pregunta.',
        ];
    }

    /** ¿La terminal está declarando que el spec se contradice? Se busca la declaración explícita. */
    private function declaraContradiccion(string $pregunta): bool
    {
        $p = mb_strtolower($pregunta);
        foreach (['spec contradictorio', 'se contradice', 'contradicción en el spec',
            'contradiccion en el spec', 'el item se contradice', 'requisitos incompatibles'] as $marca) {
            if (str_contains($p, $marca)) {
                return true;
            }
        }

        return false;
    }

    /** Thomas resuelve: registra la respuesta y el item sigue su curso con la terminal. */
    private function responder(RoadmapItem $item, array $veredicto, string $sid): void
    {
        $item->forceFill([
            'consulta_respuesta'    => $veredicto['respuesta'],
            'consulta_resuelta_at'  => now(),
            'consulta_resuelta_por' => self::NOMBRE,
        ])->save();

        $this->reportes->append(
            $item,
            self::NOMBRE,
            'respuesta',
            mb_substr($veredicto['respuesta'], 0, 500),
            $veredicto['motivo'],
            ['decision' => 'procede', 'para_terminal' => $sid]
        );
    }

    /**
     * Escala a Irving. La consulta se marca resuelta (por Irving, no por Thomas) para que el loop
     * no la reprocese, pero el item SÍ sale del lazo automático hacia su bandeja.
     */
    private function escalar(RoadmapItem $item, array $veredicto, string $sid): void
    {
        $item->forceFill([
            'consulta_respuesta'    => $veredicto['respuesta'],
            'consulta_resuelta_at'  => now(),
            'consulta_resuelta_por' => 'escalado-a-irving',
            'estado_aprobacion'     => 'requiere_irving',
            'worker_sid'            => null,
        ])->save();

        $this->reportes->append(
            $item,
            self::NOMBRE,
            'escalacion',
            'Escalado a Irving: ' . ($veredicto['categoria'] ?? 'sin categoría'),
            $veredicto['motivo'],
            ['decision' => 'escalado', 'categoria' => $veredicto['categoria'], 'desde_terminal' => $sid]
        );
    }

    // =================================================================
    // 1-bis. CARRIL MECÁNICO (#566) — aprobar lo que no tiene nada que decidir
    // =================================================================

    /**
     * ¿Este item es MECÁNICO + REVERSIBLE + no-prod, es decir, trabajo sin decisión pendiente?
     *
     * El autopilot exige un brief con `confianza`/`reversible` explícitos, así que un item obvio
     * pero sin brief se queda en la bandeja pidiéndole a Irving que "decida" algo que ya está
     * decidido por el enunciado (cerrar un hueco ruteado, borrar andamiaje muerto, registrar una
     * ruta que da 404). Eso es peaje puro y es lo que seca la cola.
     *
     * PURA: no escribe nada, para poder auditarla en seco.
     * Devuelve ['mecanico' => bool, 'motivo' => string, 'senal' => ?string].
     */
    public function clasificarMecanico(RoadmapItem $item): array
    {
        $no = fn (string $m) => ['mecanico' => false, 'motivo' => $m, 'senal' => null];

        if (! config('circuito.thomas.mecanico.enabled', true)) {
            return $no('El carril mecánico está apagado (circuito.thomas.mecanico.enabled).');
        }
        if (! config('circuito.thomas.enabled', true) || $this->circuito->isPaused()) {
            return $no('Circuito en pausa o Thomas apagado: no se aprueba nada.');
        }

        // Rótulo de frontera dura: es de Irving por definición, sin importar el contenido.
        if (preg_match('/\[(BLOCKED|PARKED)-/i', (string) $item->title)) {
            return $no('Item rotulado [BLOCKED-]/[PARKED-]: decisión de Irving por definición.');
        }

        // Un C es una decisión de diseño; un item sin nivel no está triado.
        $nivel = (string) $item->nivel_riesgo;
        $orden = ['A' => 1, 'B' => 2, 'C' => 3];
        $tope  = strtoupper((string) config('circuito.thomas.mecanico.max_nivel', 'B'));
        if (! isset($orden[$nivel])) {
            return $no('Sin nivel de riesgo: sin triar no entra al carril mecánico.');
        }
        if ($orden[$nivel] > ($orden[$tope] ?? 2)) {
            return $no("Nivel {$nivel} por encima del tope mecánico ({$tope}): es decisión de diseño.");
        }

        $heno = mb_strtolower(preg_replace('/\s+/', ' ',
            (string) $item->title . ' ' . (string) $item->description . ' ' . (string) $item->prompt));

        // (1) Frontera dura: prod / borrar datos / dinero / credenciales. Se reusa EXACTAMENTE el
        // mismo conjunto que gobierna las consultas de las terminales — una sola definición de
        // "esto no lo decide la máquina", no dos que puedan divergir.
        foreach ((array) config('circuito.thomas.escalamiento', []) as $categoria => $terminos) {
            foreach ((array) $terminos as $t) {
                if ($t !== '' && str_contains($heno, mb_strtolower($t))) {
                    return $no("Cae en la frontera dura «{$categoria}» («{$t}»): irreversible o de alto impacto.");
                }
            }
        }

        // (2) Negocio/producto: qué DEBE hacer una feature no lo decide una máquina.
        foreach ((array) config('circuito.thomas.mecanico.negocio', []) as $t) {
            if ($t !== '' && $this->apareceComoPalabra($heno, mb_strtolower($t))) {
                return $no("Menciona «{$t}»: es decisión de negocio/producto, no trabajo mecánico.");
            }
        }

        // (3) Señal mecánica explícita. ALLOWLIST: sin señal conocida, se queda con Irving.
        foreach ((array) config('circuito.thomas.mecanico.senales', []) as $t) {
            if ($t !== '' && $this->apareceComoPalabra($heno, mb_strtolower($t))) {
                return [
                    'mecanico' => true,
                    'senal'    => $t,
                    'motivo'   => "Trabajo mecánico y reversible (señal «{$t}»), nivel {$nivel}, "
                        . 'fuera de la frontera dura y sin componente de negocio.',
                ];
            }
        }

        return $no('No coincide con ninguna señal mecánica conocida: ante la duda, se queda con Irving.');
    }

    /**
     * FOOTPRINT de un texto (título + descripción) según el mapa determinista de
     * `config/circuito.clasificador`. Devuelve el módulo o null si nada matchea.
     *
     * Vive aquí y no en el comando porque tiene DOS consumidores: el barrido
     * `circuito:clasificar-modulo` y el alta desde la Torre, que necesita darle footprint al item
     * en el momento de crearlo (un item nuevo sin módulo nace bloqueando a las 6 terminales).
     *
     * Palabra completa, no substring — ver `apareceComoPalabra`.
     */
    public function clasificarModulo(string $texto): ?string
    {
        $heno = mb_strtolower(preg_replace('/\s+/', ' ', $texto));

        foreach ((array) config('circuito.clasificador.reglas', []) as $modulo => $terminos) {
            foreach ((array) $terminos as $t) {
                if ($t !== '' && $this->apareceComoPalabra($heno, mb_strtolower($t))) {
                    return $modulo;
                }
            }
        }

        return null;
    }

    /**
     * ¿El texto de un item declara algo de la frontera dura (prod / borrar datos / dinero /
     * credenciales)? Devuelve la categoría o null.
     *
     * Lo usa el alta desde la Torre: un item que Irving crea corre solo por default, pero si
     * declara algo irreversible se para para que él lo confirme a propósito.
     */
    public function categoriaFronteraDura(string $texto): ?string
    {
        $heno = mb_strtolower(preg_replace('/\s+/', ' ', $texto));

        foreach ((array) config('circuito.thomas.escalamiento', []) as $categoria => $terminos) {
            foreach ((array) $terminos as $t) {
                if ($t !== '' && str_contains($heno, mb_strtolower($t))) {
                    return $categoria;
                }
            }
        }

        return null;
    }

    /** ¿Cuántas auto-aprobaciones mecánicas van hoy? (para el tope diario) */
    public function mecanicosHoy(): int
    {
        return RoadmapItem::where('aprobado_por', self::APROBADOR_MECANICO)
            ->whereDate('revisado_at', now()->toDateString())
            ->count();
    }

    public const APROBADOR_MECANICO = 'thomas-mecanico';

    /**
     * Aprueba un item por el carril mecánico y lo manda a la cola ejecutable.
     *
     * Reusa los estados que el pool YA reconoce (A → `aprobado_claude`, B → `aprobado_revisor`):
     * no se inventa un estado nuevo ni se toca `ejecutablesParalelo`.
     * Devuelve ['aprobado' => bool, 'estado' => ?string, 'motivo' => string].
     */
    public function aprobarMecanico(RoadmapItem $item): array
    {
        $c = $this->clasificarMecanico($item);
        if (! $c['mecanico']) {
            return ['aprobado' => false, 'estado' => null, 'motivo' => $c['motivo']];
        }

        $tope = (int) config('circuito.thomas.mecanico.tope_diario', 25);
        if ($tope > 0 && $this->mecanicosHoy() >= $tope) {
            return ['aprobado' => false, 'estado' => null,
                'motivo' => "Tope diario del carril mecánico alcanzado ({$tope}). Se reanuda mañana."];
        }

        $estado = $item->nivel_riesgo === 'A' ? 'aprobado_claude' : 'aprobado_revisor';

        $log = $item->log ?: [];
        $log[] = [
            'ts'           => now()->toIso8601String(),
            'por'          => self::APROBADOR_MECANICO,
            'decidido_por' => self::APROBADOR_MECANICO,
            'decision'     => 'aprobar',
            'estado'       => $estado,
            'nivel'        => $item->nivel_riesgo,
            'senal'        => $c['senal'],
            'motivo'       => $c['motivo'],
            // Con qué política se decidió: si mañana se afloja, el histórico sigue siendo legible.
            'politica'     => [
                'max_nivel'   => config('circuito.thomas.mecanico.max_nivel'),
                'tope_diario' => $tope,
            ],
        ];

        $item->forceFill([
            'estado_aprobacion' => $estado,
            'aprobado_por'      => self::APROBADOR_MECANICO,
            'revisado_at'       => now(),
            'log'               => $log,
        ])->save();

        $this->reportes->append(
            $item,
            self::NOMBRE,
            'decision',
            "Auto-aprobado por el carril mecánico → {$estado}.",
            $c['motivo'],
            ['senal' => $c['senal'], 'estado' => $estado, 'reversible' => true]
        );

        Log::channel('roadmap_externo')->info('thomas-mecanico', [
            'item' => $item->id, 'nivel' => $item->nivel_riesgo, 'estado' => $estado, 'senal' => $c['senal'],
        ]);

        return ['aprobado' => true, 'estado' => $estado, 'motivo' => $c['motivo']];
    }

    /**
     * Coincidencia por PALABRA COMPLETA. `\b` de PCRE no sirve con acentos (en «auditoría» la í
     * rompe el borde), así que se delimita con `\p{L}\p{N}` y el modificador /u.
     */
    private function apareceComoPalabra(string $heno, string $termino): bool
    {
        return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($termino, '/') . '(?![\p{L}\p{N}])/u', $heno);
    }

    // =================================================================
    // 1-ter. ¿QUÉ ESPERA REALMENTE ESTE ITEM? (#566 — la raíz del bucle)
    // =================================================================

    /**
     * DIAGNÓSTICO: qué le falta de verdad a un item para avanzar.
     *
     * ESTA ES LA PIEZA QUE FALTABA. El bucle que dio 13 vueltas en #117 y 9 aprobaciones en #19 no
     * era falta de permiso: era que **aprobar se trataba como responder**. Un item cuya única
     * pendiente era el MERGE volvía a `aprobado_irving` con cada clic, el pool lo re-despachaba, el
     * worker veía que no había nada que hacer y lo re-escalaba. Aprobar más fuerte nunca lo iba a
     * mover, porque la aprobación no era lo que faltaba.
     *
     * Devuelve ['pendiente' => …, 'detalle' => …]:
     *   merge       → el trabajo está HECHO en su rama; falta integrarlo (→ E1 auto-merge).
     *   respuesta   → hay una pregunta puntual sin contestar; aprobar en genérico no la responde.
     *   ejecucion   → está listo para que una terminal lo trabaje (el caso normal).
     *   dependencia → espera a otro item.
     *   cierre      → ya no hay nada que hacer (superseded o terminado).
     *
     * PURA: no escribe nada.
     */
    public function pendienteReal(RoadmapItem $item): array
    {
        $r = fn (string $p, string $d) => ['pendiente' => $p, 'detalle' => $d];

        if (in_array($item->estado_aprobacion, ['completado', 'cancelado', 'rechazado'], true)
            || $item->status === 'done' || $item->archivado_at !== null) {
            return $r('cierre', 'El item ya está cerrado o archivado.');
        }

        // (1) MERGE — trabajo hecho esperando integración. Va PRIMERO: es la bolsa más grande y la
        // que más se confundía con "falta decidir".
        if (! empty($item->branch) && empty($item->merge_commit)) {
            // Las banderas de BD (`branch_has_content`, `branch_ahead_count`) las sella un chequeo
            // periódico y se quedan FRÍAS: #19 tenía trabajo real en su rama y las banderas en
            // cero, así que se leía como "falta ejecutarlo" y volvía al pool eternamente. Cuando
            // las banderas no son concluyentes se le pregunta a git, que es la única fuente que no
            // se desactualiza.
            $tieneTrabajo = (bool) $item->esperando_merge_irving
                || (bool) $item->branch_has_content
                || (int) $item->branch_ahead_count > 0;

            if (! $tieneTrabajo) {
                $tieneTrabajo = (bool) $this->circuito->archivosDeRama((string) $item->branch);
            }

            if ($tieneTrabajo) {
                return $r('merge', "La rama {$item->branch} tiene trabajo sin integrar: lo pendiente "
                    . 'es el merge, no una aprobación.');
            }
        }

        // (2) RESPUESTA — una pregunta concreta sin contestar. Aprobar en genérico NO la responde,
        // y ése fue el bucle de #99 (15 aprobaciones, ninguna respondía la pregunta).
        foreach ((array) $item->preguntasNormalizadas() as $p) {
            $sinResponder = ($p['opcion_elegida'] ?? null) === null && ! empty($p['opciones']);
            if ($sinResponder && RoadmapItem::boolEstricto($p, 'requiere_irving') === true) {
                return $r('respuesta', 'Tiene una pregunta marcada `requiere_irving` sin contestar: '
                    . 'aprobar en genérico no la responde.');
            }
        }

        // (3) DEPENDENCIA — lo declara el propio item.
        $texto = (string) $item->comentarios_claude . ' ' . (string) $item->description;
        if (preg_match('/\b(depende de|bloqueado por|espera a)\s+#(\d+)/ui', $texto, $m)) {
            return $r('dependencia', "Depende de #{$m[2]} según su propio registro.");
        }

        return $r('ejecucion', 'Listo para que una terminal lo trabaje.');
    }

    // =================================================================
    // 1-quater. AUTO-MERGE de trabajo verificado (#566 E1)
    // =================================================================

    /**
     * ¿La rama de este item se puede auto-mergear?
     *
     * Thomas NO reimplementa el merge: decide la ELEGIBILIDAD y se lo encola al MergeRunner de
     * siempre, que ya corre la verificación de regresión, el gate de frontend y aborta ante
     * conflicto dejando main intacto.
     *
     * Lo que retiene para Irving es lo que apunta a prod o es irreversible — y se decide mirando el
     * DIFF, no el título: "el item dice que no toca prod" no es verificable; "el diff no toca
     * `deploy/`" sí.
     */
    public function elegibleAutoMerge(RoadmapItem $item): array
    {
        $no = fn (string $m) => ['elegible' => false, 'motivo' => $m];

        if (! config('circuito.thomas.automerge.enabled', true)) {
            return $no('El auto-merge está apagado (circuito.thomas.automerge.enabled).');
        }
        if ($this->circuito->isPaused()) {
            return $no('Circuito en pausa (kill switch): no se auto-mergea nada.');
        }
        if (preg_match('/\[(BLOCKED|PARKED)-/i', (string) $item->title)) {
            return $no('Item rotulado [BLOCKED-]/[PARKED-]: frontera dura.');
        }
        if (empty($item->branch)) {
            return $no('No tiene rama que integrar.');
        }
        if (! empty($item->merge_commit)) {
            return $no('Ya está integrado.');
        }

        // Frontera dura por texto (prod/dinero/credenciales/borrar) — mismo config que gobierna
        // las consultas de las terminales. Una sola definición, no una copia que pueda divergir.
        $texto = (string) $item->title . ' ' . (string) $item->description;
        if ($cat = $this->categoriaFronteraDura($texto)) {
            return $no("Declara «{$cat}» (frontera dura): lo mergea Irving.");
        }

        // El DIFF manda: rutas sensibles y migraciones destructivas.
        $diff = $this->circuito->archivosDeRama((string) $item->branch);
        if ($diff === null) {
            return $no('No se pudo leer el diff de la rama: fail-closed, lo revisa Irving.');
        }
        if (! $diff) {
            return $no('La rama no trae cambios: no hay nada que integrar.');
        }

        foreach ((array) config('circuito.thomas.automerge.rutas_sensibles', []) as $ruta) {
            foreach ($diff as $archivo) {
                if ($ruta !== '' && str_contains($archivo, $ruta)) {
                    return $no("La rama toca «{$archivo}» (ruta sensible): lo mergea Irving.");
                }
            }
        }

        // Migraciones: agregar es reversible con `git revert`; un drop/truncate ya cambió el
        // esquema y el revert del código no lo deshace.
        $migraciones = array_filter($diff, fn ($f) => str_contains($f, 'migrations/'));
        if ($migraciones) {
            $cuerpo = $this->circuito->contenidoDeRama((string) $item->branch, $migraciones);
            foreach ((array) config('circuito.thomas.automerge.patrones_destructivos', []) as $p) {
                if ($p !== '' && stripos($cuerpo, $p) !== false) {
                    return $no("Trae una migración con «{$p}»: no se deshace con git revert, lo revisa Irving.");
                }
            }
        }

        return ['elegible' => true, 'motivo' => 'Trabajo verificado, reversible y sin tocar prod ('
            . count($diff) . ' archivo(s)).'];
    }

    /**
     * Encola el auto-merge de un item elegible. El MergeRunner hace el resto (verificación,
     * gate de frontend, abort ante conflicto). Devuelve ['ok' => bool, 'motivo' => string].
     */
    public function autoMergear(RoadmapItem $item): array
    {
        $e = $this->elegibleAutoMerge($item);
        if (! $e['elegible']) {
            return ['ok' => false, 'motivo' => $e['motivo']];
        }

        // Sale del parqueo: ya no espera a Irving, lo integra Thomas.
        $item->forceFill([
            'esperando_merge_irving'  => false,
            'excluir_pool_automatico' => false,
        ])->save();

        $this->circuito->enqueueMerge((int) $item->id, self::NOMBRE, 'auto');

        $this->reportes->append(
            $item,
            self::NOMBRE,
            'decision',
            'Auto-merge encolado: trabajo verificado, reversible y sin tocar prod.',
            $e['motivo'],
            ['rama' => $item->branch, 'reversible' => true]
        );

        Log::channel('roadmap_externo')->info('thomas-automerge', [
            'item' => $item->id, 'rama' => $item->branch, 'motivo' => $e['motivo'],
        ]);

        return ['ok' => true, 'motivo' => $e['motivo']];
    }

    // =================================================================
    // 2. ESTIMACIÓN DE ESFUERZO (orientativa, nunca bloqueante)
    // =================================================================

    /**
     * Minutos estimados para un item. No rechaza nada por pasarse del estimado: solo alimenta la
     * Torre y el orden del reparto. Determinista: mismo item → mismo número.
     */
    public function estimarEsfuerzo(RoadmapItem $item): int
    {
        $cfg  = (array) config('circuito.thomas.esfuerzo', []);
        $base = $cfg['base_por_nivel'][$item->nivel_riesgo] ?? ($cfg['base_sin_nivel'] ?? 45);

        $kbSpec = (mb_strlen((string) $item->description) + mb_strlen((string) $item->prompt)) / 1024;
        $extra  = (int) round($kbSpec * ($cfg['min_por_kb_spec'] ?? 8));

        return (int) min($base + $extra, $cfg['tope_minutos'] ?? 240);
    }

    /** Sella la estimación en el item (idempotente: no la re-escribe si ya la trae). */
    public function sellarEsfuerzo(RoadmapItem $item): int
    {
        if ($item->eta_minutos !== null) {
            return (int) $item->eta_minutos;
        }

        $eta = $this->estimarEsfuerzo($item);
        $item->forceFill(['eta_minutos' => $eta, 'eta_asignada_at' => now()])->save();

        return $eta;
    }

    // =================================================================
    // 3. VERIFICACIÓN DE CIERRE
    // =================================================================

    /**
     * Criterios de aceptación COMUNES que Thomas exige antes de dar por bueno un cierre. Los
     * específicos de cada item viven en su propio spec y los verifica la terminal.
     *
     * Devuelve ['ok' => bool, 'faltantes' => string[]]. No muta nada: decidir qué hacer con un
     * cierre incompleto es del llamador.
     */
    public function verificarCierre(RoadmapItem $item): array
    {
        $cfg       = (array) config('circuito.thomas.cierre', []);
        $faltantes = [];

        if (empty($item->branch)) {
            $faltantes[] = 'no registró rama de trabajo';
        }

        if (($cfg['exige_reporte_coloquial'] ?? true) && trim((string) $item->reporte_coloquial) === '') {
            $faltantes[] = 'falta reporte_coloquial (qué cambió y dónde, en llano) — sin esto Irving no puede revisarlo';
        }

        if (($cfg['exige_enlace_revision'] ?? true) && trim((string) $item->enlace_revision) === '') {
            $faltantes[] = 'falta enlace_revision (la ruta REAL de la UI donde se ve el cambio)';
        }

        if ($item->tieneConsultaViva()) {
            $faltantes[] = 'cerró con una consulta a Thomas todavía sin resolver';
        }

        return ['ok' => $faltantes === [], 'faltantes' => $faltantes];
    }

    // =================================================================
    // 4. DIAGNÓSTICO DEL REPARTO (invariantes que Thomas vigila)
    // =================================================================

    /**
     * Verifica las invariantes del reparto que el encargo exige, LEYENDO el estado real (no lo
     * cambia). Es lo que hace comprobable que "ninguna terminal está ociosa habiendo cola" y que
     * "dos items del mismo módulo no corren a la vez".
     */
    public function diagnostico(): array
    {
        $enVuelo = RoadmapItem::where('estado_aprobacion', 'en_progreso')
            ->get(['id', 'title', 'modulo', 'worker_sid', 'claimed_at', 'eta_minutos']);

        // Módulos con más de un item en vuelo = falla de serialización.
        $dobles = $enVuelo->filter(fn ($i) => filled($i->modulo))
            ->groupBy('modulo')->filter(fn ($g) => $g->count() > 1)
            ->map(fn ($g, $m) => ['modulo' => $m, 'ids' => $g->pluck('id')->all()])
            ->values()->all();

        $cola      = $this->circuito->ejecutablesParalelo([], 100);
        $n         = $this->circuito->getParalelismo();
        $ocupadas  = $enVuelo->pluck('worker_sid')->filter()->unique()->count();
        $libres    = max(0, $n - $ocupadas);

        return [
            'terminales'          => ['total' => $n, 'ocupadas' => $ocupadas, 'libres' => $libres],
            'en_vuelo'            => $enVuelo->map(fn ($i) => [
                'id' => (int) $i->id, 'modulo' => $i->modulo, 'terminal' => $i->worker_sid,
                'terminal_nombre' => $this->circuito->nombreWorker($i->worker_sid),
                'desde' => optional($i->claimed_at)->toIso8601String(), 'eta_min' => $i->eta_minutos,
            ])->values()->all(),
            'cola_ejecutable'     => count($cola),
            'colisiones_modulo'   => $dobles,
            'consultas_vivas'     => RoadmapItem::conConsultaViva()->count(),
            // La invariante del encargo: si hay cola aprobada, no debería quedar terminal ociosa.
            // (El scheduler corre cada minuto, así que un desfase de <60s es normal, no una falla.)
            'ocio_con_cola'       => $libres > 0 && count($cola) > 0,
            'pausado'             => $this->circuito->isPaused(),
        ];
    }

    /**
     * Barrida del loop: resuelve consultas que quedaron colgadas (la terminal murió o se le acabó
     * el turno después de preguntar) y sella estimaciones faltantes. Idempotente.
     */
    public function tick(bool $apply = true): array
    {
        if (! config('circuito.thomas.enabled', true)) {
            return ['saltado' => 'thomas deshabilitado'];
        }
        // Kill switch: en pausa Thomas no decide nada, igual que el autopilot.
        if ($this->circuito->isPaused()) {
            return ['saltado' => 'circuito en pausa'];
        }

        $resueltas = [];
        foreach (RoadmapItem::conConsultaViva()->limit(20)->get() as $item) {
            $veredicto = $this->evaluar($item, (string) $item->consulta_supervisor, (array) $item->consulta_opciones);
            if ($apply) {
                $veredicto['decision'] === 'escalado'
                    ? $this->escalar($item, $veredicto, (string) $item->consulta_supervisor_sid)
                    : $this->responder($item, $veredicto, (string) $item->consulta_supervisor_sid);
            }
            $resueltas[] = ['item' => (int) $item->id, 'decision' => $veredicto['decision']];
        }

        $sellados = 0;
        if ($apply) {
            foreach (RoadmapItem::whereNull('eta_minutos')->elegibleParaPool()
                ->whereIn('estado_aprobacion', ['aprobado_claude', 'aprobado_revisor', 'aprobado_irving', 'en_progreso'])
                ->limit(30)->get() as $item) {
                $this->sellarEsfuerzo($item);
                $sellados++;
            }
        }

        return ['consultas_resueltas' => $resueltas, 'esfuerzos_sellados' => $sellados];
    }
}
