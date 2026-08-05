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
     * ¿Puede el autopilot decidir este item? EVALÚA SIN ESCRIBIR NADA (lo usa el --dry del comando
     * y la propia `aplicar`). Devuelve:
     *   ['auto' => bool, 'motivo' => 'slug', 'detalle' => 'texto para el log',
     *    'respuestas' => [preguntaId => claveOpcion], 'confianza' => 'alta', 'reversible' => bool]
     *
     * `$ignorarPausa` es SOLO para auditar en seco (--dry): con el circuito pausado toda evaluación
     * daría "kill switch" y la herramienta de auditoría quedaría inútil justo cuando más se usa
     * (antes de aflojar la política). Ninguna vía que ESCRIBA lo activa.
     */
    public function evaluar(RoadmapItem $item, bool $ignorarPausa = false): array
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
        if (preg_match('/\[(BLOCKED|PARKED)-/i', (string) $item->title)) {
            return $no('frontera_dura', 'Item rotulado [BLOCKED-]/[PARKED-]: es decisión de Irving por definición.');
        }

        $nivel = (string) $item->nivel_riesgo;
        if (! isset(self::NIVELES[$nivel])) {
            return $no('sin_nivel', 'El item no tiene nivel de riesgo asignado: sin triar no se ejecuta solo.');
        }

        $tope = strtoupper((string) config('circuito.autopilot.max_nivel', 'B'));
        $tope = isset(self::NIVELES[$tope]) ? $tope : 'B';   // valor raro en config → cae al tope seguro
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
     * Decide el item si `evaluar` lo autoriza: responde cada pregunta con su recomendada, lo pasa al
     * estado aprobado que corresponde a su nivel y deja el rastro en el `log`.
     * Devuelve el mismo arreglo de `evaluar` + ['aplicado' => bool, 'estado' => ?string].
     */
    public function aplicar(RoadmapItem $item): array
    {
        $v = $this->evaluar($item);
        if (! $v['auto']) {
            return $v + ['aplicado' => false, 'estado' => null];
        }

        foreach ($v['respuestas'] as $pid => $clave) {
            $item->responderPregunta((string) $pid, (string) $clave);
        }

        // Reusa los estados que el pool YA reconoce: A auto-ejecutable, B autorizado por el revisor
        // interno. No se inventa un estado nuevo ni se toca `ejecutablesParalelo`.
        $estado = $item->nivel_riesgo === 'A' ? 'aprobado_claude' : 'aprobado_revisor';

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
}
