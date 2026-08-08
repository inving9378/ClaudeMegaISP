<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

class RoadmapItem extends Model
{
    protected $table = 'roadmap_items';

    protected $fillable = [
        'title', 'description', 'status', 'priority',
        'target_version', 'prompt', 'position',
        'started_at', 'completed_at',
        'subtasks', 'log',
        // Circuito de mejora continua (Parte 1.1)
        'modulo', 'nivel_riesgo', 'estado_aprobacion',
        // Quién fijó el nivel_riesgo vigente: interno|externo (circuito #260)
        'nivel_riesgo_origen',
        'comentarios_claude', 'revisado_at', 'aprobado_por',
        // Reportes + deep-link de revisión (#427 / #432 ADENDA B)
        'reporte_tecnico', 'reporte_coloquial', 'enlace_revision',
        // Bandeja de decisiones interactiva (#313) + brief multi-pregunta (#432 Fase 3)
        'opciones', 'opcion_elegida', 'preguntas',
        // Aislamiento por rama (#311)
        'branch', 'merge_commit',
        // Acciones avanzadas de la bandeja (#320)
        'origen_item_id',
        // Integración robusta (#325)
        'marcado_version',
        // Disparo/urgente (#337)
        'urgente', 'urgente_at', 'urgente_by',
        // Candado anti-colisión (#341)
        'en_desarrollo_humano',
        // Clasificación UI/backend + ciclo de vida de archivo (#334)
        'revision_ui', 'ui_hint', 'archivado_at', 'archivado_por',
        // Firma del worker que lo reclamó/ejecutó — wt-K (#334 A)
        'worker_sid',
        // Colisión en vuelo entre dos items paralelos (#438)
        'colision_pausada_por', 'colision_pausada_at',
        // FASE 1 — Validación funcional por Irving (revisa el resultado, no el código)
        'validacion_funcional_requerida', 'pendiente_validacion_irving', 'validado_por_irving',
        'validado_at', 'validado_por', 'comentario_validacion', 'revision_tecnica', 'validacion_brief',
        // #507 anti-bucle — parqueo de items que YA no son ejecutables por un worker
        'excluir_pool_automatico', 'decision_resuelta', 'requiere_sesion_supervisada',
        'bloqueado_por_bucle', 'motivo_bloqueo', 'escalaciones_fingerprint', 'esperando_merge_irving',
        // TORRE V2 — canal de consulta terminal → Thomas (autoridad intermedia antes de Irving)
        'consulta_supervisor', 'consulta_supervisor_sid', 'consulta_supervisor_at', 'consulta_opciones',
        'consulta_respuesta', 'consulta_resuelta_at', 'consulta_resuelta_por',
        // Estimación de esfuerzo del reparto (orientativa, nunca bloqueante)
        'eta_minutos', 'eta_asignada_at',
        // #561 — contador de reclamos fallidos (reap) antes de escalar a Irving
        'reap_count',
        // #559 — huella del Motor de Auditoría Continua (dedup contra abiertos Y cerrados)
        'auditor_fingerprint',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'revisado_at'  => 'datetime',
        'position'     => 'integer',
        'subtasks'     => 'array',
        'log'          => 'array',
        'opciones'     => 'array',
        'preguntas'    => 'array',
        'marcado_version' => 'boolean',
        'urgente'      => 'boolean',
        'urgente_at'   => 'datetime',
        'en_desarrollo_humano' => 'boolean',
        'revision_ui'  => 'boolean',
        'archivado_at' => 'datetime',
        'colision_pausada_at' => 'datetime',
        // FASE 1 — Validación funcional por Irving
        'validacion_funcional_requerida' => 'boolean',
        'pendiente_validacion_irving'    => 'boolean',
        'validado_por_irving'            => 'boolean',
        'revision_tecnica'               => 'boolean',
        'validado_at'                    => 'datetime',
        'validacion_brief'               => 'array',
        // #507 anti-bucle
        'excluir_pool_automatico'     => 'boolean',
        'decision_resuelta'           => 'boolean',
        'requiere_sesion_supervisada' => 'boolean',
        'bloqueado_por_bucle'         => 'boolean',
        'esperando_merge_irving'      => 'boolean',
        'escalaciones_fingerprint'    => 'array',
        // TORRE V2 — consulta a Thomas
        'consulta_supervisor_at'      => 'datetime',
        'consulta_resuelta_at'        => 'datetime',
        'consulta_opciones'           => 'array',
        'eta_asignada_at'             => 'datetime',
        'reap_count'                  => 'integer',
    ];

    // Enums del circuito (fuente de verdad para validación en el endpoint externo)
    public const NIVELES_RIESGO = ['A', 'B', 'C'];

    public const ESTADOS_APROBACION = [
        'pendiente_revision',
        'aprobado_claude',
        'aprobado_revisor',   // #338: autorizado por el revisor adversarial (B técnico seguro)
        'requiere_irving',
        'aprobado_irving',
        'rechazado',
        'en_progreso',
        'completado',
        'cancelado',
    ];

    /**
     * #507 anti-bucle — si la MISMA causa de escalación se repite esta cantidad de veces sin que
     * cambie nada material (rama, opción elegida, nivel, preguntas), el item sale del pool
     * automático (`bloqueado_por_bucle`) y deja de quemar workers. Sigue visible para Irving.
     */
    public const ESCALACION_BUCLE_UMBRAL = 3;

    protected $attributes = [
        'subtasks' => '[]',
        'log'      => '[]',
    ];

    /**
     * #507 — bandera TRANSITORIA (no persistida, propiedad PHP real): la activa SOLO el cierre
     * MANUAL de Irving (`RoadmapController::decidir` accion=cerrar/cancelar) para permitir cerrar un
     * nivel C con rama sin merge. Sin ella, el guard de abajo retendría ese cierre en
     * `esperando_merge_irving`.
     */
    public bool $cierreManualIrving = false;

    /**
     * #420: guard de cierre — cualquier save() que deje estado_aprobacion=completado sincroniza
     * status=done + completed_at. Evita que un cierre (tinker, endpoint, merge) deje status=pending
     * colgado, que es justo lo que inflaba el contador "Pendientes" de la Torre (cuenta por status).
     */
    protected static function booted(): void
    {
        // #456: guardia simétrica al #420 — causa raíz de la bandeja pendiente_revision llenándose de
        // items done/in_progress. Las acciones MANUALES del Kanban legado (RoadmapController::start/
        // complete/cancel, disparadas por el toggle de estado en RoadmapTab.vue) solo mutan `status` y
        // nunca tocaron `estado_aprobacion`, que nace en 'pendiente_revision' para TODO item nuevo
        // (incluidos los creados a mano vía "Agregar item"). Si `status` avanza mientras
        // estado_aprobacion sigue en 'pendiente_revision' o en 'en_progreso' (el propio resultado de
        // start() vía esta guardia, o un claim del circuito), sincroniza para que el item salga de la
        // bandeja en vez de quedar "hecho" pero eternamente pendiente de revisión. Estados de decisión
        // ya gobernados por el circuito (aprobado_*, requiere_irving, completado, cancelado, rechazado)
        // NUNCA se tocan aquí — solo el tramo puramente Kanban.
        static::saving(function (self $item) {
            if (in_array($item->estado_aprobacion, ['pendiente_revision', 'en_progreso'], true) && $item->isDirty('status')) {
                if ($item->status === 'done') {
                    $item->estado_aprobacion = 'completado';
                } elseif ($item->status === 'in_progress') {
                    $item->estado_aprobacion = 'en_progreso';
                } elseif ($item->status === 'cancelled') {
                    $item->estado_aprobacion = 'cancelado';
                }
            }
        });

        // #507 anti-bucle — DEBE ir ANTES del guard #420 de abajo: si reruteamos un cierre optimista
        // de nivel C a "esperando merge", el #420 (que reacciona a estado==='completado') ya no debe
        // forzarle status=done.
        static::saving(function (self $item) {
            // (1) Un nivel C CON rama que intenta cerrar a 'completado' SIN merge real
            // (merge_commit vacío) NO está terminado: su trabajo espera el merge MANUAL de Irving.
            // Se PARQUEA (esperando_merge_irving + fuera del pool) en vez de cerrarse o volver a la
            // bandeja. El MergeRunner —único que trae merge_commit— sí lo cierra a completado.
            // El cierre MANUAL de Irving se respeta ($cierreManualIrving).
            if ($item->isDirty('estado_aprobacion')
                && $item->estado_aprobacion === 'completado'
                && ! $item->cierreManualIrving
                && empty($item->merge_commit)
                && $item->nivel_riesgo === 'C'
                && ! empty($item->branch)) {
                $item->estado_aprobacion       = 'aprobado_irving';   // sigue autorizado; NO es bandeja
                $item->esperando_merge_irving  = true;
                $item->decision_resuelta       = true;
                $item->excluir_pool_automatico = true;
                $item->status                  = 'pending';
            }

            // (2) Al ENTRAR a requiere_irving por cualquier camino automático, cuenta repeticiones de
            // la MISMA causa. Si se repite ESCALACION_BUCLE_UMBRAL veces sin cambio material, sale
            // del pool (sigue en la bandeja, pero ningún worker lo re-toma).
            if ($item->exists
                && $item->isDirty('estado_aprobacion')
                && $item->estado_aprobacion === 'requiere_irving'
                && $item->getOriginal('estado_aprobacion') !== 'requiere_irving') {
                $item->contarEscalacion();
            }
        });

        static::saving(function (self $item) {
            if ($item->estado_aprobacion === 'completado') {
                if ($item->status !== 'done') {
                    $item->status = 'done';
                }
                if (! $item->completed_at) {
                    $item->completed_at = now();
                }
            }
        });

        // #427: todo item nuevo nace con reporte_coloquial + modulo (nunca null). nivel_riesgo
        // se deja fuera a propósito: null es su estado "sin triajear" y el circuito solo puede
        // ENDURECERLO (A→B→C) después — forzar un default lo dejaría atascado para siempre.
        static::creating(function (self $item) {
            if (trim((string) $item->reporte_coloquial) === '') {
                $item->reporte_coloquial = static::generarReporteColoquial($item->title, $item->description);
            }
            if (trim((string) $item->modulo) === '') {
                $item->modulo = 'Sin clasificar';
            }
        });
    }

    /** Resumen ~40 palabras para "Escuchar" (#427). Idéntico criterio que RoadmapController::resumenItem. */
    public static function generarReporteColoquial(?string $title, ?string $description): string
    {
        $texto = trim(($title ?? '') . '. ' . ($description ?? ''), " .\t\n\r\0\x0B");
        if ($texto === '') {
            return '';
        }
        $palabras = preg_split('/\s+/', $texto);

        return count($palabras) > 40 ? implode(' ', array_slice($palabras, 0, 40)) . '…' : $texto;
    }

    /**
     * #341 (anti-colisión): ¿este item lo está trabajando un humano/otra sesión? El circuito
     * autónomo NUNCA debe tomarlo para una vuelta. Señal doble: estado en_progreso (alguien lo
     * trabaja) O bandera explícita en_desarrollo_humano (candado manual). Fuente única del guard.
     */
    public function estaEnDesarrollo(): bool
    {
        return $this->estado_aprobacion === 'en_progreso' || (bool) $this->en_desarrollo_humano;
    }

    /**
     * Fase 0 (anti-rebote): ¿este item ya trae una decisión VIGENTE de Irving? Un proceso automático
     * (re-lectura de señales, priorización) NO debe devolverlo a `requiere_irving` salvo que exista un
     * hallazgo NUEVO y MATERIAL (p.ej. conflicto real de merge, que sí lo escala legítimamente desde
     * otro camino). Señal doble, sin depender de columnas nuevas todavía:
     *   1) estado ya aprobado (aprobado_irving|aprobado_revisor|aprobado_claude), o
     *   2) la ÚLTIMA acción de Irving en la bitácora fue una aprobación (aún no revertida).
     */
    public function tieneDecisionVigenteDeIrving(): bool
    {
        if (in_array($this->estado_aprobacion, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true)) {
            return true;
        }
        foreach (array_reverse((array) $this->log) as $e) {
            if (! is_array($e) || strpos((string) ($e['por'] ?? ''), 'irving') === false) {
                continue;   // solo cuenta la última entrada hecha por Irving
            }
            return ($e['decision'] ?? '') === 'aprobar';
        }

        return false;
    }

    // ── #507 anti-bucle — un item YA decidido nunca vuelve al pool de reclamo ────────────────────

    /** ¿El título lleva rótulo de frontera dura ([BLOCKED-…] / [PARKED-…])? */
    public function tieneRotuloBloqueo(): bool
    {
        return (bool) preg_match('/\[(BLOCKED|PARKED)-/i', (string) $this->title);
    }

    /**
     * Huella ESTABLE de la causa de escalación: deriva de lo MATERIAL (rama, opción elegida, nivel,
     * preguntas). No incluye timestamps ni prosa que crece cada vuelta → dos escalaciones "por lo
     * mismo" comparten huella; un cambio real (otra opción, código en otra rama, preguntas nuevas)
     * la cambia y REINICIA el contador.
     */
    public function escalacionFingerprint(): string
    {
        return substr(sha1(implode('|', [
            (string) $this->branch,
            (string) $this->opcion_elegida,
            (string) $this->nivel_riesgo,
            $this->preguntas ? json_encode($this->preguntas) : '',
        ])), 0, 40);
    }

    /**
     * Registra UNA escalación y aplica el anti-bucle. Huella igual a la previa → incrementa; distinta
     * → reinicia a 1. Al llegar al umbral marca `bloqueado_por_bucle` + `excluir_pool_automatico`.
     * SOLO muta atributos (se persiste en el save en curso); nunca guarda por su cuenta.
     */
    public function contarEscalacion(): void
    {
        $fp     = $this->escalacionFingerprint();
        $estado = is_array($this->escalaciones_fingerprint) ? $this->escalaciones_fingerprint : [];
        $n      = (($estado['fingerprint'] ?? null) === $fp) ? ((int) ($estado['count'] ?? 0)) + 1 : 1;

        $this->escalaciones_fingerprint = ['fingerprint' => $fp, 'count' => $n, 'ultima' => now()->toIso8601String()];

        if ($n >= self::ESCALACION_BUCLE_UMBRAL) {
            $this->bloqueado_por_bucle     = true;
            $this->excluir_pool_automatico = true;
            if (trim((string) $this->motivo_bloqueo) === '') {
                $this->motivo_bloqueo = "Anti-bucle: {$n} escalaciones seguidas por la MISMA causa sin cambio material. "
                    . 'Fuera del pool automático hasta que cambie algo (decisión, rama, alcance) o lo destrabes a mano.';
            }
        }
    }

    /**
     * GUARD ÚNICO de despacho: qué puede reclamar un worker. Un proceso automático (scheduler /
     * claim-next / destrabe / priorizar) SOLO toca items que no esperan una acción HUMANA.
     * Excluye: parqueados esperando merge manual, excluidos del pool (sesión supervisada, bucle) y
     * los rotulados [BLOCKED-…]/[PARKED-…] (frontera dura: desbloquear = QUITAR el rótulo, no
     * aprobar con el rótulo puesto).
     */
    public function scopeElegibleParaPool($query)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('excluir_pool_automatico')->orWhere('excluir_pool_automatico', false))
            ->where(fn ($q) => $q->whereNull('esperando_merge_irving')->orWhere('esperando_merge_irving', false))
            ->where('title', 'not like', '%[BLOCKED-%')
            ->where('title', 'not like', '%[PARKED-%');
    }

    /**
     * #431 Fase 1 — CLAVE ESTABLE de una opción (no su prosa). Deriva de un hash del texto
     * normalizado → sobrevive al reordenamiento de las opciones (NO es índice posicional) y cabe
     * de sobra en la columna (16 chars), matando el bug de `max:255` con opciones largas (la prosa
     * de un fork C llega a 655 chars → 422 silencioso). Lo que se PERSISTE en `opcion_elegida` es
     * esta clave; la prosa vive solo en `opciones`.
     */
    public static function claveOpcion(string $texto): string
    {
        return substr(sha1(trim(preg_replace('/\s+/', ' ', $texto))), 0, 16);
    }

    /** Opciones enriquecidas para la UI: [{clave, texto, recomendada}]. `opciones` = array de prosa. */
    public function opcionesDetalladas(): array
    {
        $out = [];
        foreach ((array) ($this->opciones ?? []) as $texto) {
            $texto = trim((string) $texto);
            if ($texto === '') {
                continue;
            }
            $out[] = [
                'clave'       => static::claveOpcion($texto),
                'texto'       => $texto,
                'recomendada' => stripos($texto, 'RECOMENDADA') !== false,
                // #507 sub-paso 1 — el modelo viejo (`opciones`: strings planos) NO trae datos
                // estructurados. `null` = SIN DATO (no "false"): el autopilot exige el dato
                // explícito, así que un item legacy nunca se auto-ejecuta por omisión.
                'confianza'   => null,
                'reversible'  => null,
            ];
        }

        return $out;
    }

    /**
     * Resuelve la entrada del usuario (una clave estable, la prosa completa, o un índice "0/1/2")
     * a la CLAVE de una opción REAL de este item. Devuelve null si no corresponde a ninguna (el
     * controlador lo trata como "opción inválida / faltante"). NUNCA devuelve prosa.
     */
    public function resolverClaveOpcion(?string $input): ?string
    {
        $input = $input !== null ? trim($input) : '';
        if ($input === '') {
            return null;
        }
        $det = $this->opcionesDetalladas();
        if (empty($det)) {
            return null;
        }
        foreach ($det as $o) {                       // 1) ya es una clave válida
            if ($o['clave'] === $input) {
                return $o['clave'];
            }
        }
        foreach ($det as $o) {                       // 2) es la prosa (exacta o normalizada)
            if ($o['texto'] === $input || static::claveOpcion($o['texto']) === static::claveOpcion($input)) {
                return $o['clave'];
            }
        }
        if (ctype_digit($input)) {                    // 3) índice posicional "0".."n"
            $i = (int) $input;
            if (isset($det[$i])) {
                return $det[$i]['clave'];
            }
        }

        return null;
    }

    /** ¿Este item EXIGE decidir antes de aprobar? = alguna pregunta trae opciones (multi o legacy). */
    public function exigeOpcion(): bool
    {
        foreach ($this->preguntasNormalizadas() as $p) {
            if (! empty($p['opciones'])) {
                return true;
            }
        }

        return false;
    }

    // ── #432 Fase 3 — BRIEF MULTI-PREGUNTA ──────────────────────────────────────────────────────

    /**
     * #507 sub-paso 1 — lector ESTRICTO de los booleanos del brief (`recomendada`, `reversible`).
     * Devuelve null si la llave no viene (SIN DATO) y false ante cualquier valor que no sea un
     * `true` inequívoco. Existe porque la coerción de PHP falla justo hacia el lado peligroso:
     * `(bool) "si"` y `! empty("false")` dan TRUE, y con eso el autopilot leería como "reversible"
     * una opción que el modelo nunca afirmó que lo fuera. Ante cualquier ambigüedad: false.
     */
    public static function boolEstricto(array $src, string $llave): ?bool
    {
        if (! array_key_exists($llave, $src)) {
            return null;   // sin dato
        }
        $v = $src[$llave];
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v)) {
            return $v === 1;
        }
        if (is_string($v)) {
            return in_array(strtolower(trim($v)), ['true', '1'], true);
        }

        return false;
    }

    /** ¿Modelo multi-pregunta activo? Feature flag con fallback al campo viejo (`opciones`). */
    public static function multiPreguntaEnabled(): bool
    {
        return (bool) config('circuito.multi_pregunta', true);
    }

    /**
     * Preguntas NORMALIZADAS para la bandeja/UI: SIEMPRE una lista uniforme
     * [{id, pregunta, opciones:[{clave,texto,recomendada}], opcion_elegida, fase}].
     * - flag ON + `preguntas` presentes → las usa (rellena claves estables).
     * - si no (item viejo / flag OFF) → sintetiza UNA pregunta desde `opciones`/`opcion_elegida`
     *   (fallback), para que la UI sea idéntica sin importar el origen.
     */
    public function preguntasNormalizadas(): array
    {
        if (static::multiPreguntaEnabled() && ! empty($this->preguntas) && is_array($this->preguntas)) {
            $out = [];
            foreach ($this->preguntas as $idx => $p) {
                $ops = [];
                foreach ((array) ($p['opciones'] ?? []) as $o) {
                    $t = is_array($o) ? trim((string) ($o['texto'] ?? '')) : trim((string) $o);
                    if ($t === '') {
                        continue;
                    }
                    // #507 sub-paso 1 — datos ESTRUCTURADOS por opción (los emite el Revisor en el
                    // brief). `confianza`/`reversible` en null = SIN DATO: los items legacy (los 47
                    // ya poblados) no los traen y el autopilot los trata como "no auto-ejecutable",
                    // nunca como permiso. El `stripos` sigue de fallback SOLO para `recomendada`.
                    $conf = is_array($o) ? strtolower(trim((string) ($o['confianza'] ?? ''))) : '';
                    $ops[] = [
                        'clave'       => static::claveOpcion($t),
                        'texto'       => $t,
                        'recomendada' => (is_array($o) && static::boolEstricto($o, 'recomendada') === true)
                                         || stripos($t, 'RECOMENDADA') !== false,
                        'confianza'   => in_array($conf, ['alta', 'media', 'baja'], true) ? $conf : null,
                        'reversible'  => is_array($o) ? static::boolEstricto($o, 'reversible') : null,
                    ];
                }
                $out[] = [
                    'id'             => (string) ($p['id'] ?? ('q' . ($idx + 1))),
                    'pregunta'       => trim((string) ($p['pregunta'] ?? '')),
                    'opciones'       => $ops,
                    'opcion_elegida' => $p['opcion_elegida'] ?? null,
                    'fase'           => $p['fase'] ?? null,
                    // #507 — el Revisor marca la pregunta que NO puede resolver con seguridad:
                    // aunque haya recomendada de alta confianza, esta pregunta es de Irving.
                    'requiere_irving' => static::boolEstricto($p, 'requiere_irving') === true,
                ];
            }

            return $out;
        }

        // Fallback: una sola pregunta desde el campo viejo.
        $ops = $this->opcionesDetalladas();
        if (empty($ops)) {
            return [];
        }

        return [[
            'id'             => 'q1',
            'pregunta'       => '',   // pregunta implícita = la recomendación/comentario del item
            'opciones'       => $ops,
            'opcion_elegida' => $this->opcion_elegida,
            'fase'           => null,
            'requiere_irving' => false,   // #507 — sin dato en el modelo viejo; decide el nivel/confianza
        ]];
    }

    /** IDs de preguntas SIN responder (guard "responde todas antes de aprobar"). */
    public function preguntasPendientes(): array
    {
        $pend = [];
        foreach ($this->preguntasNormalizadas() as $p) {
            if (empty($p['opciones'])) {
                continue;   // una pregunta sin opciones no bloquea
            }
            if (empty($p['opcion_elegida'])) {
                $pend[] = $p['id'];
            }
        }

        return $pend;
    }

    /**
     * Registra la respuesta a UNA pregunta (por id), resolviendo la entrada a la CLAVE estable de
     * una opción REAL de ESA pregunta. Persiste en `preguntas` (y espeja al legacy `opcion_elegida`
     * la primera pregunta) o en el legacy si es el modelo viejo. Devuelve true si resolvió.
     */
    public function responderPregunta(string $preguntaId, ?string $inputOpcion): bool
    {
        $preguntas = $this->preguntasNormalizadas();
        $target    = null;
        foreach ($preguntas as $p) {
            if ($p['id'] === $preguntaId) {
                $target = $p;
                break;
            }
        }
        if ($target === null) {
            return false;
        }

        // Resolver input → clave dentro de ESA pregunta (clave / prosa / índice).
        $clave = null;
        $input = $inputOpcion !== null ? trim($inputOpcion) : '';
        if ($input !== '') {
            foreach ($target['opciones'] as $o) {
                if ($o['clave'] === $input || $o['texto'] === $input || static::claveOpcion($o['texto']) === static::claveOpcion($input)) {
                    $clave = $o['clave'];
                    break;
                }
            }
            if ($clave === null && ctype_digit($input) && isset($target['opciones'][(int) $input])) {
                $clave = $target['opciones'][(int) $input]['clave'];
            }
            if ($clave === null) {
                return false;   // input inválido para esta pregunta
            }
        }

        if (static::multiPreguntaEnabled() && ! empty($this->preguntas) && is_array($this->preguntas)) {
            $preg = $this->preguntas;
            foreach ($preg as &$p) {
                if ((string) ($p['id'] ?? '') === $preguntaId) {
                    $p['opcion_elegida'] = $clave;
                    break;
                }
            }
            unset($p);
            $this->preguntas = $preg;
            if ($preguntaId === ($preguntas[0]['id'] ?? null)) {
                $this->opcion_elegida = $clave;   // espejo al legacy (primera pregunta)
            }
        } else {
            $this->opcion_elegida = $clave;       // fallback una-pregunta
        }

        return true;
    }

    /**
     * FASE 1 — Cambios seguros YA integrados que esperan la validación funcional de Irving
     * (revisa el resultado, no el código). Alimenta la sección "Cambios para que Irving pruebe".
     */
    public function scopePendienteValidacion($query)
    {
        return $query->where('pendiente_validacion_irving', true)
                     ->whereNull('archivado_at')
                     ->orderByDesc('updated_at');
    }

    /** Items que el circuito SÍ puede tomar (excluye en_progreso y candados humanos). #341 */
    public function scopeTomablePorCircuito($query)
    {
        return $query->where('estado_aprobacion', '!=', 'en_progreso')
                     ->where(function ($q) {
                         $q->whereNull('en_desarrollo_humano')->orWhere('en_desarrollo_humano', false);
                     });
    }

    /**
     * #348: items que el circuito AUTO-EJECUTA sin una nueva decisión de Irving —
     * nivel A/B (aún sin proponer) o cualquiera YA aprobado por Irving (aprobado_irving).
     * Excluye requiere_irving (bandeja), terminales y candados humanos (#341, vía tomable).
     */
    public function scopeAutoEjecutable($query)
    {
        return $query->where('status', 'pending')
                     ->tomablePorCircuito()
                     ->elegibleParaPool()   // #507: nunca un item parqueado (espera-merge / bucle / rotulado)
                     ->whereNotIn('estado_aprobacion', ['requiere_irving', 'rechazado', 'completado', 'cancelado'])
                     ->where(function ($q) {
                         $q->whereIn('nivel_riesgo', ['A', 'B'])
                           ->orWhere('estado_aprobacion', 'aprobado_irving');
                     });
    }

    /**
     * #348: items que ESPERAN la decisión de Irving (el circuito NO los corre solo):
     * los de la bandeja (requiere_irving, cualquier nivel) + los de negocio/diseño
     * (nivel C aún sin aprobar). Terminales excluidos.
     */
    public function scopeEsperaDecision($query)
    {
        return $query->where('status', 'pending')
                     ->tomablePorCircuito()
                     ->whereNotIn('estado_aprobacion', ['rechazado', 'completado', 'cancelado'])
                     ->where(function ($q) {
                         $q->where('estado_aprobacion', 'requiere_irving')
                           ->orWhere(function ($q2) {
                               $q2->where('nivel_riesgo', 'C')
                                  ->where('estado_aprobacion', '!=', 'aprobado_irving');
                           });
                     });
    }

    /**
     * #313: items C en la bandeja (requiere_irving) que AÚN no traen opciones — los únicos que el
     * generador (circuito:proponer-opciones) puede tocar. Fuente ÚNICA del invariante "C sin
     * opciones": un C debería nacer con opciones + opcion_elegida ya resueltas; este scope marca
     * los que quedaron sin ellas para proponérselas SIN pisar los que ya las traen.
     */
    public function scopeCSinOpciones($query)
    {
        return $query->where('nivel_riesgo', 'C')
                     ->where('estado_aprobacion', 'requiere_irving')
                     ->whereNotIn('status', ['done', 'cancelled'])
                     ->where(function ($w) {
                         $w->whereNull('opciones')->orWhere('opciones', '[]')->orWhere('opciones', '');
                     });
    }

    /**
     * #346 (punto 2): detección PASIVA de items "en progreso" estancados — sin actividad
     * (`updated_at`) hace más de N días. Solo INFORMA (aviso en la Torre); no auto-cancela ni
     * archiva ni toca estado. Espejo del branch `terminal` del accessor `estacion` (en_progreso /
     * in_progress / candado humano), excluyendo lo ya archivado.
     */
    public function scopePosibleEstancado($query, int $dias = 10)
    {
        return $query->whereNull('archivado_at')
                     ->where(function ($q) {
                         $q->where('estado_aprobacion', 'en_progreso')
                           ->orWhere('status', 'in_progress')
                           ->orWhere('en_desarrollo_humano', true);
                     })
                     ->where('updated_at', '<', now()->subDays($dias));
    }

    /** #334: fuera del radar activo (archivado). Su complemento = lo pendiente/visible. */
    public function scopeArchivado($query)
    {
        return $query->whereNotNull('archivado_at');
    }

    public function scopeNoArchivado($query)
    {
        return $query->whereNull('archivado_at');
    }

    /**
     * ESTACIÓN — el "dónde vive" un item (#432, principio rector: la Hoja de ruta es la BANDEJA DE
     * ENTRADA, no un almacén). Un item ocupa EXACTAMENTE UNA estación y se DESPACHA de la Hoja de
     * ruta a la suya en cuanto se tria / decide / ejecuta:
     *   - done        → terminal/archivado (done|completado|cancelado|cancelled|rechazado|archivado).
     *   - integracion → tiene rama (branch != null) y no está en done.
     *   - terminal    → lo trabaja un worker (in_progress|en_progreso|worker_sid|en_desarrollo_humano).
     *   - bandeja     → decisión de Irving (requiere_irving | C sin decidir | [BLOCKED-/PARKED-]).
     *   - listo       → ejecutable ya triado (A/B o aprobado_irving) esperando slot de terminal.
     *   - intake      → recién creado SIN TRIAR: lo ÚNICO que vive en la Hoja de ruta.
     */
    public function getEstacionAttribute(): string
    {
        if ($this->archivado_at !== null
            || in_array($this->status, ['done', 'cancelled'], true)
            || in_array($this->estado_aprobacion, ['completado', 'cancelado', 'rechazado'], true)) {
            return 'done';
        }
        // Terminal = ACTIVAMENTE trabajándose (estado en_progreso / status in_progress) o candado
        // humano. NO se usa `worker_sid`: queda pegado tras el reaper (señal stale) y clasificaría
        // como "terminal" items que en realidad rebotaron a la bandeja.
        if ($this->estado_aprobacion === 'en_progreso'
            || $this->status === 'in_progress'
            || (bool) $this->en_desarrollo_humano) {
            return 'terminal';
        }
        // #507 — parqueado esperando el merge de Irving: vive SOLO en Integración. Va ANTES de la
        // bandeja: aunque sea C, ya NO es una decisión pendiente (el trabajo está hecho).
        if ((bool) $this->esperando_merge_irving) {
            return 'integracion';
        }
        // Bandeja (decisión) ANTES que integración: un item con rama vieja que rebotó a Irving
        // (requiere_irving / conflicto) SIGUE siendo una decisión, no integración.
        $bloqueado  = (bool) preg_match('/\[(BLOCKED|PARKED)-/i', (string) $this->title);
        $noAprobado = $this->estado_aprobacion !== 'aprobado_irving';
        if ($this->estado_aprobacion === 'requiere_irving'
            || ($this->nivel_riesgo === 'C' && $this->opcion_elegida === null && $noAprobado)
            || ($bloqueado && $noAprobado)) {
            return 'bandeja';
        }
        if (! empty($this->branch)) {
            return 'integracion';
        }
        if (in_array($this->nivel_riesgo, ['A', 'B'], true) || $this->estado_aprobacion === 'aprobado_irving') {
            return 'listo';
        }

        return 'intake';
    }

    /**
     * TORRE V2 — ESTADO DE COLA del item, en el vocabulario del reparto de Thomas.
     *
     * DERIVADO, nunca almacenado: los datos ya viven en `estado_aprobacion` + `worker_sid` +
     * `branch` + `merge_commit`. Una columna paralela solo agregaría una segunda verdad que se
     * desincroniza (el circuito escribe esos campos desde el scheduler, el reaper, el merge-runner
     * y las seis terminales; mantener un espejo consistente entre todos ellos es justo el tipo de
     * bug que ya costó caro aquí).
     *
     *   en_cola        → triado y ejecutable, esperando terminal libre.
     *   asignado       → reclamado por una terminal, todavía sin rama.
     *   en_progreso    → la terminal ya abrió rama y está trabajando.
     *   en_verificacion→ trabajo terminado, rama esperando verificación/merge.
     *   completado     → mergeado y cerrado.
     *   esperando_irving → fuera del lazo automático: es decisión suya.
     *   sin_triar      → recién creado, todavía sin nivel/aprobación.
     */
    public function getEstadoColaAttribute(): string
    {
        $estacion = $this->estacion;

        if ($estacion === 'done') {
            return in_array($this->estado_aprobacion, ['cancelado', 'rechazado'], true)
                ? 'cancelado'
                : 'completado';
        }

        if ($estacion === 'bandeja') {
            return 'esperando_irving';
        }

        // Trabajo terminado esperando integración (o el merge manual de Irving en los C).
        if ($estacion === 'integracion') {
            return 'en_verificacion';
        }

        if ($estacion === 'terminal') {
            // Con rama = ya está editando; sin rama = apenas reclamado.
            return ! empty($this->branch) ? 'en_progreso' : 'asignado';
        }

        return $estacion === 'listo' ? 'en_cola' : 'sin_triar';
    }

    /** Historial append-only de reportes de este item (Torre v2). */
    public function reports()
    {
        return $this->hasMany(RoadmapItemReport::class, 'roadmap_item_id');
    }

    /** ¿Hay una consulta a Thomas viva (preguntada y sin responder)? */
    public function tieneConsultaViva(): bool
    {
        return $this->consulta_supervisor_at !== null && $this->consulta_resuelta_at === null;
    }

    /** Items con una consulta esperando resolución de Thomas. */
    public function scopeConConsultaViva($query)
    {
        return $query->whereNotNull('consulta_supervisor_at')->whereNull('consulta_resuelta_at');
    }

    /**
     * BANDEJA (Panorama) — TODA la estación de decisión, no solo `requiere_irving`: incluye los C
     * sin decidir y los [BLOCKED-/PARKED-] aunque el revisor aún no los haya movido a requiere_irving,
     * y aunque arrastren una rama/worker vieja (rebote). Así el supervisor los ENRUTA a la bandeja de
     * Irving y NINGUNO se queda perdido. Mirror EXACTO del accessor (precedencia done>terminal>bandeja).
     */
    public function scopeBandeja($query)
    {
        return $query->whereNull('archivado_at')
                     ->whereNotIn('status', ['done', 'cancelled', 'in_progress'])
                     ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado', 'en_progreso'])
                     ->where(fn ($q) => $q->whereNull('en_desarrollo_humano')->orWhere('en_desarrollo_humano', false))
                     ->where(function ($q) {
                         $q->where('estado_aprobacion', 'requiere_irving')
                           ->orWhere(fn ($c) => $c->where('nivel_riesgo', 'C')->whereNull('opcion_elegida')->where('estado_aprobacion', '!=', 'aprobado_irving'))
                           ->orWhere(fn ($b) => $b->where(fn ($w) => $w->where('title', 'like', '%[BLOCKED-%')->orWhere('title', 'like', '%[PARKED-%'))
                                                  ->where('estado_aprobacion', '!=', 'aprobado_irving'));
                     });
    }

    /**
     * INTAKE = lo ÚNICO que vive en la Hoja de ruta (#432): pending, SIN TRIAR (sin nivel_riesgo, en
     * pendiente_revision), sin rama (→ integración) ni candado humano, y sin ser decisión de negocio
     * ([BLOCKED-/PARKED-]). Todo lo demás ya fue enrutado a su estación → la Hoja de ruta queda casi
     * vacía. `scopeBacklog` = intake.
     */
    public function scopeBacklog($query, array $enCurso = [])
    {
        // #432 BLOQUE 0 — antipunto-ciego: el intake se define por `nivel_riesgo IS NULL` (sin triar),
        // NO por un estado exacto. Así CUALQUIER item sin nivel es VISIBLE en la Hoja de ruta (jamás
        // invisible) hasta que el revisor le asigna nivel y lo despacha a su estación. Excluye lo ya
        // despachado (rama/terminal/done), lo aprobado_irving (→ listo) y las decisiones
        // (requiere_irving / [BLOCKED-/PARKED-]). Mirror del branch `intake` del accessor.
        return $query->whereNull('nivel_riesgo')
                     ->whereNull('branch')
                     ->whereNull('archivado_at')
                     ->whereNotIn('status', ['done', 'cancelled', 'in_progress'])
                     ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado', 'en_progreso', 'aprobado_irving', 'requiere_irving'])
                     ->where(fn ($q) => $q->whereNull('en_desarrollo_humano')->orWhere('en_desarrollo_humano', false))
                     ->where('title', 'not like', '%[BLOCKED-%')
                     ->where('title', 'not like', '%[PARKED-%')
                     ->when(! empty($enCurso), fn ($q) => $q->whereNotIn('id', $enCurso));
    }

    /**
     * #507 sub-paso 3 — ORDEN DE LA COLA DE EJECUCIÓN (distinto del de la bandeja, `ordered`).
     * En el pool continuo, cada terminal que queda libre jala el siguiente de aquí:
     *   1. 🔥 `urgente` — la palanca dura de Irving, salta toda la fila.
     *   2. POR CONCLUIRSE / REANUDABLES — items que ya tienen trabajo hecho: rama abierta
     *      (`branch`) o pausados por colisión y ya liberados (`colision_pausada_por`). Cerrar lo
     *      empezado antes de abrir frentes nuevos evita acumular ramas a medias, que es lo que
     *      después choca en el merge.
     *   3. prioridad declarada (alta → media → baja → sin prioridad).
     *   4. más antiguos primero (`position`, `id`).
     * Se mantiene aparte de `ordered()` a propósito: ese ordena lo que Irving VE en la bandeja y no
     * debe cambiar porque cambie la política de despacho.
     */
    public function scopeOrdenCola($query)
    {
        return $query->orderByDesc('urgente')
                     ->orderByRaw('CASE WHEN branch IS NOT NULL OR colision_pausada_por IS NOT NULL THEN 0 ELSE 1 END')
                     ->orderByRaw("FIELD(priority,'baja','media','alta') DESC")
                     ->orderBy('position')
                     ->orderBy('id');
    }

    public function scopeOrdered($query)
    {
        // Orden efectivo de la cola (#348): 🔥 urgentes primero → prioridad (alta→media→baja,
        // sin-prioridad al final) → estado → antigüedad (posición, id).
        // - `urgente` (#337) es la palanca DURA: salta toda la fila.
        // - `priority` (alta/media/baja) es la palanca SUAVE: "hazlo más pronto". FIELD invertido
        //   + DESC deja alta(3)→media(2)→baja(1)→null/otros(0 = al final), sin degradar el
        //   agrupamiento por estado que ya existía (in_progress→pending→done→cancelled).
        return $query->orderByDesc('urgente')
                     ->orderByRaw("FIELD(status,'in_progress','pending','done','cancelled')")
                     ->orderByRaw("FIELD(priority,'baja','media','alta') DESC")
                     ->orderBy('position')
                     ->orderBy('id');
    }
}
