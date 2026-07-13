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
        // Bandeja de decisiones interactiva (#313)
        'opciones', 'opcion_elegida',
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
     * BACKLOG (pipeline por estado): SOLO lo que aún vive en la pestaña "Hoja de ruta".
     * Un item sale del backlog en cuanto entra a otra columna del pipeline:
     *   - En Terminales / tomado por un worker → su id llega en $enCurso (current_item vivo)
     *     o quedó en_progreso; también en cuanto tiene rama (branch != null → Integración).
     *   - En Integración → whereNotNull('branch') & no archivado.
     *   - Terminal/archivado → done|completado|cancelado|cancelled o archivado_at.
     * $enCurso = ids de sesiones vivas (RoadmapCircuitoService::idsEnCurso), belt-and-suspenders
     * para la ventana en que un worker ya tomó el item pero aún no le creó la rama.
     */
    public function scopeBacklog($query, array $enCurso = [])
    {
        return $query->whereNotIn('status', ['done', 'cancelled'])
                     ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'en_progreso'])
                     ->whereNull('archivado_at')
                     ->whereNull('branch')   // con rama = ya está en Integración/Terminales
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
