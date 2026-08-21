<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class RoadmapItem extends Model
{
    protected $table = 'roadmap_items';

    /**
     * #878 — proyección LIGERA suficiente para `RoadmapCircuitoService::compact()` y para los dos
     * accessors que consulta (`estacion`, `estado_cola`) más `tieneConsultaViva()`.
     *
     * Existe como constante y no como lista suelta en el servicio porque el accessor `estacion` lee
     * nueve columnas para decidir: si alguien agrega una condición ahí y la proyección no la trae,
     * el item se clasifica en la estación equivocada SIN error visible. Con la lista aquí, al lado
     * del accessor, el que la toque ve las dos cosas juntas.
     */
    /**
     * #878 — ACTORES AUTOMÁTICOS: quién decide sin Irving delante. Se comparan por PREFIJO de
     * `aprobado_por`, no por lista cerrada de literales, para que un actor nuevo quede cubierto por
     * defecto. El modo de fallo correcto aquí es "se frena de más", nunca "revocó y nadie se enteró".
     */
    public const ACTORES_AUTOMATICOS = ['autopilot', 'revisor:', 'destrabe', 'clasificador'];

    /** ¿Esta firma de `aprobado_por` es de un actor automático? */
    public static function firmaAutomatica(?string $aprobadoPor): bool
    {
        foreach (self::ACTORES_AUTOMATICOS as $a) {
            if ($aprobadoPor !== null && str_starts_with($aprobadoPor, $a)) {
                return true;
            }
        }

        return false;
    }

    public const COLUMNAS_COMPACT = [
        'id', 'title', 'modulo', 'status', 'priority', 'urgente', 'nivel_riesgo', 'estado_aprobacion',
        'worker_sid', 'origen_item_id', 'branch', 'archivado_at', 'en_desarrollo_humano',
        'esperando_merge_irving', 'origen_bloqueo', 'opcion_elegida',
        'consulta_supervisor_at', 'consulta_resuelta_at',
    ];

    protected $fillable = [
        'title', 'description', 'status', 'priority',
        'target_version', 'prompt', 'position',
        'started_at', 'completed_at',
        'subtasks', 'log',
        // Circuito de mejora continua (Parte 1.1)
        'modulo', 'nivel_riesgo', 'estado_aprobacion',
        // Quién fijó el nivel_riesgo vigente: interno|externo (circuito #260)
        'nivel_riesgo_origen',
        // Veredicto persistido de la VÁLVULA DE NACIMIENTO (mencion|accion|null) — lo consumen los
        // guards que corren DESPUÉS del alta, sin el texto delante.
        'frontera_valvula', 'frontera_valvula_at',
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
        // #921 — fecha futura de reactivación (independiente de excluir_pool_automatico)
        'agendado_para',
        // FASE 2A.3 — quién puso el freno (humano FRENA / clasificador INFORMA) y hasta cuándo
        'origen_bloqueo', 'bloqueo_expira_en', 'bloqueo_renovaciones',
        // TORRE V2 — canal de consulta terminal → Thomas (autoridad intermedia antes de Irving)
        'consulta_supervisor', 'consulta_supervisor_sid', 'consulta_supervisor_at', 'consulta_opciones',
        'consulta_respuesta', 'consulta_resuelta_at', 'consulta_resuelta_por',
        // Estimación de esfuerzo del reparto (orientativa, nunca bloqueante)
        'eta_minutos', 'eta_asignada_at',
        // #561 — contador de reclamos fallidos (reap) antes de escalar a Irving
        'reap_count',
        // #546 — reloj en regresión por terminal: ETA de DURACIÓN del trabajo (no del reparto)
        'trabajo_iniciado_at', 'eta_segundos', 'eta_metodo',
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
        'agendado_para'               => 'datetime',
        'frontera_valvula_at'         => 'datetime',
        'escalaciones_fingerprint'    => 'array',
        // FASE 2A.3
        'bloqueo_expira_en'           => 'datetime',
        'bloqueo_renovaciones'        => 'integer',
        // TORRE V2 — consulta a Thomas
        'consulta_supervisor_at'      => 'datetime',
        'consulta_resuelta_at'        => 'datetime',
        'consulta_opciones'           => 'array',
        'eta_asignada_at'             => 'datetime',
        'reap_count'                  => 'integer',
        // #546
        'trabajo_iniciado_at'         => 'datetime',
        'eta_segundos'                => 'integer',
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
     * Bandera TRANSITORIA (no persistida): la enciende el cierre en cascada del paraguas, cuando el
     * último sub-item cerró y el padre ya puede completarse de verdad. Sin ella, el guard de abajo
     * lo retendría para siempre — el padre nunca podría cerrar.
     */
    public bool $cierreParaguas = false;

    /**
     * #420: guard de cierre — cualquier save() que deje estado_aprobacion=completado sincroniza
     * status=done + completed_at. Evita que un cierre (tinker, endpoint, merge) deje status=pending
     * colgado, que es justo lo que inflaba el contador "Pendientes" de la Torre (cuenta por status).
     */
    /**
     * FASE 2A.5 — estados desde los que el Kanban legado PUEDE arrastrar `estado_aprobacion`
     * (guard #456). Los estados de decisión ya gobernados por el circuito —`aprobado_claude`,
     * `aprobado_revisor`, `requiere_irving`, `completado`, `cancelado`, `rechazado`— NO están aquí
     * a propósito: mover una tarjeta en un tablero no puede deshacer un veredicto del revisor.
     *
     * `aprobado_irving` se agregó en 2A.5: es donde se queda la mayoría de lo ya autorizado y era
     * el hueco por el que "moví la tarjeta a Hecho y no pasó nada".
     */
    /** Orden de `nivel_riesgo`, para recortar el juego de niveles despachables. */
    public const ORDEN_NIVEL = ['A' => 1, 'B' => 2, 'C' => 3];

    public const ESTADOS_SINCRONIZABLES_DESDE_KANBAN = ['pendiente_revision', 'en_progreso', 'aprobado_irving'];

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
        //
        // FASE 2A.5 — SE AMPLÍA a `aprobado_irving` (en vez de agregar un guard nuevo: dos guards
        // sobre el mismo par de campos es exactamente cómo se llega a la deriva). `aprobado_irving`
        // es donde vive la mayoría de los items ya autorizados, y era el hueco que quedaba: mover su
        // tarjeta a "Hecho" en el Kanban no cerraba nada.
        //
        // ⚠️ PRECEDENCIA — ESCRITA, NO IMPLÍCITA EN EL ORDEN DE LOS HOOKS.
        //
        // Con la ampliación, las DOS direcciones quedan activas a la vez:
        //   (A) status → estado_aprobacion   ← este hook (el Kanban legado sólo muta `status`)
        //   (B) estado_aprobacion → status   ← el hook de `completado` de más abajo, y el parqueo
        //                                      de C-con-rama, que además fuerza `status = pending`
        //
        // REGLA: **gana `estado_aprobacion`.** Es la máquina de estados real del circuito (Thomas,
        // autopilot, MergeRunner); `status` es el espejo Kanban. Por eso (A) sólo actúa cuando el
        // llamador NO tocó `estado_aprobacion` en el mismo save — que es el caso para el que existe
        // el #456: `RoadmapController::start/complete/cancel` mutan `status` a secas.
        //
        // Sin ese `! isDirty('estado_aprobacion')`, un save que cambia los dos campos (los hay:
        // `decidir()`, `integracionRechazo()`, `MergeRunner::markMerged()`) dejaría que este hook
        // pisara en silencio la decisión explícita del llamador. No es un bucle —cada hook corre una
        // vez por save, en orden de registro— pero sí el pisotón que 2A.5 viene a cerrar.
        static::saving(function (self $item) {
            if ($item->isDirty('estado_aprobacion')) {
                return;   // PRECEDENCIA: el llamador decidió explícitamente; `status` es el que sigue.
            }

            if (in_array($item->estado_aprobacion, self::ESTADOS_SINCRONIZABLES_DESDE_KANBAN, true)
                && $item->isDirty('status')) {
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

            // (2b) PARAGUAS — un item que se descompuso NO se completa mientras le queden sub-items
            // abiertos. Se retiene como paraguas: sigue autorizado (no es una decisión pendiente) y
            // fuera del pool (ningún worker lo re-toma). Lo cierra solo el último hijo que cierre.
            if ($item->exists
                && $item->isDirty('estado_aprobacion')
                && $item->estado_aprobacion === 'completado'
                && ! $item->cierreParaguas
                && $item->tieneSubItemsAbiertos()) {
                $abiertos = $item->subItemsAbiertos()->count();

                $item->estado_aprobacion       = 'aprobado_irving';
                $item->status                  = 'pending';
                $item->excluir_pool_automatico = true;

                $log = $item->log ?: [];
                $log[] = [
                    'ts'              => now()->toIso8601String(),
                    'por'             => 'paraguas',
                    'evento'          => 'paraguas_abierto',
                    'subitems_abiertos' => $abiertos,
                    'motivo'          => "Este item se descompuso y le quedan {$abiertos} sub-item(s) "
                                       . 'abierto(s): no se completa. Queda como paraguas y cierra solo '
                                       . 'cuando el último de ellos cierre.',
                ];
                $item->log = $log;
            }

            // (3) #878 — NINGÚN ACTOR AUTOMÁTICO REVOCA UNA AUTORIZACIÓN HUMANA EXPLÍCITA.
            //
            // Regla de Irving (2026-08-20): el triaje PUEDE endurecer el nivel de riesgo —A→B→C es
            // información nueva y es legítima— pero NO puede devolverle una decisión que él ya tomó.
            // Un item que él aprobó al crearlo y que un actor automático manda de vuelta a
            // `requiere_irving` es el sistema discutiendo con él, y es lo que lo frena: pide el
            // trabajo, lo autoriza, y la máquina se lo regresa a la bandeja a preguntarle lo mismo.
            //
            // Sólo se veta ESTA transición (aprobado_irving → requiere_irving por firma automática).
            // El nivel de riesgo del mismo save pasa intacto: endurecer sigue permitido.
            // Un humano sí puede (su `aprobado_por` no es una firma automática), y el des-parqueo
            // de #507 tampoco se toca porque va hacia `aprobado_irving`, no desde él.
            if ($item->exists
                && $item->isDirty('estado_aprobacion')
                && $item->getOriginal('estado_aprobacion') === 'aprobado_irving'
                && $item->estado_aprobacion === 'requiere_irving'
                && static::firmaAutomatica((string) $item->aprobado_por)) {
                $firma = (string) $item->aprobado_por;

                $item->estado_aprobacion = 'aprobado_irving';        // la autorización se conserva
                $item->aprobado_por      = $item->getOriginal('aprobado_por');

                $log = $item->log ?: [];
                $log[] = [
                    'ts'     => now()->toIso8601String(),
                    'por'    => $firma,
                    'evento' => 'revocacion_automatica_rechazada',
                    'motivo' => "«{$firma}» intentó devolver este item a requiere_irving, pero ya "
                              . 'estaba autorizado por un humano. El nivel de riesgo sí puede subir; '
                              . 'la autorización no se revoca automáticamente.',
                ];
                $item->log = $log;

                Log::warning('roadmap: revocación automática rechazada', [
                    'item' => $item->id, 'actor' => $firma, 'nivel' => $item->nivel_riesgo,
                ]);
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

        // PARAGUAS — cuando un SUB-ITEM cierra, se revisa si era el último: si sí, el padre cierra
        // solo. Va en `saved` (no en `saving`) para que la fila del hijo ya esté escrita cuando se
        // cuenten los hermanos abiertos; si no, el propio hijo se contaría a sí mismo.
        static::saved(function (self $item) {
            if (empty($item->origen_item_id)) {
                return;
            }
            $cerrado = in_array($item->estado_aprobacion, ['completado', 'cancelado', 'rechazado'], true)
                || in_array($item->status, ['done', 'cancelled'], true);
            if (! $cerrado) {
                return;
            }

            $padre = static::find($item->origen_item_id);
            // Sólo cierra al padre que está RETENIDO como paraguas: si sigue en la bandeja o lo está
            // trabajando alguien, no es asunto de este hook.
            if (! $padre || $padre->estado_aprobacion !== 'aprobado_irving' || $padre->tieneSubItemsAbiertos()) {
                return;
            }

            $log = $padre->log ?: [];
            $log[] = [
                'ts'     => now()->toIso8601String(),
                'por'    => 'paraguas',
                'evento' => 'paraguas_cerrado',
                'ultimo_subitem' => $item->id,
                'motivo' => "Cerró el último sub-item (#{$item->id}): el paraguas ya puede completarse.",
            ];
            $padre->log = $log;
            $padre->cierreParaguas   = true;   // habilita el guard de arriba para ESTE save
            $padre->estado_aprobacion = 'completado';
            $padre->save();
        });

        // FASE 2A.3 — clasificar al INSERTAR, en vez de barrer con Opus cada 3 minutos.
        // `afterCommit` es obligatorio: buena parte de las altas ocurren dentro de transacciones
        // (backfills, sub-items de las terminales) y sin esto el worker buscaría una fila que
        // todavía no existe. Falla-segura: el clasificador es advisory, si no corre no frena nada.
        static::created(function (self $item) {
            \App\Modules\Addons\Roadmap\Jobs\ClasificarRiesgoJob::dispatch($item->id)->afterCommit();
        });

        // FASE 2A.4 — TRAZABILIDAD DE LOS FRENOS DE DESPACHO.
        //
        // El `log` del item solo registraba DECISIONES, nunca cambios de bandera. Por eso los flags
        // huérfanos de `excluir_pool_automatico` resultaron inatribuibles: estaban encendidos y no
        // había forma de saber quién ni por qué. Un bloqueo cuya razón nunca se registró tampoco se
        // puede reevaluar — `circuito:re-triage` (2A.4) no tendría contra qué comparar.
        //
        // Va REGISTRADO AL FINAL a propósito: los `saving` corren en orden de registro, así que este
        // se ejecuta después del parqueo de C-con-rama y de `contarEscalacion()` y alcanza a ver
        // los cambios que ELLOS hacen. Si se mueve arriba, deja de registrarlos.
        //
        // ⚠️ Cubre a todo el que escriba por el MODELO (controlador, Thomas, integrar, hooks). Las
        // escrituras crudas por `DB::table()` —claim atómico, lease, migraciones de reconciliación—
        // no pasan por aquí y siguen anotando su propio rastro a mano.
        //
        // AUDITADO (item #780, 2A.4b, 2026-08-18): ninguna escritura cruda VIVA toca estas 4
        // banderas. La única excepción es la migración `2026_08_18_120000_limpia_flags_huerfanos_
        // excluir_pool`, que ya anota su propio rastro a mano en el mismo UPDATE. Candado estático
        // que evita que el hueco se reabra en silencio:
        // `tests/Unit/Modules/Addons/Roadmap/RawWritesDontTouchBloqueoFlagsTest.php`.
        static::saving(function (self $item) {
            if (! $item->exists) {
                return;   // en la creación no hay "cambio de bandera" que narrar
            }

            $cambios = [];
            foreach (['excluir_pool_automatico', 'esperando_merge_irving', 'bloqueado_por_bucle', 'motivo_bloqueo',
                      'origen_bloqueo', 'bloqueo_expira_en'] as $col) {
                if ($item->isDirty($col)) {
                    $cambios[$col] = ['antes' => $item->getOriginal($col), 'despues' => $item->{$col}];
                }
            }
            if (empty($cambios)) {
                return;
            }

            $log   = $item->log ?: [];
            $log[] = [
                'ts'         => now()->toIso8601String(),
                'por'        => static::actorActual(),
                'estado'     => $item->estado_aprobacion,
                'decision'   => 'flags',
                'comentario' => $item->motivo_bloqueo ?: null,
                'flags'      => $cambios,
            ];
            $item->log = $log;
        });
    }

    /**
     * Quién está escribiendo: el usuario autenticado si lo hay, o el comando de consola en curso.
     * Se usa para atribuir los cambios de bandera (2A.4) — sin esto el rastro dice "cambió" pero no
     * "quién", que es justo lo que hizo inatribuibles a los huérfanos.
     */
    protected static function actorActual(): string
    {
        $u = auth()->hasUser() ? auth()->user() : null;
        if ($u) {
            return 'irving:' . ($u->login_user ?? $u->email ?? $u->id);
        }

        if (app()->runningInConsole()) {
            $cmd = $_SERVER['argv'][1] ?? null;

            return 'consola:' . ($cmd ?: 'artisan');
        }

        return 'sistema';
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

    /**
     * ¿El título lleva rótulo de frontera dura ([BLOCKED-…] / [PARKED-…])?
     *
     * OJO — esto es el chequeo CRUDO del string, que sobrevive sólo como FALLBACK legacy mientras
     * quedan títulos rotulados. Para decidir si algo frena, usar `tieneFrenoHumano()`.
     */
    public function tieneRotuloBloqueo(): bool
    {
        return (bool) preg_match('/\[(BLOCKED|PARKED)-/i', (string) $this->title);
    }

    /** Rótulo COMPLETO (`[BLOCKED-NEGOCIO]`, `[PARKED-PROD]`…), para poder mostrarlo (2A.4). */
    public const RE_ROTULO = '/\[(BLOCKED|PARKED)-[^\]]+\]/i';

    /**
     * FASE 2A.3 — ¿hay un freno VIGENTE que deba detener el despacho?
     *
     * Punto ÚNICO de la regla de Irving (2026-08-18): **el bloqueo humano frena; el del
     * clasificador sólo informa.** Antes esa distinción no existía —los cinco guards hacían el
     * mismo `preg_match` sobre el título— así que "que el clasificador sólo aconseje" se habría
     * implementado dejando de honrar el rótulo, y eso habría tirado también los 33 frenos humanos.
     *
     * `origen_bloqueo='clasificador'` NO entra aquí a propósito: el triaje automático de riesgo
     * opina y deja su brief, pero no detiene nada. Su señal se ve en la Torre y en el digest.
     */
    public function tieneFrenoHumano(): bool
    {
        // Columna primero (fuente nueva); el rótulo en el título es el fallback legacy.
        return $this->origen_bloqueo === 'humano' || $this->tieneRotuloBloqueo();
    }

    /**
     * FASE 2A.3 — versión SQL de `tieneFrenoHumano()`, para los scopes. Se aplica sobre un grupo
     * `where(function ($q) { ... })`. Existe para que la regla viva en UN lugar también del lado de
     * la consulta: son tres scopes los que la necesitan y tenerla copiada es cómo empezó todo esto.
     */
    /**
     * FASE 2A.3 §5 — literales que delatan que un item toca PRODUCCIÓN.
     *
     * No es una lista de bloqueo: por decisión de Irving (2026-08-18) esto **no frena nada**. Es la
     * señal para avisar. Con carril autónomo hasta nivel B y 6 terminales, prod es la única
     * combinación del sistema que no se deshace con un `git checkout`; el objetivo no es impedirlo,
     * es que sea imposible enterarse tarde.
     */
    public const SENALES_PRODUCCION = [
        '192.168.105.108',
        '38.123.192.198',
        'v1megaisp.com.mx',
        'meganet_prod',
        '/var/www/ClaudeMegaISP',
        '/var/www/MEGANET',
    ];

    /** El literal de producción que aparece en el item, o null. Devuelve CUÁL para poder mostrarlo. */
    public function tocaProduccion(): ?string
    {
        $blob = ($this->title ?? '') . ' ' . ($this->description ?? '') . ' ' . ($this->prompt ?? '')
            . ' ' . ($this->comentarios_claude ?? '') . ' ' . ($this->branch ?? '');

        foreach (self::SENALES_PRODUCCION as $senal) {
            if (stripos($blob, $senal) !== false) {
                return $senal;
            }
        }

        return null;
    }

    public static function sqlConFrenoHumano($q): void
    {
        $q->where('origen_bloqueo', 'humano')
          ->orWhere('title', 'like', '%[BLOCKED-%')     // fallback legacy
          ->orWhere('title', 'like', '%[PARKED-%');
    }

    /** Negación de `sqlConFrenoHumano` (De Morgan: sin columna humana Y sin rótulo en el título). */
    public static function sqlSinFrenoHumano($q): void
    {
        $q->where(fn ($x) => $x->whereNull('origen_bloqueo')->orWhere('origen_bloqueo', '!=', 'humano'))
          ->where('title', 'not like', '%[BLOCKED-%')
          ->where('title', 'not like', '%[PARKED-%');
    }

    // ── FASE 2A.4 — LOS FRENOS HUMANOS NO CADUCAN, SE RESURFACEAN ───────────────────────────────

    /**
     * ¿Cuántas DECISIONES MUDAS acumula este item? Una decisión muda es la misma decisión, del
     * mismo actor, con el MISMO estado resultante que la anterior: Irving dijo que sí otra vez y el
     * item no se movió ni un milímetro.
     *
     * Es la señal más limpia de "aquí hay un desacuerdo entre lo que decidiste y lo que quieres":
     * un freno que tú pusiste, sobre un item que tú sigues aprobando. #65 lleva 26 aprobaciones
     * contra su propio `[BLOCKED-NEGOCIO]`.
     *
     * DEFINICIÓN ÚNICA: la usan el digest (ventana de N días) y el re-triage (histórico completo).
     * Las entradas de traza (`flags`, `alerta_prod`) no son decisiones y además CORTAN la racha —
     * si no cortaran, una traza en medio uniría dos decisiones distintas.
     */
    public static function contarMudasEnLog(?array $log, ?\Illuminate\Support\Carbon $desde = null): int
    {
        $mudas = 0;
        $prev  = null;

        foreach ((array) $log as $e) {
            if (! is_array($e)) {
                continue;
            }
            if (! isset($e['decision']) || in_array($e['decision'], ['flags', 'alerta_prod'], true)) {
                $prev = null;
                continue;
            }
            $k = ($e['por'] ?? '?') . '|' . $e['decision'] . '|' . ($e['estado'] ?? '?');
            if ($prev !== null && $k === $prev
                && ($desde === null || (isset($e['ts']) && \Illuminate\Support\Carbon::parse($e['ts'])->gte($desde)))) {
                $mudas++;
            }
            $prev = $k;
        }

        return $mudas;
    }

    /** Atajo de instancia: decisiones mudas de TODA la vida del item. */
    public function aprobacionesMudas(): int
    {
        return static::contarMudasEnLog($this->log);
    }

    /**
     * ¿Desde cuándo está en pie este freno? Devuelve `[Carbon, bool $exacto]`.
     *
     * EXACTO sólo cuando existe el rastro de 2A.4: una entrada de `flags` donde **una persona**
     * puso `origen_bloqueo = humano`. Para los 33 frenos legacy —que venían como rótulo dentro del
     * título y sólo se sellaron en columna el 2026-08-18— ese rastro no existe, y usar la fecha del
     * backfill diría "0 días" para todos, que es exactamente la mentira que este reporte tiene que
     * evitar. En ese caso se cae a la PRIMERA entrada del log (la actividad más antigua registrada)
     * y se marca como APROXIMADO: es una cota inferior honesta, no una fecha inventada.
     *
     * @return array{0:?\Illuminate\Support\Carbon,1:bool}
     */
    public function frenoDesde(): array
    {
        foreach ((array) $this->log as $e) {
            if (! is_array($e) || ($e['decision'] ?? null) !== 'flags') {
                continue;
            }
            $cambio = $e['flags']['origen_bloqueo'] ?? null;
            if (is_array($cambio) && ($cambio['despues'] ?? null) === 'humano'
                && str_starts_with((string) ($e['por'] ?? ''), 'irving:')
                && isset($e['ts'])) {
                return [\Illuminate\Support\Carbon::parse($e['ts']), true];
            }
        }

        foreach ((array) $this->log as $e) {
            if (is_array($e) && isset($e['ts'])) {
                return [\Illuminate\Support\Carbon::parse($e['ts']), false];
            }
        }

        return [$this->created_at, false];
    }

    /** Lo que DECÍA el rótulo, para poder recordarlo sin abrir el item. */
    public function textoDelFreno(): string
    {
        if (preg_match(self::RE_ROTULO, (string) $this->title, $m)) {
            return $m[0];
        }

        // La migración 2A.3 sacó el rótulo del título pero guardó el título previo en el log: ésa
        // es la fuente fiel de "lo que decía el rótulo". `motivo_bloqueo` NO sirve como primera
        // opción — lo comparte con el anti-bucle, así que en #99/#26 contaría la historia del bucle
        // en vez del freno.
        foreach (array_reverse((array) $this->log) as $e) {
            if (is_array($e) && ($e['decision'] ?? null) === 'rotulo_a_columna'
                && preg_match(self::RE_ROTULO, (string) ($e['titulo_previo'] ?? ''), $m)) {
                return $m[0];
            }
        }

        if (trim((string) $this->motivo_bloqueo) !== '') {
            return mb_strimwidth(trim((string) $this->motivo_bloqueo), 0, 70, '…');
        }
        return '(sin rótulo — freno sellado en columna)';
    }

    /**
     * FASE 2A.3 — cuántos items dependen HOY del fallback legacy: llevan rótulo en el título pero
     * NO están sellados como freno humano en columna. Cuando esto marque 0 durante una semana, el
     * `LIKE` sobre `title` de `scopeElegibleParaPool` puede retirarse sin perder ningún freno.
     */
    public static function contarFallbackRotulo(): int
    {
        return static::query()
            ->whereNull('archivado_at')
            ->where(fn ($q) => $q->where('title', 'like', '%[BLOCKED-%')->orWhere('title', 'like', '%[PARKED-%'))
            ->where(fn ($q) => $q->whereNull('origen_bloqueo')->orWhere('origen_bloqueo', '!=', 'humano'))
            ->count();
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
        return $query->where(fn ($q) => static::sqlElegibleParaPool($q));
    }

    /**
     * #921 — items con fecha futura de reactivación. Para el contador "N items agendados" de la
     * Torre; los que ya pasaron su fecha los limpia `circuito:reactivar-agendados` (el campo vuelve
     * a null), así que "agendado" aquí siempre significa "todavía espera su fecha".
     */
    public function scopeAgendados($query)
    {
        return $query->whereNotNull('agendado_para');
    }

    /**
     * FASE 2A.5 — DEFINICIÓN ÚNICA del predicado de elegibilidad para el pool, en SQL.
     *
     * Existe para que el scope de Eloquent y el CANDADO ATÓMICO del reclamo
     * (`RoadmapCircuitoService::claimNextParalelo`) no puedan volver a separarse: los dos aplican
     * ESTE método, no una copia. Es la cuarta vez que el mismo predicado se bifurca —scope,
     * SQL crudo del reclamo, `preg_match` en PHP y la Vue— y la del reclamo es la cara:
     * decide QUÉ TOCA UN WORKER, así que una deriva ahí es una terminal trabajando sobre algo
     * que no debía.
     *
     * Candado que impide la re-separación:
     * `tests/Unit/Modules/Addons/Roadmap/PoolGuardCoherenceTest.php` (compara el SQL compilado de
     * los dos caminos y falla si el reclamo vuelve a enumerar las banderas a mano) +
     * `php artisan circuito:coherencia-pool` (compara los dos CONJUNTOS sobre los items vivos).
     *
     * Se aplica sobre un grupo `where(function ($q) { ... })`. Sirve igual a un
     * `Illuminate\Database\Query\Builder` (reclamo por `DB::table`) que a uno de Eloquent.
     */
    public static function sqlElegibleParaPool($q): void
    {
        $q->where(fn ($x) => $x->whereNull('excluir_pool_automatico')->orWhere('excluir_pool_automatico', false))
          ->where(fn ($x) => $x->whereNull('esperando_merge_irving')->orWhere('esperando_merge_irving', false))
          // #921 — un item AGENDADO a futuro no es trabajo pendiente: fuera del pool hasta su fecha,
          // SIN usar `excluir_pool_automatico` (ese es el master switch de otros 6 mecanismos).
          // `circuito:reactivar-agendados` (diario) limpia el campo cuando la fecha ya pasó.
          ->where(fn ($x) => $x->whereNull('agendado_para')->orWhere('agendado_para', '<=', now()))
          // FASE 2A.3 — sólo frena el freno HUMANO. `origen_bloqueo='clasificador'` NO frena: el
          // triaje automático de riesgo aconseja, no detiene (decisión de Irving 2026-08-18).
          // Incluye el fallback legacy del rótulo en el título, que se retira cuando
          // `contarFallbackRotulo()` marque 0 durante una semana.
          ->where(fn ($x) => static::sqlSinFrenoHumano($x));
    }

    /**
     * FASE 2A.3 — CONDICIÓN ÚNICA DE DESPACHO. Es la definición de "el circuito puede tomar este
     * item AHORA", y `RoadmapCircuitoService::ejecutablesParalelo()` la consume tal cual (el
     * pre-filtro de footprint sigue siendo suyo: es una regla de la RONDA, no del item).
     *
     * Existe para que nadie vuelva a enumerar frenos a mano. El guard anti-re-aprobación de
     * `decidir()` listaba dos banderas (`esperando_merge_irving`, `bloqueado_por_bucle`) y no veía
     * el master switch `excluir_pool_automatico`: aprobar un item con el master huérfano respondía
     * 200 y no lo movía. #32 acumuló 8 aprobaciones mudas de Irving, #186 treinta y dos. Cada
     * bandera nueva reintroducía el bug. Preguntar por el RESULTADO no caduca.
     */
    public function scopeDespachable($query)
    {
        $revisor = app(\App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService::class)->revisorEnabled();

        // ENTREGA 1 — el tope de nivel del despacho sale de la POLÍTICA BASE de la Torre, no de
        // `circuito.autopilot.max_nivel`. Esa clave decía «autopilot» y gobernaba a TODOS los
        // actores desde aquí; ahora es sólo el sub-techo del autopilot, con su significado literal.
        //
        // Gobierna lo que la máquina aprueba sola: `aprobado_irving` (autorización explícita de
        // Irving) siempre pasa, y un `automatizacion_override = auto` vigente también — ver abajo.
        $base    = app(\App\Modules\Addons\Roadmap\Services\TorreAutomationPolicy::class)->politicaBase();
        $niveles = $base === null
            ? []                                                    // `manual`: nada automático despacha
            : array_slice(['A', 'B', 'C'], 0, self::ORDEN_NIVEL[$base] ?? 1);

        return $query
            ->tomablePorCircuito()
            ->elegibleParaPool()
            ->whereNotIn('status', ['done'])
            ->where(function ($w) use ($revisor) {
                $w->where(fn ($x) => $x->where('nivel_riesgo', 'A')->where('estado_aprobacion', 'aprobado_claude'));
                $w->orWhere(fn ($x) => $x->where('nivel_riesgo', 'A')->where('estado_aprobacion', 'pendiente_revision'));
                $w->orWhere('estado_aprobacion', 'aprobado_irving');
                if ($revisor) {
                    $w->orWhere('estado_aprobacion', 'aprobado_revisor');
                }
            })
            ->where(function ($w) use ($niveles, $base) {
                $w->whereIn('nivel_riesgo', $niveles)
                  ->orWhere('estado_aprobacion', 'aprobado_irving');

                // Un item sin nivel no está triado: sólo pasa si hay política (en `manual` no).
                if ($base !== null) {
                    $w->orWhereNull('nivel_riesgo');

                    // OVERRIDE POR ITEM (opción B, 2026-08-19). La excepción de Irving sobre un item
                    // concreto tiene que llegar hasta el despacho: si el gate de nivel la cancelara
                    // aquí, el item quedaría aprobado-y-nunca-despachable, con la autorización
                    // gastada y sin haber corrido jamás.
                    //
                    // Va DENTRO del `if ($base !== null)` a propósito: con la política en `manual`
                    // esta cláusula no existe, así que **`manual` sigue parando a todos, incluidos
                    // los overrides y lo ya aprobado que aún no se despachó**. Ésa es justo la
                    // retroactividad que hace que un paro de emergencia sea un paro.
                    //
                    // El override se CONSUME al reclamar (`claimNextParalelo`), no al aprobar: si el
                    // actor aprueba y algo falla antes de correr, la autorización no se quemó.
                    $w->orWhere('automatizacion_override', 'auto');
                }
            });
    }

    /**
     * FASE 2A.3 — ¿por qué este item NO despacharía? `null` = sí despacha.
     *
     * La VERDAD la da `scopeDespachable` (una sola consulta). Lo de abajo sólo TRADUCE ese "no" a
     * un motivo accionable, en orden de lo que Irving puede resolver primero. Si algún día se
     * agrega un freno y nadie actualiza esta traducción, el veredicto sigue siendo correcto: se
     * cae al motivo genérico, nunca a un falso "sí".
     */
    public function motivoNoDespachable(): ?array
    {
        if (static::query()->whereKey($this->getKey())->despachable()->exists()) {
            return null;
        }

        if ($this->status === 'done' || $this->estado_aprobacion === 'completado') {
            return ['code' => 'ya_cerrado', 'accion' => 'ninguna',
                'error' => 'Este item ya está cerrado. Aprobarlo no lo reabre: si hay trabajo nuevo, crea un item de seguimiento.'];
        }

        if (in_array($this->estado_aprobacion, ['cancelado', 'rechazado'], true)) {
            return ['code' => 'descartado', 'accion' => 'reabrir',
                'error' => 'Este item está ' . $this->estado_aprobacion . '. Aprobarlo no lo devuelve a la cola; hay que reabrirlo explícitamente.'];
        }

        if ($this->tieneFrenoHumano()) {
            // `desbloqueable` le dice a la Torre que puede ofrecer «Quitar el freno y aprobar» en el
            // mismo lugar (2A.3 §3). Es un freno que puso una persona: la salida es una decisión,
            // no un trámite de ir a editar el título a mano.
            return ['code' => 'freno_humano', 'accion' => 'quitar_freno', 'desbloqueable' => true,
                'motivo_texto' => $this->motivo_bloqueo ?: null,
                'error' => 'Este item tiene un freno que pusiste tú'
                    . ($this->motivo_bloqueo ? ' (' . $this->motivo_bloqueo . ')' : '')
                    . ': el circuito no lo va a tomar mientras siga puesto. Aprobar y desbloquear son '
                    . 'dos cosas distintas — si ya quieres que corra, quita el freno.'];
        }

        if ($this->esperando_merge_irving) {
            return ['code' => 'esperando_merge', 'accion' => 'mergear',
                'error' => 'Este item YA está terminado y sólo espera tu merge — aprobarlo otra vez no lo mueve. '
                    . 'Usa «Mergear» (o circuito:integrar --force).'];
        }

        if ($this->agendado_para && $this->agendado_para->isFuture()) {
            return ['code' => 'agendado', 'accion' => 'esperar_fecha',
                'error' => 'Item agendado para el ' . $this->agendado_para->toDateTimeString()
                    . ': no es trabajo pendiente todavía. `circuito:reactivar-agendados` lo devuelve al pool solo, en su fecha.'];
        }

        if ($this->bloqueado_por_bucle) {
            return ['code' => 'bloqueado_por_bucle', 'accion' => 'cambio_material',
                'error' => 'Item fuera del pool por anti-bucle: ' . ($this->motivo_bloqueo ?: 'se re-escaló por la misma causa varias veces')
                    . ' Aprobarlo igual reabre el ciclo; cambia algo material (decisión, alcance, rama) o reenvía con forzar=true.'];
        }

        if ($this->requiere_sesion_supervisada) {
            return ['code' => 'sesion_supervisada', 'accion' => 'sesion_con_irving',
                'error' => 'Este item pide sesión supervisada: no lo toma una terminal sola. '
                    . 'Hay que trabajarlo contigo presente, o quitarle esa marca si ya no aplica.'];
        }

        if ($this->excluir_pool_automatico) {
            return ['code' => 'fuera_del_pool', 'accion' => 'destrabar',
                'error' => 'Item excluido del pool automático' . ($this->motivo_bloqueo ? ' (' . $this->motivo_bloqueo . ')' : ' sin motivo registrado')
                    . '. Aprobarlo no lo devuelve a la cola: hay que destrabarlo (forzar=true) o corregir la causa.'];
        }

        if ($this->en_desarrollo_humano) {
            return ['code' => 'desarrollo_humano', 'accion' => 'liberar',
                'error' => 'Este item está marcado como trabajo humano en curso: el circuito no lo toca hasta que se libere.'];
        }

        if ($this->estado_aprobacion === 'en_progreso') {
            return ['code' => 'en_progreso', 'accion' => 'esperar',
                'error' => 'Una terminal ya lo tiene en progreso' . ($this->worker_sid ? ' (' . $this->worker_sid . ')' : '') . '.'];
        }

        return ['code' => 'no_despachable', 'accion' => 'revisar',
            'error' => 'La decisión quedó registrada, pero el item sigue sin ser reclamable por el circuito '
                . '(estado ' . ($this->estado_aprobacion ?: '—') . ', nivel ' . ($this->nivel_riesgo ?: '—') . '). Revísalo en su detalle.'];
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

    /**
     * #880 — Épica #874 Fase 2: candidatos a "por qué no avanza". Une los items con al menos UNA
     * señal REAL de estancamiento (columnas que ya existen, no una fecha vieja por sí sola): el
     * anti-bucle, una consulta a Thomas sin resolver, una colisión de archivos pausada, reclamos
     * huérfanos repetidos, o el estancamiento por tiempo que ya detectaba `posibleEstancado` (#346).
     * Cualquier estación puede traer una señal, por eso solo excluye lo ya cerrado/archivado.
     */
    public function scopeNoAvanza($query, int $dias = 10)
    {
        return $query->whereNull('archivado_at')
                     ->whereNotIn('status', ['done', 'cancelled'])
                     ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
                     ->where(function ($q) use ($dias) {
                         $q->where('bloqueado_por_bucle', true)
                           ->orWhere('esperando_merge_irving', true)
                           ->orWhereNotNull('colision_pausada_por')
                           ->orWhere('reap_count', '>=', 2)
                           ->orWhere(function ($q2) {
                               $q2->whereNotNull('consulta_supervisor_at')->whereNull('consulta_resuelta_at');
                           })
                           ->orWhere(fn ($q3) => $q3->posibleEstancado($dias));
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
        // #507 — parqueado esperando el merge de Irving: vive SOLO en Integración. Va ANTES de la
        // bandeja: aunque sea C, ya NO es una decisión pendiente (el trabajo está hecho).
        if ((bool) $this->esperando_merge_irving) {
            return 'integracion';
        }
        // Bandeja (decisión) ANTES que integración: un item con rama vieja que rebotó a Irving
        // (requiere_irving / conflicto) SIGUE siendo una decisión, no integración.
        // FASE 2A.3 — punto único. Un item marcado sólo por el CLASIFICADOR ya no cae a la bandeja
        // por eso: su marca es un consejo, no una decisión pendiente de Irving.
        $bloqueado  = $this->tieneFrenoHumano();
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

    /**
     * PARAGUAS — sub-items de este item que todavía NO están cerrados.
     *
     * Regla de Irving (2026-08-20): **un item que se descompone no se completa.** Queda abierto como
     * paraguas y se cierra solo cuando todos sus sub-items estén cerrados.
     *
     * El porqué: si descomponer contara como completar, `completado` significaría dos cosas
     * distintas —«esto ya está hecho» y «esto lo partí en siete»— y la Torre reportaría trabajo
     * terminado donde no se hizo nada. Pasó de verdad con la épica #874: se cerró al descomponerse
     * y figuraba como hecha con cinco de sus siete fases sin empezar.
     *
     * Gratis, además: con el paraguas abierto la barra de avance de la épica es real.
     */
    public function subItemsAbiertos()
    {
        return static::where('origen_item_id', $this->id)
            ->whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
            ->whereNotIn('status', ['done', 'cancelled']);
    }

    public function tieneSubItemsAbiertos(): bool
    {
        return $this->exists && $this->subItemsAbiertos()->exists();
    }

    /**
     * #895 — CANDADO DE IDEMPOTENCIA para la descomposición: ¿este item YA generó al menos un
     * sub-item (abierto o cerrado)? A diferencia de `subItemsAbiertos()`, cuenta TODOS — si la
     * vuelta se cortó a mitad y una terminal reanuda el mismo item, no debe volver a descomponerlo
     * (duplicaría sub-items). `origen_item_id` es la trazabilidad; este método es el candado.
     */
    public function yaFueDescompuesto(): bool
    {
        return $this->exists && static::where('origen_item_id', $this->id)->exists();
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

    /**
     * #880 — Épica #874 Fase 2: frase legible de por qué ESTE item no avanza, a partir de columnas
     * reales que ya existen (nunca inventa un mecanismo de destrabe nuevo). Null si no hay ninguna
     * señal conocida — la ausencia de frase NO se pinta como estancamiento.
     *
     * Prioridad: la señal MÁS específica primero (bucle → consulta → colisión → merge → reap huérfano),
     * el estancamiento por tiempo al final por ser el más genérico de todos.
     */
    public function porQueNoAvanza(): ?string
    {
        if ($this->bloqueado_por_bucle) {
            $extra = $this->motivo_bloqueo ? " ({$this->motivo_bloqueo})" : '';
            return "El anti-bucle lo sacó de la cola automática: escaló varias veces con el mismo resultado, sin una decisión nueva de por medio{$extra}.";
        }

        if ($this->tieneConsultaViva()) {
            $desde = $this->consulta_supervisor_at ? $this->consulta_supervisor_at->diffForHumans() : 'hace un momento';
            $pregunta = mb_strimwidth((string) $this->consulta_supervisor, 0, 140, '…');
            return "Espera que Thomas resuelva una consulta abierta {$desde}: «{$pregunta}»";
        }

        if ($this->colision_pausada_por) {
            return "Pausado por colisión de archivos con el item #{$this->colision_pausada_por}: se reanuda solo en cuanto ese item termine.";
        }

        if ($this->esperando_merge_irving) {
            return 'El trabajo ya está listo; solo falta que Irving lo integre (merge) a main.';
        }

        if ((int) $this->reap_count >= 2) {
            return "Se reclamó y se soltó {$this->reap_count} veces sin que ninguna terminal lo terminara (reclamo huérfano).";
        }

        $enProgreso = $this->estado_aprobacion === 'en_progreso' || $this->status === 'in_progress' || (bool) $this->en_desarrollo_humano;
        if ($enProgreso && $this->updated_at && $this->updated_at->lt(now()->subDays(10))) {
            $dias = (int) floor($this->updated_at->diffInDays(now()));
            return "Figura en progreso, pero no tiene actividad hace {$dias} días.";
        }

        return null;
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
                           ->orWhere(fn ($b) => $b->where(fn ($w) => static::sqlConFrenoHumano($w))
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
                     ->where(fn ($q) => static::sqlSinFrenoHumano($q))
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
        foreach (self::criteriosOrdenCola() as $criterio) {
            ($criterio['orderBy'])($query);
        }

        return $query;
    }

    /**
     * #890 (Torre fase 6) — ÚNICA fuente del criterio de orden de la cola ejecutable: cada entrada
     * trae su SQL (consumido por `scopeOrdenCola`, arriba) Y su lectura humana (consumida por
     * `explicarOrdenCola`, abajo). Antes la Torre mostraba una frase escrita a mano
     * ("urgente → prioridad → antigüedad") que podía desincronizarse del `ORDER BY` real si éste
     * cambiaba; con esto sólo hay un lugar que editar y las dos salidas se mueven juntas.
     */
    public static function criteriosOrdenCola(): array
    {
        return [
            [
                'label'    => 'urgente',
                'orderBy'  => fn ($q) => $q->orderByDesc('urgente'),
                'valor'    => fn (self $i) => $i->urgente ? 1 : 0,
                'describe' => fn ($v) => $v ? 'está marcado urgente' : 'no está marcado urgente',
            ],
            [
                'label'    => 'por concluirse',
                'orderBy'  => fn ($q) => $q->orderByRaw(
                    'CASE WHEN branch IS NOT NULL OR colision_pausada_por IS NOT NULL THEN 0 ELSE 1 END'
                ),
                'valor'    => fn (self $i) => ($i->branch || $i->colision_pausada_por) ? 1 : 0,
                'describe' => fn ($v) => $v
                    ? 'ya tiene trabajo en curso por concluir (rama abierta o reanudable)'
                    : 'no tiene trabajo en curso por concluir',
            ],
            [
                'label'    => 'prioridad',
                'orderBy'  => fn ($q) => $q->orderByRaw("FIELD(priority,'baja','media','alta') DESC"),
                'valor'    => fn (self $i) => $i->priority ?: 'sin prioridad',
                'describe' => fn ($v) => 'su prioridad es ' . $v,
            ],
            [
                'label'    => 'antigüedad',
                'orderBy'  => fn ($q) => $q->orderBy('position')->orderBy('id'),
                'valor'    => fn (self $i) => $i->position,
                'describe' => fn ($v) => 'es el que lleva más tiempo esperando su turno',
            ],
        ];
    }

    /**
     * #890 — Frase que explica por qué el PRIMER item de `$items` (ya ordenados con `ordenCola()`)
     * va primero. Recorre `criteriosOrdenCola()` EN ORDEN y usa el primer criterio en el que el
     * primero difiere del resto — es justo el que decidió su lugar. Si el criterio de arriba
     * cambia (se agrega, se quita o se reordena una entrada), esta frase cambia sola: no hay
     * texto aparte que actualizar.
     */
    public static function explicarOrdenCola(iterable $items): string
    {
        $criterios = self::criteriosOrdenCola();
        $orden     = implode(' → ', array_column($criterios, 'label'));
        $items     = collect($items)->values();

        if ($items->count() < 2) {
            return "Orden: {$orden}.";
        }

        $primero = $items->first();
        $resto   = $items->slice(1);

        foreach ($criterios as $c) {
            $valorPrimero = ($c['valor'])($primero);
            if ($resto->contains(fn (self $i) => ($c['valor'])($i) !== $valorPrimero)) {
                return "Orden: {$orden}. El #{$primero->id} va primero porque "
                    . ($c['describe'])($valorPrimero) . ' y los demás no.';
            }
        }

        return "Orden: {$orden}. El #{$primero->id} va primero (empata en todos los criterios con los demás).";
    }

    /**
     * #878 — HIDRATACIÓN EN DOS PASOS para consultas que necesitan la fila COMPLETA en un orden
     * concreto.
     *
     * `ORDER BY` + `SELECT *` sobre esta tabla revienta MySQL con "Out of sort memory" (1038):
     * son 96 columnas, 22 de ellas TEXT/JSON, y el filesort dimensiona su registro por el ancho
     * DECLARADO de las columnas, no por el contenido — así que falla incluso con `LIMIT 1` y con
     * pocas filas. Es el mismo defecto que dejó la bandeja de Irving invisible 20 días.
     *
     * Cuando el llamador SÓLO necesita algunos campos, la solución es `get([columnas])`. Cuando
     * necesita el modelo entero (autopilot, revisor, briefs), sirve esto: se ordena sobre una
     * proyección de `id` —filesort estrecho, no revienta— y se traen las filas anchas SIN
     * `ORDER BY`, restaurando el orden en PHP.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  ya filtrado y ORDENADO
     */
    public static function hidratarEnOrden($query, ?int $limite = null): \Illuminate\Database\Eloquent\Collection
    {
        if ($limite !== null) {
            $query->limit($limite);
        }

        $ids = $query->pluck('id')->all();   // paso 1: sólo `id` → el sort no toca las columnas anchas
        if ($ids === []) {
            return static::query()->whereRaw('1 = 0')->get();
        }

        // paso 2: filas completas SIN `ORDER BY` (un `SELECT *` sin filesort nunca da 1038).
        return static::whereIn('id', $ids)->get()
            ->sortBy(fn (self $i) => array_search($i->id, $ids, true))
            ->values();
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
