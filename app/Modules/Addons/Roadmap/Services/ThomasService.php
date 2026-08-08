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
