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

    protected $attributes = [
        'subtasks' => '[]',
        'log'      => '[]',
    ];

    public static function currentInProgress(): ?self
    {
        return static::where('status', 'in_progress')->first();
    }

    /**
     * #420: guard de cierre — cualquier save() que deje estado_aprobacion=completado sincroniza
     * status=done + completed_at. Evita que un cierre (tinker, endpoint, merge) deje status=pending
     * colgado, que es justo lo que inflaba el contador "Pendientes" de la Torre (cuenta por status).
     */
    protected static function booted(): void
    {
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
                    $ops[] = [
                        'clave'       => static::claveOpcion($t),
                        'texto'       => $t,
                        'recomendada' => (is_array($o) && ! empty($o['recomendada'])) || stripos($t, 'RECOMENDADA') !== false,
                    ];
                }
                $out[] = [
                    'id'             => (string) ($p['id'] ?? ('q' . ($idx + 1))),
                    'pregunta'       => trim((string) ($p['pregunta'] ?? '')),
                    'opciones'       => $ops,
                    'opcion_elegida' => $p['opcion_elegida'] ?? null,
                    'fase'           => $p['fase'] ?? null,
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
