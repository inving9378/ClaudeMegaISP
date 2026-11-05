<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

/**
 * Seguimiento de un item desde el árbol de la Torre (pedido de Irving, 2026-10-26): explica en una
 * frase por qué no avanza, quién tiene que moverlo y —si lo que falta es una decisión o un freno—
 * lo devuelve al circuito con la opción elegida («Meter al circuito»).
 *
 * No inventa un criterio propio: usa las mismas reglas que el despachador —
 * `motivoNoDespachable()` (scopeDespachable), `esperandoDependencias()` (depende_de),
 * `idsEnCurso()` (pestaña Terminales)— y los mismos movimientos que `decidir` con `forzar`.
 * Cada situación trae sus `acciones`: lo que espera tu merge ofrece «Mergear ahora»; lo que espera
 * a otro item o a sus sub-items lista a quién espera, con la acción que mueve a cada uno, y —para
 * dependencias— «Iniciar sin esperar», que quita la dependencia (queda en el log) y lo mete.
 */
class SeguimientoItemService
{
    /** `motivo_espera` que no es una decisión sino algo material que falta (CIRC-03). */
    private const ESPERAS_DE_INSUMO = [
        'credencial'          => 'una credencial o acceso',
        'hardware'            => 'hardware o un equipo físico',
        'frontera_produccion' => 'una acción en producción',
        'autorizacion'        => 'una autorización',
        'sesion_presencial'   => 'una sesión contigo presente',
        'adjunto_faltante'    => 'un archivo adjunto que no está en disco',
    ];

    /** idsEnCurso() lee las sesiones vivas del disco: una vez por petición basta. */
    private ?array $enCurso = null;

    public function __construct(private RoadmapCircuitoService $svc)
    {
    }

    private function enCurso(): array
    {
        return $this->enCurso ??= $this->svc->idsEnCurso();
    }

    /**
     * @return array{situacion:string, titulo:string, explicacion:string, quien:string,
     *   puede_meter:bool, aviso_meter:?string, item:array, dependencias:array, hijos_abiertos:array,
     *   hijos_abiertos_total:int, preguntas:array, preguntas_pendientes:int, motivo_tecnico:?array,
     *   enlaces:array}
     */
    public function diagnostico(RoadmapItem $item): array
    {
        $base = $this->base($item) + [
            'item'      => $this->resumenItem($item),
            'preguntas' => $item->preguntasNormalizadas(),
        ];
        $diag = array_merge($base, $this->situacion($item, $base, true));
        $diag['acciones'] = $this->acciones($item, $diag);

        return $diag;
    }

    private function base(RoadmapItem $item): array
    {
        return [
            'dependencias'         => [],
            'hijos_abiertos'       => [],
            'hijos_abiertos_total' => 0,
            'preguntas_pendientes' => count($item->preguntasPendientes()),
            'motivo_tecnico'       => null,
            'aviso_meter'          => null,
            'enlaces'              => ['ficha' => '/roadmap/item/' . $item->id],
        ];
    }

    /**
     * Botones que de verdad mueven el item según su situación. El panel nunca debe quedarse solo
     * con «Abrir ficha»: si lo que falta es de otro item, la acción va en la fila de ese item.
     *
     * @return list<array{tipo:string, item_id:int, etiqueta:string, confirmar?:string}>
     */
    private function acciones(RoadmapItem $item, array $diag): array
    {
        $a = [];
        if ($diag['puede_meter']) {
            $a[] = ['tipo' => 'meter', 'item_id' => $item->id,
                'etiqueta' => $diag['situacion'] === 'en_cola' ? 'Iniciar ya' : 'Meter al circuito'];
        }
        if ($diag['situacion'] === 'espera_merge' && $item->branch) {
            $a[] = ['tipo' => 'mergear', 'item_id' => $item->id, 'etiqueta' => 'Mergear ahora'];
        }
        if ($diag['situacion'] === 'dependencia') {
            $a[] = ['tipo' => 'forzar', 'item_id' => $item->id, 'etiqueta' => 'Iniciar sin esperar',
                'confirmar' => 'Se quita la dependencia y una terminal lo toma aunque lo que espera no haya '
                    . 'terminado (puede trabajar sobre algo incompleto). Queda registrado. ¿Continuar?'];
        }

        return $a;
    }

    /** Fila de un item que frena a otro (dependencia o sub-item), con la acción que lo mueve. */
    private function fila(RoadmapItem $i): array
    {
        $d = $this->situacion($i, $this->base($i), false);
        $accion = collect($this->acciones($i, $d))->first(fn ($x) => in_array($x['tipo'], ['meter', 'mergear'], true));

        return [
            'id'          => $i->id,
            'titulo'      => $i->title,
            'estado'      => $i->estado_aprobacion,
            'situacion'   => $d['situacion'],
            'resumen'     => $d['titulo'],
            'explicacion' => $d['explicacion'],
            'accion'      => $accion ? ['etiqueta' => $accion['tipo'] === 'mergear' ? 'Mergear' : 'Meter al circuito'] + $accion : null,
        ];
    }

    /**
     * Aprueba el item con las respuestas elegidas, quita los frenos que lo dejan fuera de la cola y
     * lo marca urgente para que una terminal lo tome primero. El disparo de la vuelta lo pide el
     * controller (necesita el actor y el permiso `circuito.disparar`).
     *
     * @param  array<string,string|null>  $respuestas  {preguntaId: clave|texto|índice}
     * @return array{ok:bool, code:string, mensaje:string}
     */
    public function meterAlCircuito(RoadmapItem $item, array $respuestas, string $autor, bool $ignorarDependencias = false): array
    {
        $antes = $this->diagnostico($item);
        $forzando = $ignorarDependencias && $antes['situacion'] === 'dependencia';
        if (! $antes['puede_meter'] && ! $forzando) {
            return ['ok' => false, 'code' => $antes['situacion'], 'mensaje' => $antes['explicacion']];
        }

        // «Iniciar sin esperar»: la dependencia se quita, pero lo que había queda en el log.
        $dependenciasQuitadas = null;
        if ($forzando) {
            $dependenciasQuitadas = ['depende_de' => $item->depende_de];
            $item->depende_de = [];
            $desc = $item->descomposicion;
            if (is_array($desc) && ! empty($desc['depende_de'])) {
                $dependenciasQuitadas['posiciones'] = $desc['depende_de'];
                $desc['depende_de'] = [];
                $item->descomposicion = $desc;
            }
        }

        foreach ($respuestas as $pid => $op) {
            if ($op !== null && $op !== '' && ! $item->responderPregunta((string) $pid, (string) $op)) {
                return ['ok' => false, 'code' => 'opcion_invalida',
                    'mensaje' => "La opción elegida para la pregunta {$pid} ya no existe; recarga el seguimiento."];
            }
        }
        if ($item->exigeOpcion() && $item->preguntasPendientes() !== []) {
            $n = count($item->preguntasPendientes());

            return ['ok' => false, 'code' => 'preguntas_pendientes',
                'mensaje' => $n === 1 ? 'Elige una opción antes de meterlo al circuito.'
                    : "Responde las {$n} preguntas antes de meterlo al circuito."];
        }

        $frenoQuitado = array_filter([
            'excluir_pool_automatico' => (bool) $item->excluir_pool_automatico,
            'bloqueado_por_bucle'     => (bool) $item->bloqueado_por_bucle,
            'origen_bloqueo'          => $item->origen_bloqueo,
            'motivo_bloqueo'          => $item->motivo_bloqueo,
            'motivo_espera'           => $item->motivo_espera,
            'estado_previo'           => $item->estado_aprobacion,
        ]);

        $item->excluir_pool_automatico  = false;
        $item->bloqueado_por_bucle      = false;
        $item->escalaciones_fingerprint = null;
        $item->origen_bloqueo           = null;
        $item->motivo_bloqueo           = 'meter-al-circuito';
        if ($item->motivo_espera !== null) {
            $item->motivo_espera = null;
        }
        $item->title = trim(preg_replace('/\[(BLOCKED|PARKED)-[^\]]*\]\s*/i', '', (string) $item->title));

        // Reclamo que la terminal soltó sin cerrar: se libera para que el pool lo vuelva a tomar.
        if (! in_array((int) $item->id, $this->enCurso(), true)) {
            $item->worker_sid = null;
            $item->claimed_at = null;
            if ($item->status === 'in_progress') {
                $item->status = 'pending';
            }
        }

        $item->estado_aprobacion = 'aprobado_irving';
        $item->aprobado_por      = $autor;
        $item->revisado_at       = now();
        $item->urgente           = true;
        $item->urgente_at        = now();
        $item->urgente_by        = $autor;
        $item->priority          = 'alta';
        $item->log = array_merge($item->log ?: [], [[
            'ts'            => now()->toIso8601String(),
            'por'           => $autor,
            'evento'        => 'metido_al_circuito',
            'situacion'     => $antes['situacion'],
            'respuestas'    => $respuestas ?: null,
            'freno_quitado' => $frenoQuitado ?: null,
            'dependencias_quitadas' => $dependenciasQuitadas,
        ]]);
        $item->save();

        // Post-condición contra la MISMA regla del despachador (igual que decidir): si algo más lo
        // frena, se dice cuál — la aprobación ya quedó escrita.
        $bloqueo = $item->fresh()->motivoNoDespachable();
        if ($bloqueo !== null) {
            return ['ok' => false, 'code' => $bloqueo['code'],
                'mensaje' => 'Quedó aprobado, pero todavía no es tomable: ' . $bloqueo['error']];
        }

        return ['ok' => true, 'code' => 'en_cola',
            'mensaje' => 'Listo: quedó aprobado, sin frenos y al frente de la cola. Una terminal libre lo toma en el próximo ciclo.'];
    }

    /** Situación del item en orden de especificidad: lo que lo frena de verdad, primero. */
    private function situacion(RoadmapItem $item, array $base, bool $conFilas): array
    {
        if ($item->status === 'done' || $item->estado_aprobacion === 'completado') {
            return $this->s('cerrado', 'Hecho', 'Este item ya está cerrado.', 'nadie');
        }
        if ($item->status === 'cancelled' || in_array($item->estado_aprobacion, ['cancelado', 'rechazado'], true)) {
            return $this->s('descartado', 'Cancelado', 'Este item se canceló. Si hay trabajo nuevo, crea un item de seguimiento desde su ficha.', 'nadie');
        }

        $enCurso = in_array((int) $item->id, $this->enCurso(), true);
        if ($enCurso) {
            $r = $this->s('trabajando', 'Trabajando',
                'La terminal ' . ($item->worker_sid ?: '—') . ' lo está trabajando ahora.', 'circuito');
            $r['enlaces'] = $base['enlaces'] + ['terminales' => '/releases?tab=terminales'];

            return $r;
        }

        // Ya mezclado pero abierto (proyecto con sub-items): la bandera de merge quedó puesta, pero lo
        // que falta son los sub-items — eso lo explica el bloque de abajo, no «Mergear ahora».
        if ($item->esperando_merge_irving && ! $item->merge_commit) {
            $r = $this->s('espera_merge', 'Espera tu merge',
                'El trabajo ya está hecho en la rama ' . ($item->branch ?: '—') . '. Solo falta mezclarlo: '
                . 'usa «Mergear ahora» (o la pestaña Integración para ver el diff antes).', 'tu');
            $r['enlaces'] = $base['enlaces'] + ['integracion' => '/releases?tab=integracion'];

            return $r;
        }

        $hijos = RoadmapItem::where('origen_item_id', $item->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado']);
        $totalHijos = (clone $hijos)->count();
        if ($totalHijos > 0) {
            $r = $this->s('paraguas', 'Espera a sus sub-items',
                "Es un proyecto con {$totalHijos} sub-item(s) abierto(s): se cierra solo cuando terminen. "
                . 'Abajo está cada uno con lo que lo mueve.', 'otro_item');
            $r['hijos_abiertos_total'] = $totalHijos;
            if ($conFilas) {
                $r['hijos_abiertos'] = $hijos->orderBy('position')->orderBy('id')->limit(25)->get()
                    ->map(fn ($h) => $this->fila($h))->values()->all();
            }

            return $r;
        }

        $deps = $this->svc->esperandoDependencias((int) $item->id);
        $motivo = $item->motivoNoDespachable();
        if ($deps !== null || ($motivo['code'] ?? null) === 'bloqueado_por_dependencia') {
            $r = $this->s('dependencia', 'Espera a otro item',
                rtrim($deps['texto'] ?? $motivo['error'], '.') . '. Se libera solo cuando eso termine; para '
                . 'acelerarlo mueve el item que lo frena, o inícialo sin esperar.', 'otro_item');
            if ($conFilas) {
                $r['dependencias'] = $this->bloqueantes($item, $deps)->map(fn ($d) => $this->fila($d))->values()->all();
            }
            $r['motivo_tecnico'] = $motivo;

            return $r;
        }

        // Después de las dependencias: un reclamo colgado de algo que igual espera a otro item se
        // explica por lo que espera (el vigilante suelta el reclamo solo).
        if ($item->estado_aprobacion === 'en_progreso') {
            return $this->s('reclamo_huerfano', 'Terminal lo soltó',
                'La terminal ' . ($item->worker_sid ?: '—') . ' lo reclamó pero ya no lo está trabajando. '
                . 'El vigilante lo devuelve a la cola en unos minutos; si no quieres esperar, mételo al circuito.',
                'circuito', true);
        }

        if ($item->colision_pausada_por) {
            return $this->s('colision', 'Pausado por choque',
                "Otro item en curso (#{$item->colision_pausada_por}) toca los mismos archivos. "
                . 'Se reanuda solo cuando ese termine.', 'otro_item');
        }
        if ($item->requiere_sesion_supervisada) {
            return $this->s('sesion', 'Necesita sesión contigo',
                'Está marcado para trabajarse contigo presente; una terminal no lo toma sola.', 'tu');
        }
        if ($item->en_desarrollo_humano) {
            return $this->s('humano', 'Lo trabaja una persona',
                'Está marcado como trabajo humano en curso; el circuito no lo toca hasta que se libere.', 'tu');
        }
        if ($item->agendado_para && $item->agendado_para->isFuture()) {
            return $this->s('agendado', 'Agendado',
                'Está agendado para el ' . $item->agendado_para->format('d/m/Y H:i') . '; vuelve a la cola solo ese día.', 'nadie');
        }

        // Una decisión ya contestada (opción elegida) no es «espera tu decisión» aunque el item
        // conserve motivo_espera=decision: lo que lo frena entonces es el estado, abajo.
        $pendientes = $base['preguntas_pendientes'];
        $decisionSinOpciones = $item->motivo_espera === 'decision' && ! $item->exigeOpcion()
            && $item->estado_aprobacion === 'requiere_irving';
        if ($pendientes > 0 || $decisionSinOpciones) {
            return $this->s('decision', 'Espera tu decisión',
                $pendientes > 0
                    ? ($pendientes === 1 ? 'Elige una opción' : "Responde las {$pendientes} preguntas")
                        . ' y mételo al circuito; una terminal lo resuelve con lo que elijas.'
                    : 'Espera tu decisión, pero no trae opciones escritas. Puedes meterlo tal cual o dejar una indicación en su ficha.',
                'tu', true);
        }

        if (isset(self::ESPERAS_DE_INSUMO[$item->motivo_espera])) {
            $r = $this->s('insumo', 'Espera algo de ti',
                'Espera ' . self::ESPERAS_DE_INSUMO[$item->motivo_espera]
                . ($item->motivo_bloqueo ? ' (' . $item->motivo_bloqueo . ')' : '') . '.', 'tu', true);
            $r['aviso_meter'] = 'Mételo solo si eso ya está resuelto; si no, la terminal volverá a escalarlo.';

            return $r;
        }

        if ($motivo !== null) {
            [$titulo, $texto] = match ($motivo['code']) {
                'fuera_del_pool', 'freno_humano' => ['Aprobado pero fuera de la cola',
                    'Está fuera de la cola' . ($item->motivo_bloqueo ? ' (' . $item->motivo_bloqueo . ')' : '')
                    . '. Aprobarlo no lo regresa; ninguna terminal lo va a tomar hasta que lo metas al circuito.'],
                'bloqueado_por_bucle' => ['Frenado por repetición',
                    'Se frenó porque escaló varias veces por la misma causa. Mételo solo si ya cambió algo (decisión, alcance o indicación).'],
                'no_despachable' => $item->estado_aprobacion === 'aprobado_revisor' && ! $this->svc->revisorEnabled()
                    ? ['Aprobado, pero no se despacha', 'Quedó como «aprobado por el revisor», pero el revisor automático está apagado y esos aprobados no se despachan. Mételo al circuito para que lo tome una terminal.']
                    : ['Espera tu visto bueno', 'Necesita tu aprobación para que una terminal lo tome (estado ' . $item->estado_aprobacion . ', nivel ' . ($item->nivel_riesgo ?: '—') . ').'],
                default => ['No avanza', (string) $motivo['error']],
            };
            $r = $this->s('frenado', $titulo, $texto, 'tu', true);
            $r['motivo_tecnico'] = $motivo;

            return $r;
        }

        return $this->s('en_cola', 'En cola',
            'Está en la cola; una terminal libre lo toma en el próximo ciclo. Si lo metes al circuito pasa al frente.',
            'circuito', true);
    }

    /** Items que frenan a éste: por `depende_de` o, en una descomposición, por posición entre hermanos. */
    private function bloqueantes(RoadmapItem $item, ?array $deps): \Illuminate\Support\Collection
    {
        if (! empty($deps['faltan'])) {
            return RoadmapItem::whereIn('id', $deps['faltan'])->get();
        }
        if (! $item->origen_item_id) {
            return collect();
        }
        $gate = app(Descomposicion\DependenciaGate::class);
        $posiciones = $gate->dependeDeDe($item);
        $estados = RoadmapItem::where('origen_item_id', $item->origen_item_id)->whereIn('position', $posiciones)
            ->pluck('estado_aprobacion', 'position')->map(fn ($e) => (string) $e)->all();

        return RoadmapItem::where('origen_item_id', $item->origen_item_id)
            ->whereIn('position', $gate->bloqueadaPor($posiciones, $estados))->get();
    }

    private function s(string $situacion, string $titulo, string $explicacion, string $quien, bool $puedeMeter = false): array
    {
        return compact('situacion', 'titulo', 'explicacion', 'quien') + ['puede_meter' => $puedeMeter];
    }

    private function resumenItem(RoadmapItem $item): array
    {
        $origen = $item->origen_item_id ? RoadmapItem::find($item->origen_item_id, ['id', 'title']) : null;

        return [
            'id'             => $item->id,
            'title'          => $item->title,
            'modulo'         => $item->modulo,
            'estado'         => $item->estado_aprobacion,
            'status'         => $item->status,
            'nivel'          => $item->nivel_riesgo,
            'worker_sid'     => $item->worker_sid,
            'branch'         => $item->branch,
            'urgente'        => (bool) $item->urgente,
            'origen_id'      => $origen?->id,
            'origen_titulo'  => $origen?->title,
        ];
    }
}
