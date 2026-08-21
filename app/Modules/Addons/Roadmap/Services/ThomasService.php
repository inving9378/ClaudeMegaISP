<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Support\DetectorTerminos;
use App\Modules\Addons\Roadmap\Services\TorreAutomationPolicy;
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
        // FASE 2A.3 — punto único: freno HUMANO frena, el del clasificador sólo informa.
        if ($item->tieneFrenoHumano()) {
            return $no('Item con freno humano vigente: decisión de Irving por definición.');
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
        // 2026-08-20 — ESTA es la puerta de NACIMIENTO: `RoadmapController::store` la consulta para
        // decidir si un item que Irving escribe nace `aprobado_irving` («crear = ejecutar», #566) o
        // se queda esperando. Usaba substring CRUDO sobre el texto completo, y por eso los items
        // #874-#877 NO nacieron autorizados: su propio bloque de guardrails —que promete no tocar
        // producción— contenía la palabra «producción».
        //
        // El texto que existe para PROTEGER no puede ser el que niega la autorización. Se comparte
        // ahora la definición única de `DetectorTerminos`: se quitan las líneas de proceso, se ancla
        // a palabra y se respetan las negaciones. El colapso de espacios se hace DESPUÉS de limpiar,
        // porque limpiar trabaja por líneas y necesita los saltos.
        $heno = mb_strtolower(preg_replace('/[ \t]+/', ' ', DetectorTerminos::limpiar($texto)));

        foreach ((array) config('circuito.thomas.escalamiento', []) as $categoria => $terminos) {
            foreach ((array) $terminos as $t) {
                if (DetectorTerminos::dispara($heno, mb_strtolower((string) $t))) {
                    return $categoria;
                }
            }
        }

        return null;
    }

    /**
     * FRONTERA DURA DE UN ITEM — igual que `categoriaFronteraDura()` sobre su texto, pero honrando
     * el veredicto que la VÁLVULA DE NACIMIENTO ya dejó guardado.
     *
     * Existe porque el keyword decide en un momento (el alta) y los guards que lo consumen corren
     * después, en otro proceso y sin el texto delante. Si cada uno re-evaluara el texto crudo, un
     * item ya despejado volvería a quedar vetado en el siguiente guard — que es exactamente lo que
     * pasaba: `TorreAutomationPolicy::estadoInicial()` lo forzaba a `requiere_irving` «por delante
     * de todo» aunque el triaje ya lo hubiera leído como B.
     *
     * `mencion` (la válvula lo despejó) → null: no hay frontera que aplicar.
     * `accion` o NULL (no evaluada / no se pudo preguntar) → manda el keyword, como siempre.
     */
    public function fronteraDuraDeItem(RoadmapItem $item): ?string
    {
        if ($item->frontera_valvula === 'mencion') {
            return null;
        }

        return $this->categoriaFronteraDura(
            (string) $item->title . ' ' . (string) $item->description . ' ' . (string) $item->prompt
        );
    }

    /**
     * #566 E2 — LA DECISIÓN YA ESTÁ TOMADA, el item sólo no avanzó.
     *
     * El autopilot audita 25 items de la bandeja y 11 salen con «no quedan preguntas sin responder
     * que el autopilot deba decidir» → y aun así se quedan ahí. Es el mismo error de fondo que el
     * bucle del merge: se trata «no hay nada que decidir» como «no aprobar», cuando significa lo
     * contrario — el brief está contestado, no falta nadie. Ése es el síntoma que #29 describía
     * como «se re-aprueba solo con las mismas respuestas q1-q6 cada día».
     *
     * Aquí se cierra: si todas las preguntas están contestadas y ninguna pendiente es de Irving,
     * el item pasa a la cola. Aplica hasta nivel C porque la decisión de C ya la tomó quien
     * respondió el brief; Thomas no está decidiendo por él, está dejando de retenerlo.
     */
    public function aprobarYaDecidido(RoadmapItem $item): array
    {
        $v = $this->evaluarYaDecidido($item);
        if (! $v['aprobado']) {
            return $v;
        }

        $estado = $v['estado'];

        $log = $item->log ?: [];
        $log[] = [
            'ts' => now()->toIso8601String(), 'por' => self::APROBADOR_YA_DECIDIDO,
            'decidido_por' => self::APROBADOR_YA_DECIDIDO, 'decision' => 'aprobar',
            'estado' => $estado, 'nivel' => $item->nivel_riesgo,
            'motivo' => 'Brief completamente respondido: la decisión ya estaba tomada y el item '
                . 'seguía retenido sin que faltara nadie.',
        ];

        $item->forceFill([
            'estado_aprobacion'       => $estado,
            'aprobado_por'            => self::APROBADOR_YA_DECIDIDO,
            'revisado_at'             => now(),
            'log'                     => $log,
            // Sale del parqueo: lo que lo retenía no era una decisión pendiente.
            'excluir_pool_automatico' => false,
            'bloqueado_por_bucle'     => false,
        ])->save();

        $this->reportes->append(
            $item, self::NOMBRE, 'decision',
            "Brief ya respondido → a la cola ({$estado}).",
            'Todas las preguntas del brief estaban contestadas; el item seguía retenido sin que '
                . 'faltara ninguna decisión.',
            ['estado' => $estado, 'reversible' => true]
        );

        Log::channel('roadmap_externo')->info('thomas-ya-decidido', [
            'item' => $item->id, 'nivel' => $item->nivel_riesgo, 'estado' => $estado,
        ]);

        return $v;
    }

    /**
     * Evaluación PURA del carril "ya decidido" (no escribe): para auditar en seco.
     *
     * ⚠️ DE QUÉ DEPENDE LA SEGURIDAD DE ESTE CARRIL — leer antes de tocarlo.
     *
     * «Brief 100 % contestado» **NO implica que lo contestara un humano**: `AutopilotService::
     * aplicar()` escribe `opcion_elegida` con sus propias respuestas. Lo que impide que un item
     * auto-contestado reentre por aquí y se re-apruebe **no está en este método**: está en el
     * FILTRO del único llamador, `DestrabarCommand`, que sólo alimenta items en
     * `requiere_irving | pendiente_revision | aprobado_irving` (+ parqueados y anti-bucle) — y el
     * autopilot los deja en `aprobado_claude`/`aprobado_revisor`, fuera de ese conjunto.
     *
     * Es decir: la seguridad de este carril vive en OTRO archivo, y quien lea sólo este método no
     * se entera. El día que alguien amplíe ese filtro por una razón razonable, el agujero se abre
     * sin que nada avise. Candado que lo impide:
     * `tests/Unit/Modules/Addons/Roadmap/DestrabeNoRecibeAutoAprobadosTest.php`.
     */
    public function evaluarYaDecidido(RoadmapItem $item): array
    {
        $no = fn (string $m) => ['aprobado' => false, 'estado' => null, 'motivo' => $m];

        if (! config('circuito.thomas.enabled', true) || $this->circuito->isPaused()) {
            return $no('Circuito en pausa o Thomas apagado.');
        }
        // FASE 2A.3 — punto único: freno HUMANO frena, el del clasificador sólo informa.
        if ($item->tieneFrenoHumano()) {
            return $no('Item con freno humano vigente: decisión de Irving por definición.');
        }
        // #893 — `requiere_sesion_supervisada` es un flag que Irving fija EXPLÍCITAMENTE para decir
        // «esto no se auto-despacha, necesito estar presente». No vive dentro de `tieneFrenoHumano()`
        // (es una columna aparte, ver Models/RoadmapItem.php), así que este carril lo ignoraba por
        // completo y aprobaba items que Irving había marcado para verse en persona. Guard propio.
        if ($item->requiere_sesion_supervisada) {
            return $no('Item marcado `requiere_sesion_supervisada`: Irving pidió estar presente, no se auto-despacha.');
        }
        // MISMO texto que el carril mecánico (título + descripción + prompt): antes este carril
        // miraba sólo título+descripción y un término de frontera que viviera en el `prompt` se le
        // escapaba, así que dos carriles con la misma regla daban veredictos distintos.
        // Honra el veredicto de la válvula de nacimiento (2026-08-20): un término que sólo se
        // MENCIONA no veta el carril mecánico. Uno que se toca de verdad sigue frenando aquí.
        if ($cat = $this->fronteraDuraDeItem($item)) {
            return $no("Declara «{$cat}» (frontera dura): decide Irving.");
        }

        // #893 — REGRESIÓN detectada al verificar este fix (`$texto` quedó indefinida en 96cf38f2:
        // ese commit reemplazó `categoriaFronteraDura($texto)` por `fronteraDuraDeItem($item)` pero
        // olvidó que el guard de NEGOCIO de abajo también consumía `$texto`). Sin esto el guard corría
        // contra `null`, jamás matcheaba, y "Thomas nunca inventa dirección de negocio/producto" —la
        // regla que el comentario de abajo dice que NO CAMBIA— quedaba rota en silencio. Se repone
        // aquí porque es la misma función que este item ya toca.
        $texto = (string) $item->title . ' ' . (string) $item->description . ' ' . (string) $item->prompt;

        // REGLA DURA QUE NO CAMBIA: Thomas nunca inventa dirección de negocio/producto. Que el
        // brief esté contestado no convierte una decisión de producto en trabajo mecánico.
        foreach ((array) config('circuito.thomas.mecanico.negocio', []) as $t) {
            if ($t !== '' && $this->apareceComoPalabra(mb_strtolower($texto), mb_strtolower($t))) {
                return $no("Menciona «{$t}»: es decisión de negocio/producto.");
            }
        }

        $preguntas = (array) $item->preguntasNormalizadas();
        if (! $preguntas) {
            return $no('No tiene brief: no hay una decisión previa que respetar.');
        }

        // #967 — anti-bucle: «pregunta maestra contestada» NO implica que la ACCIÓN FÍSICA que esa
        // respuesta implica ya ocurrió. Caso real #463↔#308: Irving eligió 9+ veces "mergear #308 a
        // main", pero mergear es un botón manual de Irving en la Torre, no algo que este carril pueda
        // dar por hecho — y cada re-aprobación reabría el pool para descubrir el mismo bloqueo otra
        // vez. Alcance MÍNIMO VIABLE (no es un sistema de dependencias): si la pregunta maestra ya
        // contestada menciona "#N" y N es un item nivel C con rama lista pero sin mergear (mismo
        // criterio que el guard de `saving()` en RoadmapItem, línea ~273), la dependencia sigue sin
        // resolver → no aprobar, aunque el brief esté 100% contestado.
        $master = $preguntas[0];
        if (($master['opcion_elegida'] ?? null) !== null) {
            foreach (self::referenciasItemEnTexto((string) ($master['pregunta'] ?? '')) as $n) {
                if ($n === $item->id) {
                    continue;
                }
                $dep = RoadmapItem::find($n);
                if ($dep && $dep->nivel_riesgo === 'C' && ! empty($dep->branch) && empty($dep->merge_commit)) {
                    return $no("La pregunta maestra depende de #{$n}, que sigue sin mergearse a main "
                        . '(nivel C, rama lista, espera el botón de merge de Irving en la Torre): la '
                        . 'decisión ya fue tomada, pero la acción física que implica todavía no ocurrió.');
                }
            }
        }

        foreach ($preguntas as $idx => $p) {
            // LECTOR DEFENSIVO (2026-08-19). Una pregunta SIN opciones no puede contar como
            // «contestada»: `$sinResponder` daría false y la pregunta pasaría de largo, aprobando
            // el item sin que nadie decidiera nada. Hoy es inalcanzable —`RevisorService::
            // parsePreguntas` descarta la pregunta entera si se queda sin opciones válidas— pero el
            // escritor es defensivo y el lector no lo era, y esa asimetría es una bomba barata de
            // desactivar. Si una pregunta así entra por otra vía (edición desde la Torre, escritura
            // externa, fila legacy), aquí se para.
            if (empty($p['opciones'])) {
                return $no('Tiene una pregunta sin opciones: no hay decisión que dar por tomada.');
            }

            $sinResponder = ($p['opcion_elegida'] ?? null) === null;
            if (! $sinResponder) {
                // #893 — «contestada» no es lo mismo que «decidida a favor del pool»: la opción
                // elegida de la pregunta MAESTRA (la primera del brief — no existe un flag propio
                // que la marque, así que se usa su posición, igual que asume el resto del brief)
                // puede ser LITERALMENTE la que dice «escalar a Irving» (la recomendada del Revisor
                // cuando no puede resolver algo solo). Tratar eso como brief-completo y aprobar es
                // lo que causó las 12 escalaciones idénticas de #186. Se para aquí igual que con una
                // pregunta sin responder. Acotado a la pregunta 0: preguntas secundarias pueden
                // mencionar «escalar a Irving» como parte de un plan de contingencia (ej. «rollback +
                // escalar a Irving» si algo falla) sin que ESA sea la decisión tomada — falso
                // positivo real visto en #463 q4, que no es la pregunta maestra.
                if ($idx === 0 && self::opcionElegidaEsEscalar($p)) {
                    return $no('La opción elegida de la pregunta maestra es "escalar a Irving": no es una decisión tomada para el pool.');
                }
                continue;
            }
            // Queda algo sin contestar: si es de Irving, es suyo; si no, que lo tome el autopilot.
            return RoadmapItem::boolEstricto($p, 'requiere_irving') === true
                ? $no('Tiene una pregunta marcada `requiere_irving` sin contestar.')
                : $no('Todavía tiene preguntas sin responder: las decide el autopilot con su brief.');
        }

        // ENTREGA 1 — el estado lo resuelve la POLÍTICA, no una condición local. Hasta aquí este
        // carril no miraba NINGÚN tope de nivel: un item C con el brief contestado quedaba
        // `aprobado_revisor` y se despachaba. Ahora pasa por `min(politicaBase, ya_decidido)`, cuyo
        // sub-techo nace en `C` justamente para no apagar ese comportamiento al construir el panel.
        $estado = app(TorreAutomationPolicy::class)->estadoInicial($item, 'thomas.ya_decidido');
        if ($estado === 'requiere_irving') {
            return $no('La política de la Torre no autoriza este nivel por el carril «ya decidido».');
        }

        return [
            'aprobado' => true,
            'estado'   => $estado,
            'motivo'   => 'Brief ya respondido: no falta ninguna decisión.',
        ];
    }

    /**
     * #893 — ¿el texto de la opción ELEGIDA de esta pregunta es literalmente "escalar a Irving"?
     * PURA (solo arrays, sin BD ni contenedor) a propósito: es el núcleo del fix de las 12
     * escalaciones idénticas de #186, y necesita un test de regresión que no dependa de bootear
     * Laravel ni tocar la BD compartida de dev (`tests/TestCase.php` corre `migrate:fresh` contra
     * ella — ver `EvaluarYaDecididoEscalarTest`).
     */
    public static function opcionElegidaEsEscalar(array $pregunta): bool
    {
        $clave = $pregunta['opcion_elegida'] ?? null;
        if ($clave === null) {
            return false;
        }
        foreach ((array) ($pregunta['opciones'] ?? []) as $o) {
            if (($o['clave'] ?? null) === $clave) {
                return stripos((string) ($o['texto'] ?? ''), 'escalar a irving') !== false;
            }
        }

        return false;
    }

    /**
     * #967 — ids de RoadmapItem referenciados como "#N" en un texto (ej. "mergear #308 a main").
     * PURA (solo regex, sin BD) a propósito: misma razón que `opcionElegidaEsEscalar()`, necesita
     * un test de regresión que no dependa de bootear Laravel ni tocar la BD compartida de dev. La
     * resolución de si esa dependencia sigue sin resolver (nivel/branch/merge_commit) vive en
     * `evaluarYaDecidido()`, que sí tiene BD.
     */
    public static function referenciasItemEnTexto(string $texto): array
    {
        if ($texto === '' || ! preg_match_all('/#(\d+)/', $texto, $m)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $m[1])));
    }

    public const APROBADOR_YA_DECIDIDO = 'thomas-ya-decidido';

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

        // ENTREGA 1 — la política decide el estado (y aplica los 4 topes duros + el override).
        // El sub-techo `thomas.mecanico.max_nivel` (B) sigue siendo más conservador que el del
        // autopilot a propósito: este carril no tiene un brief humano detrás.
        $estado = app(TorreAutomationPolicy::class)->estadoInicial($item, 'thomas.mecanico');
        if ($estado === 'requiere_irving') {
            return ['aprobado' => false, 'estado' => null,
                'motivo' => 'La política de la Torre no autoriza este nivel por el carril mecánico.'];
        }

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
        // FASE 2A.3 — punto único: freno HUMANO frena, el del clasificador sólo informa.
        if ($item->tieneFrenoHumano()) {
            return $no('Item con freno humano vigente: frontera dura.');
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
    // 1-quinquies. CONSOLIDADO ESTRATÉGICO (#566 E4)
    // =================================================================

    /**
     * Junta en UNA sola pregunta lo estratégico que Thomas no debe decidir.
     *
     * El problema no era el volumen sino la FORMA: N items bloqueados por separado, cada uno
     * pidiendo una decisión suelta, se leen como una montaña y no se despachan nunca. Juntos, con
     * la recomendación de Thomas al lado de cada punto, se contestan de una pasada.
     *
     * Thomas NO decide aquí: recomienda. La recomendación es la opción que él tomaría si pudiera,
     * y sirve para dos cosas — que Irving conteste con un sí/no en vez de redactar, y que exista un
     * default por si no contesta (ver `procederPorDefault`).
     */
    public function consolidar(array $items): array
    {
        $puntos = [];

        foreach ($items as $item) {
            $rec = null;
            foreach ((array) $item->preguntasNormalizadas() as $p) {
                if (($p['opcion_elegida'] ?? null) !== null || empty($p['opciones'])) {
                    continue;
                }
                // La recomendada del brief; si no hay, la primera reversible.
                foreach ($p['opciones'] as $o) {
                    if (is_array($o) && RoadmapItem::boolEstricto($o, 'recomendada') === true) {
                        $rec = ['pregunta' => $p['pregunta'] ?? '', 'opcion' => (string) ($o['texto'] ?? ''),
                            'reversible' => RoadmapItem::boolEstricto($o, 'reversible') === true];
                        break 2;
                    }
                }
                foreach ($p['opciones'] as $o) {
                    if (is_array($o) && RoadmapItem::boolEstricto($o, 'reversible') === true) {
                        $rec = ['pregunta' => $p['pregunta'] ?? '', 'opcion' => (string) ($o['texto'] ?? ''),
                            'reversible' => true];
                        break 2;
                    }
                }
            }

            $puntos[] = [
                'id'            => (int) $item->id,
                'title'         => (string) $item->title,
                'modulo'        => (string) $item->modulo,
                'nivel'         => (string) $item->nivel_riesgo,
                'pregunta'      => $rec['pregunta'] ?? 'Sin pregunta estructurada; requiere su criterio.',
                'recomendacion' => $rec['opcion'] ?? null,
                'reversible'    => $rec['reversible'] ?? false,
            ];
        }

        return $puntos;
    }

    /** Escribe el consolidado como un solo documento legible para Irving. */
    public function escribirConsolidado(array $puntos): string
    {
        $path  = (string) config('circuito.thomas.consolidado.doc_path', storage_path('app/circuito/decisiones-pendientes-irving.md'));
        $horas = (int) config('circuito.thomas.consolidado.horas_default', 48);

        $md  = "# Decisiones pendientes — una sola pasada\n\n";
        $md .= '> Generado por Thomas el ' . now()->format('Y-m-d H:i') . ". Son las decisiones que **no**\n";
        $md .= "> puede tomar solo: estratégicas o irreversibles. Todo lo demás ya lo resolvió y está corriendo.\n";
        $md .= ">\n";
        $md .= $horas > 0
            ? "> **Si no contestas en {$horas} h**, Thomas procede con la recomendación en los puntos marcados\n"
                . "> ♻️ *reversible* y lo deja registrado para que lo revises después. Los no reversibles esperan.\n\n"
            : "> Thomas **no** procede solo en ninguno: todos esperan tu respuesta.\n\n";

        foreach ($puntos as $i => $p) {
            $n = $i + 1;
            $md .= "## {$n}. #{$p['id']} — {$p['title']}\n\n";
            $md .= "- **Módulo:** {$p['modulo']} · **Nivel:** {$p['nivel']}\n";
            $md .= "- **Qué hay que definir:** {$p['pregunta']}\n";
            if ($p['recomendacion']) {
                $md .= '- **Recomendación de Thomas:** ' . $p['recomendacion']
                    . ($p['reversible'] ? "  ♻️ *reversible*" : '  ⚠️ *no reversible — espera tu respuesta*') . "\n";
            } else {
                $md .= "- **Recomendación de Thomas:** — (no hay opción estructurada que recomendar)\n";
            }
            $md .= "\n";
        }

        // El doc vive fuera de git (estado de runtime) → el directorio puede no existir en un
        // checkout nuevo. Se crea al vuelo; la escritura sigue siendo best-effort (@).
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($path, $md);

        return $path;
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

    /**
     * #895 — ¿Este item cabe en una vuelta, o hay que descomponerlo en sub-items ANTES de picar
     * código? La terminal corre esto como primer paso (via `circuito:cabida`).
     *
     * CONSERVADOR A PROPÓSITO: solo dice que NO cabe con evidencia dura — nunca con el bucket
     * heurístico de `EstimadorTiempo` (techo fijo por nivel de riesgo sin muestras reales, que
     * dispararía para casi cualquier item B/C sin decir nada útil). Dos señales, cualquiera basta:
     *   1. Empírica: `reanudaciones_timeout >= 1` — el item YA timeouteó antes.
     *   2. Histórica: mediana real (`eta_metodo = 'historico'`, ≥3 muestras módulo+nivel) por
     *      encima del umbral configurado.
     * Sin ninguna señal → cabe (el estimado es orientativo, no un oráculo: por defecto no bloquea).
     *
     * Idempotente: si el item YA se descompuso (tiene sub-items, abiertos o cerrados), no vuelve a
     * evaluar — decir "cabe" aquí solo significa "no re-descompongas", el guard de paraguas del
     * modelo ya se encarga de que no se complete mientras le queden sub-items abiertos.
     *
     * @return array{cabe:bool, motivo:string, eta_segundos:?int}
     */
    public function caberEnVuelta(RoadmapItem $item): array
    {
        if ($item->yaFueDescompuesto()) {
            return ['cabe' => true, 'motivo' => 'ya_descompuesto', 'eta_segundos' => null];
        }

        if ((int) $item->reanudaciones_timeout >= 1) {
            return ['cabe' => false, 'motivo' => 'ya_timeouteo_antes', 'eta_segundos' => null];
        }

        $eta    = $this->circuito->estimarEtaTrabajo($item->modulo, $item->nivel_riesgo);
        $umbral = (int) config('circuito.thomas.cabida.umbral_segundos', 480);

        if ($eta['eta_metodo'] === 'historico' && $eta['eta_segundos'] > $umbral) {
            return ['cabe' => false, 'motivo' => 'historico_excede_umbral', 'eta_segundos' => $eta['eta_segundos']];
        }

        return ['cabe' => true, 'motivo' => 'sin_senal_de_riesgo', 'eta_segundos' => $eta['eta_segundos']];
    }

    // =================================================================
    // 3. VERIFICACIÓN DE CIERRE
    // =================================================================

    /**
     * Criterios de aceptación COMUNES que Thomas exige antes de dar por bueno un cierre. Los
     * específicos de cada item viven en su propio spec y los verifica la terminal.
     *
     * #1005 (#1003 §1/§2): `sin_ui` es el escape valve para items sin pantalla (migraciones,
     * refactors, tests) — exentos de `enlace_revision` SIEMPRE que traigan `sin_ui_motivo` (cómo se
     * comprobó en su lugar: el comando, el test, la consulta). Si SÍ hay `enlace_revision`, se
     * valida que el path resuelva contra el registro de rutas — un link con typo ya no pasa en
     * silencio.
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

        if ($item->sin_ui) {
            if (trim((string) $item->sin_ui_motivo) === '') {
                $faltantes[] = 'marcado sin_ui pero sin sin_ui_motivo (cómo se comprobó, ya que no hay pantalla que enlazar)';
            }
        } elseif (($cfg['exige_enlace_revision'] ?? true) && trim((string) $item->enlace_revision) === '') {
            $faltantes[] = 'falta enlace_revision (la ruta REAL de la UI donde se ve el cambio) — o marcar sin_ui con su motivo si de verdad no hay pantalla';
        } elseif (($cfg['valida_enlace_resuelve'] ?? true) && ! $this->enlaceRevisionResuelve($item->enlace_revision)) {
            $faltantes[] = 'enlace_revision no resuelve contra el registro de rutas (¿typo en el path?)';
        }

        if ($item->tieneConsultaViva()) {
            $faltantes[] = 'cerró con una consulta a Thomas todavía sin resolver';
        }

        return ['ok' => $faltantes === [], 'faltantes' => $faltantes];
    }

    /**
     * #1005 (#1003 §2) — ¿el path que abre `enlace_revision` resuelve contra el registro de rutas?
     * El campo es texto libre coloquial ("/releases → pestaña X → botón Y"), no una URL pura, así
     * que solo se evalúa el PRIMER token si empieza con "/" (ruta web real). Si apunta a un archivo
     * o doc (`docs/...`, `file://...`) no hay ruta que resolver contra el router y se deja pasar —
     * esa validación de existencia de archivo queda fuera de alcance de este gate.
     */
    public function enlaceRevisionResuelve(?string $enlace): bool
    {
        $texto = trim((string) $enlace);
        if ($texto === '' || $texto[0] !== '/') {
            return true;
        }

        $primerToken = preg_split('/\s+/', $texto, 2)[0];
        $path        = '/' . ltrim(parse_url($primerToken, PHP_URL_PATH) ?: $primerToken, '/');

        try {
            app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path, 'GET'));

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * #1008 (#1003 §6) — ¿el item que se está cerrando arrastra preguntas que SÍ necesitaban
     * decisión de Irving (`preguntas[].requiere_irving=true`) y se quedaron sin `opcion_elegida`?
     * Patrón confirmado en auditoría (26 casos, wt-3 2026-08-21): quedaban enterradas en el log
     * del item cerrado en vez de generar su propio seguimiento.
     *
     * PURA (no escribe), igual que `verificarCierre()`, para poder auditarla en seco.
     */
    public function preguntasSinResolver(RoadmapItem $item): array
    {
        $preguntas = is_array($item->preguntas) ? $item->preguntas : [];

        return array_values(array_filter($preguntas, function ($p) {
            if (! is_array($p)) {
                return false;
            }

            $requiere = RoadmapItem::boolEstricto($p, 'requiere_irving') === true;
            $elegida  = trim((string) ($p['opcion_elegida'] ?? '')) !== '';

            return $requiere && ! $elegida;
        }));
    }

    /**
     * #1008 — crea el sub-item de seguimiento (mismo mecanismo que `circuito:sub-item` /
     * `RoadmapIntakeService::crear`, sin pasar por ahí porque ya estamos dentro del `saving()` del
     * padre) para que la(s) pregunta(s) sin resolver no queden enterradas. Nace DIRECTO en la
     * bandeja de Irving (`requiere_irving`, no `pendiente_revision`): no es trabajo nuevo que el
     * revisor tenga que triajear desde cero, es la MISMA decisión que ya estaba pendiente cuando el
     * padre se cerró — heredando su módulo y nivel_riesgo.
     */
    public function generarSeguimientoPreguntas(RoadmapItem $item, array $sinResolver): RoadmapItem
    {
        $lista = collect($sinResolver)
            ->map(fn ($p) => '- ' . trim((string) ($p['pregunta'] ?? '(sin texto)')))
            ->implode("\n");

        $hijo                      = new RoadmapItem();
        $hijo->title               = mb_substr("Seguimiento: pregunta sin resolver de #{$item->id}", 0, 255);
        $hijo->description         = "El item #{$item->id} («{$item->title}») se cerró con "
            . count($sinResolver) . ' pregunta(s) que requerían decisión de Irving y quedaron sin '
            . "opción elegida:\n\n{$lista}\n\nVer el item padre para el detalle completo (opciones, "
            . 'confianza, reversibilidad) de cada pregunta.';
        $hijo->modulo              = $item->modulo;
        $hijo->origen_item_id      = $item->id;
        $hijo->nivel_riesgo        = $item->nivel_riesgo;
        $hijo->nivel_riesgo_origen = $item->nivel_riesgo_origen;
        $hijo->preguntas           = array_values($sinResolver);
        $hijo->estado_aprobacion   = 'requiere_irving';
        $hijo->status              = 'pending';
        $hijo->log                 = [[
            'ts'     => now()->toIso8601String(),
            'por'    => self::NOMBRE,
            'evento' => 'item_creado',
            'via'    => 'interno',
            'padre'  => $item->id,
            'motivo' => 'Auto-generado al cerrar el padre con preguntas sin resolver (#1008).',
        ]];
        $hijo->save();

        $this->reportes->append(
            $hijo,
            self::NOMBRE,
            'nota',
            "Seguimiento de #{$item->id}: " . count($sinResolver) . ' pregunta(s) sin resolver al cerrar el padre.',
            null,
            ['padre' => $item->id]
        );

        return $hijo;
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
