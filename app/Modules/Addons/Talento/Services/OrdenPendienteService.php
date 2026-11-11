<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Controllers\TalentoWorkOrderController;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderChecklistItem;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderData;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Support\EsquemaCamposOrden;
use App\Modules\Addons\Talento\Support\OrdenEquipo;
use App\Services\TaskScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Editar y eliminar una orden de trabajo de Talento mientras está Pendiente (item #1225).
 *
 * - Pendiente = `tasks.status = 'ToDo'` y sin validar. «Atrasada» no es un estado: una pendiente
 *   cuyo día ya pasó se edita y elimina igual. En curso / Completada / Validada / Cancelada → 422.
 * - El estado se revisa con la fila bloqueada (lockForUpdate): si el técnico la inicia desde su
 *   celular al mismo tiempo, gana quien llegue primero y el otro recibe el 422.
 * - Historial: talento_order_events (único escritor TalentoOrderEventRecorder, igual que
 *   Reprogramar), un evento `order.edited` por campo con su valor anterior y nuevo, y
 *   `order.deleted` con el motivo.
 * - Eliminar es borrado lógico (SoftDeletes de `tasks`): la agenda, «Mi día», la app y la
 *   liquidación ya ignoran lo borrado, así que el espacio del colaborador queda libre y la orden
 *   no cuenta puntos. No toca el prospecto, el ticket ni el cliente ligados. No hay reservas de
 *   inventario ligadas a órdenes (inventory_reservations solo apunta a almacenes): nada que liberar.
 * - Cambiar el cliente o prospecto (#1228): solo se cambia el vínculo de la orden; el cliente y el
 *   prospecto anteriores no se tocan. Si la orden trae algo que es DEL cliente actual (el ticket del
 *   que nació, su ONU o su servicio) el cambio se rechaza: el ticket se cerraría al completar la orden
 *   (G3b) y la ONU recibiría la activación de la OLT (FieldFlowService), ambos de otra persona.
 *
 * Solo órdenes en `tasks`; las antiguas (talento_work_orders) son de solo lectura.
 *
 * Órdenes de planta externa o con plantilla (#1200): conservan su tipo; no llevan prospecto (el
 * cliente es solo referencia, se cambia o se quita); los datos de la plantilla se editan en
 * talento_work_order_data y la descripción se vuelve a armar con EsquemaCamposOrden, igual que al
 * crearla; el título solo cambia si cambian los datos que lo forman.
 */
class OrdenPendienteService
{
    public const MSG_NO_EDITABLE   = 'Esta orden ya fue iniciada o cerrada y no se puede editar. Actualiza la lista.';
    public const MSG_NO_ELIMINABLE = 'Esta orden ya fue iniciada o cerrada y no se puede eliminar. Actualiza la lista.';
    public const MSG_TIPO_FIJO     = 'Esta orden no puede cambiar a ese tipo: las de planta externa conservan el tipo con que se crearon. Cancélala y crea una nueva con el tipo correcto.';

    public const MOTIVOS_ELIMINACION = [
        'creada_por_error' => 'Creada por error',
        'duplicada'        => 'Duplicada',
        'cliente_cancelo'  => 'Cliente canceló',
        'otro'             => 'Otro',
    ];

    public function __construct(
        private OrdenTrabajoUnifiedService $unified,
        private AvisoOrdenColaborador $avisos,
        private ChoqueAgendaOrdenes $choque,
    ) {}

    public static function esPendiente(Task $task): bool
    {
        return $task->status === 'ToDo' && $task->validated_at === null;
    }

    /**
     * $data (todas opcionales): colaborador_id, type_id, client_id, crm_lead_id, scheduled_at
     * ("YYYY-MM-DD" o "YYYY-MM-DD HH:mm"), notes. client_id/crm_lead_id presentes en null = quitarlo.
     * datos (solo órdenes con plantilla): todos los campos de creación; lo que no venga queda vacío.
     *
     * @return array{ok:bool, codigo:int, mensaje?:string, ya_no_pendiente?:bool, cambios?:array}
     */
    public function editar(int $id, array $data, int $actorId): array
    {
        $r = DB::transaction(function () use ($id, $data, $actorId) {
            $task = Task::whereKey($id)->whereNotNull('talento_type_id')->with('users')->lockForUpdate()->first();
            if (! $task) {
                return $this->noEncontrada($id);
            }
            if (! self::esPendiente($task)) {
                return $this->falla(422, self::MSG_NO_EDITABLE) + ['ya_no_pendiente' => true];
            }

            // #1325: con órdenes en equipo, la persona de la orden es el responsable, no cualquier asignado.
            $responsableId   = OrdenEquipo::responsableUserId($task);
            $usuarioAnterior = $task->users->firstWhere('id', $responsableId) ?? $task->users->first();
            $auxAntes        = OrdenEquipo::auxiliaresUserIds($task);
            $tipoActual      = TalentoWorkOrderType::find($task->talento_type_id);
            $datosOrden      = TalentoWorkOrderData::where('tarea_id', $task->id)->lockForUpdate()->first();
            $plantaExterna   = (bool) $tipoActual?->esPlantaExterna();
            $inicioActual    = TaskScheduleService::start($task->start_date, $task->start_time);
            $updates = [];
            $cambios = [];

            // ── Colaborador ─────────────────────────────────────────────────────
            $colabNuevo = null;
            if (! empty($data['colaborador_id'])) {
                $col = TalentoColaborador::with('user')->find($data['colaborador_id']);
                // Si es la misma persona no hay cambio (aunque hoy esté inactiva, se pueden editar las notas).
                if (! $col?->user_id || $col->user_id !== $usuarioAnterior?->id) {
                    // Mismas dos reglas que store(): con usuario vinculado y activo.
                    if (! $col?->user_id) {
                        return $this->falla(422, 'Este colaborador no tiene un usuario de sistema vinculado; no se le puede asignar la orden.');
                    }
                    if (! TalentoColaborador::activos()->whereKey($col->id)->exists()) {
                        return $this->falla(422, 'Esta persona ya no está activa; elige a otra para asignarle la orden.');
                    }
                    $colabNuevo = $col;
                    $cambios['colaborador'] = [self::nombre($usuarioAnterior), self::nombre($col->user)];
                }
            }

            // ── Tipo: puntos, pagable, campo/interna y proyecto salen del tipo, como al crearla ──
            $tipoFinal = $tipoActual;
            if (! empty($data['type_id']) && (int) $data['type_id'] !== (int) $task->talento_type_id) {
                $tipoFinal = TalentoWorkOrderType::findOrFail($data['type_id']);
                // #1200 (regla a): sus datos, proyecto y checklist son del tipo con que nació; y una de
                // cliente no pasa a planta externa, porque se saltaría la plantilla.
                if ($datosOrden || $plantaExterna || $tipoFinal->esPlantaExterna()) {
                    return $this->falla(422, self::MSG_TIPO_FIJO);
                }
                $updates += [
                    'talento_type_id' => $tipoFinal->id,
                    'points'          => $tipoFinal->points,
                    'is_billable'     => $tipoFinal->is_billable ? 1 : 0,
                    'tipo'            => $tipoFinal->category ?? 'campo',
                    'project_id'      => $tipoFinal->project_id
                        ?? TalentoWorkOrderController::TYPE_PROJECT_MAP[$tipoFinal->id]
                        ?? $task->project_id,
                ];
                $cambios['tipo']   = [$tipoActual?->name, $tipoFinal->name];
                if ((int) $task->points !== (int) $tipoFinal->points) {
                    $cambios['puntos'] = [(int) $task->points, (int) $tipoFinal->points];
                }
            }

            // ── Fecha y hora: misma regla que Reprogramar (de hoy en adelante) ──
            $inicioFinal = $inicioActual;
            if (! empty($data['scheduled_at'])) {
                $nuevo = Carbon::parse($data['scheduled_at']);
                if (! $inicioActual || $nuevo->format('Y-m-d H:i') !== $inicioActual->format('Y-m-d H:i')) {
                    if ($nuevo->toDateString() < now()->toDateString()) {
                        return $this->falla(422, 'La nueva fecha debe ser de hoy en adelante.');
                    }
                    $inicioFinal = $nuevo;
                    $updates += TaskScheduleService::fields($nuevo, $nuevo->copy()->addHour());
                    $cambios['fecha'] = [self::fecha($inicioActual), self::fecha($nuevo)];
                }
            }

            // ── Auxiliares (#1325): si vienen, reemplazan a los de antes; si no, se conservan ──
            $auxDespues = $auxAntes;
            if (array_key_exists('auxiliares', $data) && is_array($data['auxiliares'])) {
                $responsableColId = $colabNuevo?->id ?? (int) TalentoColaborador::where('user_id', $usuarioAnterior?->id)->value('id');
                $resueltos = OrdenEquipo::resolverAuxiliares($data['auxiliares'], $responsableColId);
                if (is_string($resueltos)) {
                    return $this->falla(422, $resueltos);
                }
                $auxDespues = $resueltos->pluck('user_id')->map(fn ($u) => (int) $u)->all();
            }
            if ($colabNuevo) {
                $auxDespues = array_values(array_diff($auxDespues, [(int) $colabNuevo->user_id]));
            }
            $auxEntran = array_values(array_diff($auxDespues, $auxAntes));
            $auxSalen  = array_values(array_diff($auxAntes, $auxDespues));
            if ($auxEntran || $auxSalen) {
                $cambios['auxiliares'] = [self::nombres($auxAntes), self::nombres($auxDespues)];
            }

            // ── Choque de agenda: solo si cambió la fecha/hora o la persona ──
            // Regla única compartida con Crear y Reprogramar (#1227): ChoqueAgendaOrdenes.
            if ($inicioFinal && ($colabNuevo || isset($cambios['fecha']))) {
                $usuarioDestino = $colabNuevo ? $colabNuevo->user : $usuarioAnterior;
                if ($choque = $this->choque->verificar($usuarioDestino, $inicioFinal, $task->id)) {
                    return $choque;
                }
            }
            // #1325: los auxiliares que entran y, si cambió la fecha, todos.
            if ($inicioFinal) {
                foreach (User::whereIn('id', isset($cambios['fecha']) ? $auxDespues : $auxEntran)->get() as $auxiliar) {
                    if ($choque = $this->choque->verificar($auxiliar, $inicioFinal, $task->id)) {
                        return $choque;
                    }
                }
            }

            // ── Cliente o prospecto (nunca ambos, decisión q2 de Irving) ──
            $clientIdActual = $task->client_main_information_id
                ? DB::table('client_main_information')->where('id', $task->client_main_information_id)->value('client_id')
                : null;
            $clientIdFinal = array_key_exists('client_id', $data) ? ($data['client_id'] ?: null) : $clientIdActual;
            $leadFinal     = array_key_exists('crm_lead_id', $data) ? ($data['crm_lead_id'] ?: null) : $task->crm_lead_id;
            if ($plantaExterna && $leadFinal) {
                return $this->falla(422, 'Las órdenes de planta externa no llevan prospecto del CRM. Si la orden es por una caja que reportó un cliente, elígelo como cliente de referencia.');
            }
            if ($clientIdFinal && $leadFinal) {
                return $this->falla(422, 'Una orden es para un cliente o un prospecto, no ambos.');
            }
            $cambiaCliente   = (int) $clientIdFinal !== (int) $clientIdActual;
            $cambiaProspecto = (int) $leadFinal !== (int) $task->crm_lead_id;
            if (($cambiaCliente || $cambiaProspecto) && ($vinculos = self::vinculosDelCliente($task))) {
                return $this->falla(422, 'No se puede cambiar el cliente o prospecto de esta orden: trae '
                    . implode(' y ', $vinculos) . ' del cliente actual. Si el cliente es otro, elimínala y créala de nuevo.')
                    + ['vinculos' => $vinculos];
            }
            if ($cambiaCliente) {
                $cmiNuevo = null;
                if ($clientIdFinal) {
                    $cmiNuevo = DB::table('client_main_information as cmi')
                        ->join('clients as c', 'c.id', '=', 'cmi.client_id')
                        ->where('cmi.client_id', $clientIdFinal)
                        ->whereNull('cmi.deleted_at')->whereNull('c.deleted_at')
                        ->value('cmi.id');
                    if (! $cmiNuevo) {
                        return $this->falla(422, 'Ese cliente no tiene una ficha activa; elige otro.');
                    }
                }
                $updates['client_main_information_id'] = $cmiNuevo;
                $cambios['cliente'] = [self::clienteTexto($clientIdActual), self::clienteTexto($clientIdFinal)];
            }
            if ($cambiaProspecto) {
                $updates['crm_lead_id'] = $leadFinal;
                $cambios['prospecto'] = [self::prospectoTexto($task->crm_lead_id), self::prospectoTexto($leadFinal)];
            }

            // ── Notas ── (con plantilla, las notas son solo el bloque NOTAS de la descripción)
            $notasActuales = $datosOrden ? EsquemaCamposOrden::notasDe($task->description) : $task->description;
            $notasFinal = $notasActuales;
            if (array_key_exists('notes', $data)) {
                $nuevas = trim((string) $data['notes']) !== '' ? $data['notes'] : null;
                if ((string) $nuevas !== (string) $notasActuales) {
                    $notasFinal = $nuevas;
                    $cambios['notas'] = [$notasActuales, $notasFinal];
                }
            }

            // ── Datos de la plantilla (#1200, regla d) ──
            $valoresFinal = null;
            if ($datosOrden && array_key_exists('datos', $data)) {
                $rd = $this->datosEditados($datosOrden, (array) ($data['datos'] ?? []));
                if (! $rd['ok']) {
                    return $rd;
                }
                if ($rd['cambios']) {
                    $valoresFinal = $rd['valores'];
                    $cambios += $rd['cambios'];
                }
            }

            if (! $cambios) {
                return ['ok' => true, 'codigo' => 200, 'cambios' => []];
            }

            if ($datosOrden) {
                // Reglas b y c: la descripción se arma con los datos, como al crearla; el título de la
                // plantilla solo se rehace si cambian sus datos.
                $esquema = $datosOrden->schema_snapshot ?? [];
                $valores = $valoresFinal ?? (array) ($datosOrden->values ?? []);
                if ($valoresFinal !== null || isset($cambios['notas'])) {
                    $checklist = TalentoWorkOrderChecklistItem::where('tarea_id', $task->id)
                        ->orderBy('sort_order')->orderBy('id')->get(['label', 'cantidad', 'unidad'])->toArray();
                    $updates['description'] = EsquemaCamposOrden::descripcion(
                        $esquema, $valores, $datosOrden->template?->description, $checklist, $notasFinal
                    );
                }
                if ($valoresFinal !== null) {
                    $updates['title'] = EsquemaCamposOrden::titulo($tipoActual?->name ?? '', $datosOrden->template?->title, $esquema, $valores);
                    $datosOrden->update(['values' => (object) $valores, 'updated_by' => $actorId]);
                }
            } else {
                if (isset($cambios['notas'])) {
                    $updates['description'] = $notasFinal;
                }
                // Mismo título que al crearla: "[Tipo] primeras 60 letras de las notas".
                if (isset($cambios['tipo']) || isset($cambios['notas'])) {
                    $updates['title'] = '[' . ($tipoFinal?->name ?? '') . '] '
                        . (! empty($notasFinal) ? mb_substr($notasFinal, 0, 60) : 'Nueva orden');
                }
            }

            DB::table('tasks')->where('id', $task->id)->update($updates + [
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]);

            if ($colabNuevo || isset($cambios['auxiliares'])) {
                // Sin órdenes en equipo es lo de siempre: solo el responsable en task_user y en participantes.
                OrdenEquipo::asignar($task, (int) ($colabNuevo?->user_id ?? $usuarioAnterior?->id), $auxDespues);
            }
            if ($colabNuevo) {
                $this->unified->transicionar('task', $task->id, 'order.reassigned', null, null, $actorId, null,
                    ['assigned_by'], ['colaborador_id' => $colabNuevo->id]);
            }

            foreach ($cambios as $campo => [$de, $a]) {
                TalentoOrderEventRecorder::registrar('task', $task->id, 'order.edited', null, null, $actorId, null, [
                    'campo' => $campo,
                    'de'    => self::recorta($de),
                    'a'     => self::recorta($a),
                ]);
            }

            // #1326: si estaba con el supervisor por una incidencia, editarla la atiende.
            if ($cambios) {
                app(IncidenciasOrdenService::class)->atender($task->id, 'editada', $actorId);
            }

            return [
                'ok'       => true,
                'codigo'   => 200,
                'cambios'  => array_keys($cambios),
                '_avisos'  => [
                    'nuevo'    => $colabNuevo?->user,
                    // El responsable anterior que se quedó como auxiliar no recibe «retirada».
                    'anterior' => $colabNuevo && ! in_array((int) $usuarioAnterior?->id, $auxDespues, true) ? $usuarioAnterior : null,
                    'aux_entran' => User::whereIn('id', $auxEntran)->get()->all(),
                    'aux_salen'  => User::whereIn('id', array_diff($auxSalen, [(int) $colabNuevo?->user_id]))->get()->all(),
                    'tipo'     => $tipoFinal?->name ?? 'Orden de trabajo',
                    'inicio'   => $inicioFinal,
                ],
            ];
        });

        // Avisos fuera de la transacción: la orden ya quedó guardada pase lo que pase con ellos.
        if (! empty($r['_avisos'])) {
            $a    = $r['_avisos'];
            $task = Task::find($id);
            if ($task && $a['nuevo']) {
                $this->avisos->asignada($task, $a['nuevo'], $a['tipo'], $a['inicio'], 'al reasignar orden');
            }
            if ($task && $a['anterior']) {
                $this->avisos->retirada($task, $a['anterior'], $a['tipo'], $a['inicio']);
            }
            foreach ($task ? $a['aux_entran'] : [] as $usuario) {
                $this->avisos->asignada($task, $usuario, $a['tipo'], $a['inicio'], 'al sumar auxiliar');
            }
            foreach ($task ? $a['aux_salen'] : [] as $usuario) {
                $this->avisos->retirada($task, $usuario, $a['tipo'], $a['inicio']);
            }
        }
        unset($r['_avisos']);

        return $r;
    }

    /**
     * Borrado lógico con motivo. $motivo ∈ MOTIVOS_ELIMINACION; con 'otro' el detalle es obligatorio.
     *
     * @return array{ok:bool, codigo:int, mensaje?:string, ya_no_pendiente?:bool, ligada?:array}
     */
    public function eliminar(int $id, string $motivo, ?string $detalle, int $actorId): array
    {
        $detalle = trim((string) $detalle);
        if (! isset(self::MOTIVOS_ELIMINACION[$motivo])) {
            return $this->falla(422, 'Elige un motivo de la lista.');
        }
        if ($motivo === 'otro' && $detalle === '') {
            return $this->falla(422, 'Escribe el motivo.');
        }
        $texto = mb_substr(self::MOTIVOS_ELIMINACION[$motivo] . ($detalle !== '' ? ': ' . $detalle : ''), 0, 500);

        $r = DB::transaction(function () use ($id, $motivo, $detalle, $texto, $actorId) {
            $task = Task::whereKey($id)->whereNotNull('talento_type_id')->with('users')->lockForUpdate()->first();
            if (! $task) {
                return $this->noEncontrada($id);
            }
            if (! self::esPendiente($task)) {
                return $this->falla(422, self::MSG_NO_ELIMINABLE) + ['ya_no_pendiente' => true];
            }

            $asignado = $task->users->first();
            $ligada   = [
                'prospecto_id' => $task->crm_lead_id ? (int) $task->crm_lead_id : null,
                'ticket_id'    => $task->ticket_id ? (int) $task->ticket_id : null,
            ];

            DB::table('tasks')->where('id', $task->id)->update([
                'motivo_eliminacion' => $texto,
                'eliminada_por'      => $actorId,
                'updated_by'         => $actorId,
                'updated_at'         => now(),
            ]);
            $task->delete(); // SoftDeletes: deleted_at = ahora.
            // #1326: si estaba con el supervisor por una incidencia (p. ej. el cliente canceló), queda atendida.
            app(IncidenciasOrdenService::class)->atender($task->id, 'eliminada', $actorId);

            TalentoOrderEventRecorder::registrar('task', $task->id, 'order.deleted', 'pending', 'deleted', $actorId, $texto, [
                'motivo'          => $motivo,
                'detalle'         => $detalle !== '' ? $detalle : null,
                'asignado_a'      => $asignado?->id,
            ] + $ligada);

            activity('talento')->performedOn($task)->causedBy(User::find($actorId))
                ->withProperties(['motivo' => $motivo, 'detalle' => $detalle ?: null] + $ligada)
                ->log('Orden de trabajo eliminada');

            return [
                'ok'      => true,
                'codigo'  => 200,
                'ligada'  => $ligada,
                '_avisos' => [
                    'usuario' => $asignado,
                    'tipo'    => TalentoWorkOrderType::whereKey($task->talento_type_id)->value('name') ?? 'Orden de trabajo',
                    'inicio'  => TaskScheduleService::start($task->start_date, $task->start_time),
                ],
            ];
        });

        if (! empty($r['_avisos']['usuario'])) {
            $task = Task::withTrashed()->find($id);
            $this->avisos->eliminada($task, $r['_avisos']['usuario'], $r['_avisos']['tipo'], $r['_avisos']['inicio']);
        }
        unset($r['_avisos']);

        return $r;
    }

    /** Se conserva por compatibilidad: la regla vive en ChoqueAgendaOrdenes::buscar (#1227). */
    public function choqueDeAgenda(int $userId, Carbon $inicio, ?int $excluirTaskId = null): ?Task
    {
        return $this->choque->buscar($userId, $inicio, $excluirTaskId);
    }

    /**
     * Buscador de clientes del formulario de edición (#1228): mismo motor que el listado de Clientes
     * (ClienteSearchService, lista blanca) por nombre completo, teléfono o número de cliente.
     *
     * @return array<int, array{id:int, nombre:string, telefono:?string, estado:?string, colonia:?string, direccion:?string}>
     */
    public function buscarClientes(string $texto, int $limite = 10): array
    {
        // aplicar() deja la consulta SIN filtro si el texto es más corto que el mínimo: aquí eso
        // devolvería clientes al azar, así que se corta antes.
        if (mb_strlen(trim($texto)) < (int) config('clientes_busqueda.min_caracteres', 3)) {
            return [];
        }
        $query = \App\Models\Client::query()->select('clients.id');
        app(\App\Modules\Core\Clientes\Services\ClienteSearchService::class)
            ->aplicar($query, $texto, ['id', 'full_name', 'phone', 'phone2']);
        $ids = $query->orderByDesc('clients.id')->limit($limite)->pluck('clients.id')->all();
        if (! $ids) {
            return [];
        }

        $fichas = DB::table('client_main_information as cmi')
            ->leftJoin('colonies as col', 'col.id', '=', 'cmi.colony_id')
            ->whereIn('cmi.client_id', $ids)->whereNull('cmi.deleted_at')
            ->get(['cmi.client_id', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name', 'cmi.phone',
                'cmi.estado', 'cmi.street', 'cmi.external_number', 'cmi.address', 'col.name as colonia'])
            ->keyBy('client_id');

        return collect($ids)->filter(fn ($id) => isset($fichas[$id]))->map(function ($id) use ($fichas) {
            $f = $fichas[$id];
            $calle = trim(($f->street ?? '') . ' ' . ($f->external_number ?? ''));

            return [
                'id'        => (int) $id,
                'nombre'    => trim("{$f->name} {$f->father_last_name} {$f->mother_last_name}") ?: "Cliente #{$id}",
                'telefono'  => $f->phone ?: null,
                'estado'    => $f->estado ?: null,
                'colonia'   => $f->colonia ?: null,
                // La misma que ve el técnico en la orden (client_main_information.address); si no hay, la calle.
                'direccion' => $f->address ?: ($calle !== '' ? $calle : null),
            ];
        })->values()->all();
    }

    /** Lo que la orden trae DEL cliente actual y no se puede mover a otro sin suponer (#1228). */
    public static function vinculosDelCliente(Task $task): array
    {
        return array_values(array_filter([
            $task->ticket_id ? "el ticket #{$task->ticket_id}" : null,
            $task->olt_onu_id ? 'la ONU' : null,
            $task->client_service_id ? 'el servicio' : null,
        ]));
    }

    /** "#id · Nombre" para el historial (de dónde a dónde cambió). */
    private static function clienteTexto($clientId): ?string
    {
        if (! $clientId) {
            return null;
        }
        $f = DB::table('client_main_information')->where('client_id', $clientId)->first(['name', 'father_last_name', 'mother_last_name']);
        $nombre = $f ? trim("{$f->name} {$f->father_last_name} {$f->mother_last_name}") : '';

        return "#{$clientId}" . ($nombre !== '' ? " · {$nombre}" : '');
    }

    private static function prospectoTexto($leadId): ?string
    {
        if (! $leadId) {
            return null;
        }
        $f = DB::table('crm_lead_information as cli')->join('crm_main_information as cmi', 'cmi.crm_id', '=', 'cli.crm_id')
            ->where('cli.id', $leadId)->first(['cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name']);
        $nombre = $f ? trim("{$f->name} {$f->father_last_name} {$f->mother_last_name}") : '';

        return "#{$leadId}" . ($nombre !== '' ? " · {$nombre}" : '');
    }

    /** Si otra persona ya la eliminó, es el mismo caso que «ya no está pendiente»: actualizar la lista. */
    /**
     * #1200 (regla d): valida los campos de creación contra el esquema con que nació la orden (el
     * mismo validador que al crearla) y devuelve los valores completos: los de creación nuevos y,
     * sin tocar, los que el técnico captura al cerrar. Un cambio por campo para el historial.
     *
     * @return array{ok:bool, codigo?:int, mensaje?:string, errors?:array, valores?:array, cambios?:array}
     */
    private function datosEditados(TalentoWorkOrderData $datosOrden, array $entrada): array
    {
        $esquema = $datosOrden->schema_snapshot ?? [];
        $r = EsquemaCamposOrden::validarDatos($esquema, $entrada, EsquemaCamposOrden::MOMENTO_CREACION);
        if ($r['errors']) {
            $labels = array_column($esquema, 'label', 'key');
            $primera = array_key_first($r['errors']);
            $key = explode('.', $primera)[1] ?? '';
            $mensaje = isset($labels[$key]) ? $labels[$key] . ': ' . $r['errors'][$primera] : $r['errors'][$primera];

            return $this->falla(422, $mensaje) + ['errors' => $r['errors']];
        }

        $antes = (array) ($datosOrden->values ?? []);
        $creacion = EsquemaCamposOrden::camposDe($esquema, EsquemaCamposOrden::MOMENTO_CREACION);
        $valores = array_diff_key($antes, array_flip(array_column($creacion, 'key'))) + $r['values'];

        $cambios = [];
        foreach ($creacion as $f) {
            $de = $antes[$f['key']] ?? null;
            $a  = $r['values'][$f['key']] ?? null;
            if (json_encode($de) === json_encode($a)) {
                continue;
            }
            $cambios['dato:' . $f['label']] = $f['type'] === 'tabla'
                ? [is_array($de) ? count($de) . ' filas' : null, is_array($a) ? count($a) . ' filas' : null]
                : [EsquemaCamposOrden::formatearValor($f, $de), EsquemaCamposOrden::formatearValor($f, $a)];
        }

        return ['ok' => true, 'valores' => $valores, 'cambios' => $cambios];
    }

    private function noEncontrada(int $id): array
    {
        if (Task::onlyTrashed()->whereKey($id)->whereNotNull('talento_type_id')->exists()) {
            return $this->falla(422, 'Esta orden ya fue eliminada. Actualiza la lista.') + ['ya_no_pendiente' => true];
        }

        return $this->falla(404, 'Orden no encontrada.');
    }

    private function falla(int $codigo, string $mensaje): array
    {
        return ['ok' => false, 'codigo' => $codigo, 'mensaje' => $mensaje];
    }

    private static function nombre(?User $u): ?string
    {
        return $u ? (trim($u->name . ' ' . $u->father_last_name) ?: "Usuario #{$u->id}") : null;
    }

    /** #1325: nombres de los auxiliares para el historial ("Sin auxiliares" si no hay). */
    private static function nombres(array $userIds): string
    {
        $usuarios = User::whereIn('id', $userIds)->get()->keyBy('id');
        $lista = array_filter(array_map(fn ($id) => self::nombre($usuarios[$id] ?? null), $userIds));

        return $lista ? implode(', ', $lista) : 'Sin auxiliares';
    }

    private static function fecha(?Carbon $c): ?string
    {
        return $c ? $c->format($c->format('H:i') === '00:00' ? 'Y-m-d' : 'Y-m-d H:i') : null;
    }

    private static function recorta($v)
    {
        return is_string($v) ? mb_substr($v, 0, 1000) : $v;
    }
}
