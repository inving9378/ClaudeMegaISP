<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Modules\Addons\Hub\Services\Servicios;
use App\Http\Controllers\Controller;
use App\Http\Traits\NotificationTrait;
use App\Models\Project;
use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Services\AgendaOrdenesService;
use App\Modules\Addons\Talento\Services\CorreccionOrdenValidadaService;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Addons\Talento\Support\OrdenReferencia;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TalentoWorkOrderController extends Controller
{
    use NotificationTrait;

    /**
     * David (29-sep, verificando "Órdenes"): talento.work_orders.view lo
     * tiene TECNICO directo (para ver SUS órdenes dentro de "Mi trabajo"),
     * pero data()/show() no tenían NINGÚN scoping — cualquier técnico podía
     * ver las órdenes de CUALQUIER otro colaborador (cliente, teléfono,
     * notas, agenda) con solo omitir/cambiar colaborador_id o pedir un id
     * de orden ajeno. Staff real = talento.work_orders.manage o
     * talento.employees.view (NO talento.work_orders.view, que no
     * distingue).
     */
    private function esStaffOrdenes(): bool
    {
        return auth()->user()->can('talento.work_orders.manage') || auth()->user()->can('talento.employees.view');
    }

    /** Ids de talento_colaboradores que el usuario actual puede ver: uno mismo + su equipo directo. */
    private function idsColaboradoresVisibles(): array
    {
        $miPropioColaborador = Actor::for(auth()->user())->talento();
        if (! $miPropioColaborador) {
            return [];
        }

        return $miPropioColaborador->subordinados()->pluck('id')
            ->push($miPropioColaborador->id)
            ->unique()
            ->values()
            ->all();
    }
    // Mapeo tipo_id → project_id para tareas campo (Capa 6 refinará con lógica dinámica)
    private const TYPE_PROJECT_MAP = [
        1 => 2,  // Instalación nueva → INSTALACIONES
        2 => 5,  // Soporte/reparación → REPARACION
        3 => 3,  // Cambio de equipo → ORDEN DE SERVICIO
        4 => 4,  // Reubicación → CAMBIO DE DOMICILIO
        5 => 9,  // Baja/retiro → Planta Externa Garantias
    ];

    public function __construct(
        private OrdenTrabajoUnifiedService $unified,
        private WhatsAppGateway $whatsapp,
    ) {}

    public function index()
    {
        $this->authorize('talento.work_orders.view');
        return view('addon-talento::talento.ordenes');
    }

    public function data(Request $request)
    {
        $this->authorize('talento.work_orders.view');

        $filtros = $request->only(['colaborador_id', 'status', 'type_id', 'from', 'to', 'search', 'prospecto']);

        if (! $this->esStaffOrdenes()) {
            $idsVisibles = $this->idsColaboradoresVisibles();
            abort_if(empty($idsVisibles), 403);

            if (! empty($filtros['colaborador_id'])) {
                // Pidió un colaborador puntual (ej. la ficha de un
                // colaborador): debe ser uno mismo o su subordinado directo.
                abort_unless(in_array((int) $filtros['colaborador_id'], $idsVisibles, true), 403);
            } else {
                // Lista global (pantalla suelta /talento/ordenes) sin
                // filtro: forzado a uno mismo + su equipo — nunca "todos".
                $filtros['colaborador_id'] = $idsVisibles;
            }
        }

        $result = $this->unified->listForAdmin(
            $filtros,
            (int)($request->per_page ?? 25),
            (int)($request->query('page', 1))
        );

        return response()->json($result);
    }

    /**
     * GET /talento/api/ordenes/carga?desde=&hasta= — órdenes por día, persona y color de su tipo
     * (item #1158): calendario chico de «Asignar trabajo» y agenda de Talento › Órdenes. Quien
     * gestiona órdenes ve a todos; un supervisor, a sí mismo y a su equipo directo.
     */
    public function carga(Request $request, AgendaOrdenesService $agenda)
    {
        $this->authorize('talento.work_orders.view');

        $data = $request->validate([
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ]);
        abort_if(Carbon::parse($data['desde'])->diffInDays(Carbon::parse($data['hasta'])) > 62, 422, 'El rango no puede pasar de dos meses.');

        $ids = null;
        if (! $this->esStaffOrdenes()) {
            $ids = $this->idsColaboradoresVisibles();
            abort_if(empty($ids), 403);
        }

        return response()->json($agenda->carga($data['desde'], $data['hasta'], $ids));
    }

    /**
     * POST /talento/api/ordenes/{id}/reprogramar — mover una orden pendiente o en curso a otro día
     * con un motivo (item #1158). Quien gestiona órdenes o el supervisor directo de quien la tiene.
     */
    public function reprogramar(Request $request, int $id, AgendaOrdenesService $agenda)
    {
        $data = $request->validate([
            'fecha'  => 'required|date',
            'motivo' => 'required|string|min:5|max:500',
        ], [
            'fecha.required'  => 'Elige el nuevo día.',
            'motivo.required' => 'Escribe por qué se mueve la orden.',
            'motivo.min'      => 'Escribe por qué se mueve la orden.',
        ]);

        $task = Task::whereKey($id)->whereNotNull('talento_type_id')->with('users:users.id')->firstOrFail();

        if (! auth()->user()->can('talento.work_orders.manage')) {
            $asignado = $task->users->first()?->id;
            $esSuSupervisor = $asignado && Actor::for(auth()->user())->talento()
                ?->subordinados()
                ->where('user_id', $asignado)
                ->exists();
            abort_unless($esSuSupervisor, 403, 'No puedes reprogramar esta orden.');
        }

        $r = $agenda->reprogramar($task, $data['fecha'], $data['motivo'], auth()->id());
        if (! $r['ok']) {
            return response()->json(['error' => $r['mensaje']], $r['codigo']);
        }

        return response()->json($r);
    }

    public function prospectosCrm(Request $request)
    {
        $this->authorize('talento.work_orders.manage');

        $data = $request->validate([
            'colaborador_id' => 'nullable|exists:talento_colaboradores,id',
            'search'         => 'nullable|string|max:100',
        ]);

        return response()->json(
            $this->unified->searchProspectosCrm($data['colaborador_id'] ?? null, $data['search'] ?? null)
        );
    }


    /**
     * GET /talento/aviso/{id} — clic en la campanita sobre «Nueva tarea asignada» de una orden de
     * Talento (2026-10-27): si la orden es de quien abre el aviso, se marca leído y se abre en su
     * «Mi día»; si no, sigue a Scheduling como siempre.
     */
    public function abrirAviso(string $id)
    {
        $aviso = auth()->user()->notifications()->where('id', $id)->first();
        abort_if(! $aviso, 404);

        $datos = $this->getNotificationAttributes($aviso);
        $task  = ! empty($datos['task_id']) ? Task::find($datos['task_id']) : null;
        $url   = $task ? Actor::for(auth()->user())->urlOrdenEnMiDia($task) : null;
        if (! $url) {
            return redirect('/scheduling/task/read-notification/' . $id);
        }

        if ($aviso->read_at === null) {
            $aviso->markAsRead();
        }

        return redirect($url);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'colaborador_id' => 'required|exists:talento_colaboradores,id',
            'type_id'        => 'required|exists:talento_work_order_types,id',
            'assigned_by'    => 'nullable|exists:users,id',
            'client_id'      => 'nullable|exists:clients,id',
            'crm_lead_id'    => 'nullable|exists:crm_lead_information,id',
            'olt_onu_id'     => 'nullable|exists:olt_onus,id',
            'ticket_id'      => 'nullable|exists:tickets,id',
            // El día es obligatorio (2026-10-27): una orden sin día no le salía al técnico en «Mi día».
            // La hora sigue siendo opcional ("YYYY-MM-DD" o "YYYY-MM-DD HH:mm").
            'scheduled_at'   => 'required|date',
            'notes'          => 'nullable|string',
        ], [
            'scheduled_at.required' => 'Elige el día de la orden.',
            'scheduled_at.date'     => 'El día de la orden no es una fecha válida.',
        ]);

        // "Supervisor" (David, 28-sep) = talento_colaboradores.supervisor_id,
        // NO un rol de Spatie — el supervisor DIRECTO del colaborador puede
        // crearle órdenes aunque no tenga el permiso general de gestión.
        // talento.work_orders.manage sigue siendo la vía normal (admin/
        // DESARROLLADOR); esto SOLO abre la excepción puntual del supervisor
        // sobre SU subordinado, nunca sobre cualquier otro colaborador.
        if (! auth()->user()->can('talento.work_orders.manage')) {
            $esSuSupervisor = \App\Modules\Addons\Talento\Support\Actor::for(auth()->user())->talento()
                ?->subordinados()
                ->where('id', $data['colaborador_id'])
                ->exists();

            abort_unless($esSuSupervisor, 403, 'No tienes permiso para crear órdenes para este colaborador.');
        }

        // G2: la orden nace de un ticket; si no trae cliente se toma el del ticket (customer_lead = clients.id).
        $ticketId = null;
        if (config('unificacion.interruptores.ordenes_desde_ticket_g2') && ! empty($data['ticket_id'])) {
            $ticketId = (int) $data['ticket_id'];
            if (empty($data['client_id']) && empty($data['crm_lead_id'])) {
                $cid = \App\Models\Ticket::whereKey($ticketId)->value('customer_lead');
                $data['client_id'] = $cid && \App\Models\Client::whereKey($cid)->exists() ? (int) $cid : null;
            }
        }

        // Una OT es para un cliente ya alta O para un prospecto del CRM, nunca ambos (decisión q2 Irving).
        if (!empty($data['client_id']) && !empty($data['crm_lead_id'])) {
            return response()->json(['error' => 'Una OT es para un cliente o un prospecto, no ambos.'], 422);
        }

        $type        = TalentoWorkOrderType::findOrFail($data['type_id']);
        $colaborador = TalentoColaborador::findOrFail($data['colaborador_id']);

        // "Colaborador requerido" solo protegía que la FILA exista, no que sea
        // asignable: un colaborador sin user_id vinculado pasaba la validación
        // de arriba y luego el sync() de abajo se saltaba en silencio → orden
        // creada con la columna Colaborador vacía en el listado (caso real
        // detectado: OT #1684). Se corta aquí, antes de crear nada.
        if (! $colaborador->user_id) {
            return response()->json([
                'error' => 'Este colaborador no tiene un usuario de sistema vinculado; no se le puede asignar la orden.',
            ], 422);
        }

        // Solo a personas activas, las mismas que ofrece asignables() (2026-10-27): antes el
        // servidor aceptaba a alguien dado de baja si llegaba su id.
        if (! TalentoColaborador::activos()->whereKey($colaborador->id)->exists()) {
            return response()->json([
                'error' => 'Esta persona ya no está activa; elige a otra para asignarle la orden.',
            ], 422);
        }

        // Parsear scheduled_at → start_date + start_time
        $scheduled = ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;

        // Resolver project_id: mapeo estático tipo→proyecto; fallback al primero disponible
        $projectId = self::TYPE_PROJECT_MAP[$data['type_id']]
            ?? Project::orderBy('id')->value('id');

        // Resolver client_main_information_id desde clients.id si viene client_id
        $clientInfoId = null;
        if (!empty($data['client_id'])) {
            $clientInfoId = \Illuminate\Support\Facades\DB::table('client_main_information')
                ->where('client_id', $data['client_id'])
                ->value('id');
        }

        // Tipo de task: sigue la category del TalentoWorkOrderType (campo o interna)
        $tipo = $type->category ?? 'campo';

        $task = Task::create([
            'tipo'                       => $tipo,
            'talento_type_id'            => $data['type_id'],
            'points'                     => $type->points,
            'is_billable'                => $type->is_billable,
            'project_id'                 => $projectId,
            'client_main_information_id' => $clientInfoId,
            'crm_lead_id'                => $data['crm_lead_id'] ?? null,
            'olt_onu_id'                 => $data['olt_onu_id'] ?? null,
            'ticket_id'                  => $ticketId,
            'start_date'                 => $scheduled?->toDateString(),
            'start_time'                 => $scheduled?->format('Y-m-d H:i:s'),
            'end_time'                   => $scheduled?->copy()->addHour()->format('Y-m-d H:i:s'),
            'description'                => $data['notes'] ?? null,
            'status'                     => 'ToDo',
            'title'                      => '[' . $type->name . '] ' . (! empty($data['notes']) ? mb_substr($data['notes'], 0, 60) : 'Nueva orden'),
            'priority'                   => 'Media',
        ]);

        // Asignar al técnico (task_user pivot)
        if ($colaborador->user_id) {
            $task->users()->sync([$colaborador->user_id]);

            // Sin hora la orden queda a las 00:00: el aviso dice solo el día.
            $cuando = $scheduled
                ? ' para el ' . $scheduled->format($scheduled->format('H:i') === '00:00' ? 'd/m' : 'd/m H:i')
                : '';

            // Push al técnico si tiene tokens FCM registrados (app móvil)
            \App\Modules\Addons\Talento\Controllers\TalentoMobileApiController::sendPushToUser(
                $colaborador->user_id,
                'Nueva orden de trabajo',
                "Se te asignó: {$type->name}{$cuando}",
                ['type' => 'ot_assigned', 'ot_id' => $task->id]
            );

            // Notificación en su usuario (campanita del topbar / Portal de Colaborador):
            // mismo mecanismo que usa Scheduling para "tarea asignada" — Task::
            // sendNotifications() ya sabe escribir la fila en task_notifications +
            // Notification::send(..., StandardNotification) → tabla `notifications`.
            // Aislado: un fallo aquí no debe tumbar la creación de la orden.
            try {
                $task->sendNotifications();
            } catch (\Throwable $e) {
                Log::warning('Talento OT: fallo notificación en-app al crear orden', [
                    'task_id' => $task->id, 'error' => $e->getMessage(),
                ]);
            }

            // WhatsApp al técnico (gateway único de WhatsAppAgent — nunca una
            // integración Evolution propia). Mismo freno maestro que el resto del
            // sistema (WHATSAPP_SENDER_ENABLED, default false → log-only) y mismo
            // aislamiento: un fallo aquí no debe tumbar la creación de la orden.
            $phone = self::normalizeMxWhatsappNumber($colaborador->user->phone ?? null);
            if ($phone) {
                try {
                    $this->whatsapp->sendText(
                        Servicios::slugWhatsApp('talento.ot_asignada'),
                        $phone,
                        "Hola {$colaborador->user->name}, se te asignó una nueva orden de trabajo: "
                            . "{$type->name}{$cuando}. Consulta el detalle en «Mi día» de Talento o en la app."
                    );
                } catch (\Throwable $e) {
                    Log::warning('Talento OT: fallo notificación WhatsApp al crear orden', [
                        'task_id' => $task->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json(
            $this->unified->showForAdmin($task->id),
            201
        );
    }

    /**
     * Referencia de la ruta → [id numérico, origen|null]. Con unificacion.referencia_orden OFF es el
     * comportamiento de siempre ((int) $id, búsqueda en el orden de cada método). ON: acepta T-<id>/L-<id>
     * y un número que exista en ambos orígenes responde 409 con los candidatos.
     */
    private function ref($id): array
    {
        if (! config('unificacion.interruptores.referencia_orden')) {
            return [(int) $id, null];
        }
        $r = OrdenReferencia::resolve($id);
        if ($r['estado'] === OrdenReferencia::AMBIGUA) {
            abort(response()->json([
                'error' => 'La referencia ' . $r['id'] . ' existe como tarea y como orden antigua; usa una de las dos.',
                'candidatos' => $r['candidatos'],
            ], 409));
        }
        if ($r['estado'] === OrdenReferencia::INVALIDA) {
            abort(response()->json(['error' => 'Referencia de orden inválida.'], 422));
        }
        if ($r['estado'] === OrdenReferencia::NO_ENCONTRADA) {
            abort(404);
        }
        return [$r['id'], $r['origen']];
    }

    /** G2: órdenes (tareas con talento_type_id) ligadas a un ticket, para mostrarlas en su ficha. */
    public function ordenesDeTicket($ticketId)
    {
        abort_unless(config('unificacion.interruptores.ordenes_desde_ticket_g2'), 404);

        return Task::with(['talentoType', 'users'])
            ->where('ticket_id', (int) $ticketId)
            ->whereNotNull('talento_type_id')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($t) => [
                'id'       => $t->id,
                'ref'      => 'T-' . $t->id,
                'tipo'     => $t->talentoType?->name,
                'estado'   => $t->status,
                'fecha'    => $t->start_date,
                'asignado' => $t->users->pluck('name')->join(', '),
            ])->values();
    }

    public function show($id)
    {
        $this->authorize('talento.work_orders.view');

        $data = $this->unified->showForAdmin(...$this->ref($id));
        if (! $data) {
            abort(404);
        }

        if (! $this->esStaffOrdenes()) {
            $idsVisibles = $this->idsColaboradoresVisibles();
            abort_unless(
                $data['colaborador_id'] !== null && in_array((int) $data['colaborador_id'], $idsVisibles, true),
                403
            );
        }

        return response()->json($data);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('talento.work_orders.manage');

        $data = $request->validate([
            'colaborador_id' => 'sometimes|exists:talento_colaboradores,id',
            'type_id'        => 'sometimes|exists:talento_work_order_types,id',
            'scheduled_at'   => 'nullable|date',
            'notes'          => 'nullable|string',
            'client_id'      => 'nullable|exists:clients,id',
            'olt_onu_id'     => 'nullable|exists:olt_onus,id',
        ]);

        $ref = $this->ref($id);
        $result = $this->unified->actualizarAdmin($ref[0], $data, $ref[1]);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        return response()->json($result['data']);
    }

    public function changeStatus(Request $request, $id)
    {
        $this->authorize('talento.work_orders.manage');

        $data = $request->validate(['status' => 'required|in:pending,in_progress,completed,cancelled', 'motivo' => 'nullable|string|max:500']);

        $ref = $this->ref($id);
        $result = $this->unified->cambiarEstadoAdmin($ref[0], $data['status'], $ref[1], $data['motivo'] ?? null);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        return response()->json($result['data']);
    }

    public function validateOrder(Request $request, $id)
    {
        $this->authorize('talento.work_orders.validate');

        $result = $this->unified->validarAdmin(...[...$this->ref($id), $request->input('motivo_excepcion')]);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        return response()->json($result['data']);
    }

    /** UNI-T D7.3: corrige una orden ya validada (tipo/puntos). El servicio valida permiso, motivo, interruptor y semana cerrada. */
    public function corregirValidada(Request $request, $id)
    {
        [$ordenId, $origen] = $this->ref($id);
        $cambios = array_filter($request->only(['tipo_id', 'puntos']), fn ($v) => $v !== null && $v !== '');
        $result = app(CorreccionOrdenValidadaService::class)->corregir(
            $origen === 'task' ? 'task' : 'work_order',
            $ordenId,
            $cambios,
            $request->input('motivo'),
            auth()->id()
        );

        if (! $result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        return response()->json($result);
    }

    public function returnOrder(Request $request, $id)
    {
        $this->authorize('talento.work_orders.validate');

        [$ordenId, $origen] = $this->ref($id);
        $result = $this->unified->devolver($ordenId, $request->input('motivo'), $origen);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        return response()->json($result['data']);
    }

    public function addActivity(Request $request, $id)
    {
        $this->authorize('talento.work_orders.manage');

        $data = $request->validate([
            'description'      => 'required|string',
            'duration_minutes' => 'nullable|integer|min:0',
        ]);

        $result = $this->unified->agregarActividadAdmin((int) $id, $data);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        return response()->json($result['data'], 201);
    }

    /**
     * "Asignar trabajo" desde la ficha del cliente o del prospecto (2026-10-22): cada puesto con sus
     * colaboradores activos, para elegir primero el rol y después a la persona. Quien gestiona
     * órdenes ve a todos; un supervisor, solo a su equipo (mismo criterio que store()).
     */
    public function asignables()
    {
        $this->authorize('talento.work_orders.view');

        $query = TalentoColaborador::activos()->with('user.roles');
        if (! auth()->user()->can('talento.work_orders.manage')) {
            $propio = Actor::for(auth()->user())->talento();
            abort_unless($propio, 403, 'No tienes permiso para asignar órdenes de trabajo.');
            $query->where('supervisor_id', $propio->id);
        }

        $porRol = [];
        foreach ($query->get() as $colaborador) {
            $nombre = trim(($colaborador->user?->name ?? '') . ' ' . ($colaborador->user?->father_last_name ?? '')) ?: "#{$colaborador->id}";
            // Desarrollador no es puesto (no sale en el carnet), pero a quien lo tiene también se le
            // asigna trabajo (2026-10-24: "falta el rol desarrollador").
            $roles = $colaborador->rolesDePuesto();
            if ($colaborador->user?->roles->contains('name', 'DESARROLLADOR')) {
                $roles[] = 'DESARROLLADOR';
            }
            foreach ($roles as $rol) {
                $porRol[$rol][] = ['id' => $colaborador->id, 'nombre' => $nombre];
            }
        }

        return response()->json(collect($porRol)
            ->map(fn ($personas, $rol) => [
                'rol'      => $rol,
                'etiqueta' => TalentoColaborador::nombrePuesto($rol),
                'personas' => collect($personas)->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            ])
            ->sortBy('etiqueta', SORT_NATURAL | SORT_FLAG_CASE)
            ->values());
    }

    public function types()
    {
        $this->authorize('talento.work_orders.view');
        return response()->json(TalentoWorkOrderType::orderBy('name')->get());
    }

    public function storeType(Request $request)
    {
        $this->authorize('talento.work_orders.manage');

        $data = $request->validate([
            'name'                => 'required|string|max:120',
            'category'            => 'nullable|string|max:60',
            'color'               => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'points'              => 'required|integer|min:0',
            'is_billable'         => 'required|boolean',
            'requires_validation' => 'required|boolean',
            'active'              => 'boolean',
        ]);

        return response()->json(TalentoWorkOrderType::create($data), 201);
    }

    public function updateType(Request $request, $id)
    {
        $this->authorize('talento.work_orders.manage');

        $type = TalentoWorkOrderType::findOrFail($id);

        $data = $request->validate([
            'name'                => 'sometimes|string|max:120',
            'category'            => 'nullable|string|max:60',
            'color'               => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'points'              => 'sometimes|integer|min:0',
            'is_billable'         => 'sometimes|boolean',
            'requires_validation' => 'sometimes|boolean',
            'active'              => 'sometimes|boolean',
        ]);

        $type->update($data);
        return response()->json($type);
    }

    /**
     * MX 10 dígitos → prefijo 521 (mismo formato que espera whatsapp_conversations.
     * contact_number — ver SendReferralShareListener::normalizeNumber, WhatsAppAgent).
     * Devuelve null si no hay teléfono o no matchea un patrón MX reconocible (evita
     * mandar un número mal formado en vez de fallar silenciosamente en la API).
     */
    private static function normalizeMxWhatsappNumber(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return '521' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            return '521' . substr($digits, 2);
        }
        if (strlen($digits) === 13 && str_starts_with($digits, '521')) {
            return $digits;
        }
        return null;
    }
}
