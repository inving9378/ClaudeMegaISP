<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\NotificationTrait;
use App\Models\Project;
use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderChecklistItem;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderData;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderTemplate;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Services\AgendaOrdenesService;
use App\Modules\Addons\Talento\Services\AvisoOrdenColaborador;
use App\Modules\Addons\Talento\Services\ChoqueAgendaOrdenes;
use App\Modules\Addons\Talento\Services\CorreccionEvidenciasService;
use App\Modules\Addons\Talento\Services\CorreccionOrdenValidadaService;
use App\Modules\Addons\Talento\Services\IncidenciasOrdenService;
use App\Modules\Addons\Talento\Services\OrdenPendienteService;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Addons\Talento\Support\EsquemaCamposOrden;
use App\Modules\Addons\Talento\Support\OrdenEquipo;
use App\Modules\Addons\Talento\Support\OrdenReferencia;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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
    // Mapeo tipo_id → project_id para tareas campo (Capa 6 refinará con lógica dinámica).
    // Item #1200: solo es respaldo de los 5 tipos viejos; manda talento_work_order_types.project_id.
    // Se retira en #1206 (los ids de dev y prod no coinciden). Público desde #1225: editar el tipo de
    // una orden pendiente mueve el proyecto igual que al crearla.
    public const TYPE_PROJECT_MAP = [
        1 => 2,  // Instalación nueva → INSTALACIONES
        2 => 5,  // Soporte/reparación → REPARACION
        3 => 3,  // Cambio de equipo → ORDEN DE SERVICIO
        4 => 4,  // Reubicación → CAMBIO DE DOMICILIO
        5 => 9,  // Baja/retiro → Planta Externa Garantias
    ];

    public function __construct(
        private OrdenTrabajoUnifiedService $unified,
        private AvisoOrdenColaborador $avisos,
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

        // #1225: "Ver eliminadas" — solo quien puede eliminar órdenes.
        if ($request->boolean('eliminadas')) {
            $this->authorize('talento.ordenes.eliminar');
            $filtros['eliminadas'] = true;
        }

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

        // #1326: cuántas órdenes de las que ve esperan atención por una incidencia (botón de la bandeja).
        $result['con_incidencia'] = empty($filtros['eliminadas'])
            ? $this->unified->listForAdmin(['colaborador_id' => $filtros['colaborador_id'] ?? null, 'status' => 'incidencia'], 1, 1)['total']
            : 0;
        // #1286: correcciones que el técnico ya envió y esperan revisión.
        $result['correcciones_por_revisar'] = empty($filtros['eliminadas'])
            ? $this->unified->listForAdmin(['colaborador_id' => $filtros['colaborador_id'] ?? null, 'status' => 'correccion_revisar'], 1, 1)['total']
            : 0;
        // #1286: con la revisión del supervisor encendida, las terminadas que esperan su validación.
        $result['por_validar'] = empty($filtros['eliminadas']) && config('unificacion.interruptores.validacion_separada')
            ? $this->unified->listForAdmin(['colaborador_id' => $filtros['colaborador_id'] ?? null, 'status' => 'completed'], 1, 1)['total']
            : 0;

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
            return $this->respuestaFalla($r); // error + id de la orden con la que choca (#1227)
        }

        return response()->json($r);
    }

    /**
     * GET /talento/api/clientes-orden?search= — clientes para cambiar el de una orden Pendiente
     * (#1228). Mismo motor que el listado de Clientes; mismo permiso que editar la orden.
     */
    public function clientesOrden(Request $request, OrdenPendienteService $ordenes)
    {
        $this->authorize('talento.ordenes.editar');
        $data = $request->validate(['search' => 'required|string|max:100']);

        return response()->json($ordenes->buscarClientes($data['search']));
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
     * GET /talento/api/clientes-referencia?search= — item #1200: buscador del «Cliente de
     * referencia (opcional)» del modal "Nueva orden" para planta externa (la caja que reportó un
     * cliente). Mismo permiso que el buscador de prospectos.
     */
    public function clientesReferencia(Request $request)
    {
        $this->authorize('talento.work_orders.manage');

        $data = $request->validate(['search' => 'required|string|min:2|max:100']);

        return response()->json($this->unified->searchClientesReferencia($data['search']));
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

        // #1225: «orden retirada/eliminada» ya no es de quien abre el aviso (o ya no existe): va a su
        // «Mi día» en vez de a Scheduling, que al técnico le niega el acceso.
        if (! $url && in_array($datos['title'] ?? null, [AvisoOrdenColaborador::TITULO_RETIRADA, AvisoOrdenColaborador::TITULO_ELIMINADA], true)) {
            $propio = Actor::for(auth()->user())->talento();
            $url = $propio ? "/talento/colaborador/{$propio->id}?tab=mi_dia" : '/';
        }

        if (! $url) {
            return redirect('/scheduling/task/read-notification/' . $id);
        }

        if ($aviso->read_at === null) {
            $aviso->markAsRead();
        }

        return redirect($url);
    }

    public function store(Request $request, ChoqueAgendaOrdenes $choques)
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
            // Item #1200: plantilla del tipo y los datos que pide al crear ({key: valor}).
            'template_id'    => 'nullable|integer',
            'datos'          => 'nullable|array',
            // #1325: auxiliares del responsable (ids de talento_colaboradores), solo con órdenes en equipo.
            'auxiliares'     => 'nullable|array',
            'auxiliares.*'   => 'integer',
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

            // #1325: los auxiliares también tienen que ser de su equipo.
            $auxiliaresPedidos = array_map('intval', $data['auxiliares'] ?? []);
            $deSuEquipo = $auxiliaresPedidos
                ? Actor::for(auth()->user())->talento()?->subordinados()->whereIn('id', $auxiliaresPedidos)->count()
                : 0;
            abort_unless($deSuEquipo === count(array_unique($auxiliaresPedidos)), 403, 'Solo puedes poner como auxiliares a personas de tu equipo.');
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

        // Item #1200: planta externa (habilitar caja, montaje de fibra…). Sin prospecto del CRM: el
        // cliente, si viene, es solo la referencia de la caja reportada. El proyecto sale del tipo,
        // sin el respaldo de "primer proyecto" (asignaría uno equivocado).
        if ($type->esPlantaExterna()) {
            if (! empty($data['crm_lead_id'])) {
                return $this->rechazo(['crm_lead_id' => 'Las órdenes de planta externa no llevan prospecto del CRM. Si la orden es por una caja que reportó un cliente, elígelo como cliente de referencia.']);
            }
            if (! $type->project_id) {
                return $this->rechazo(['type_id' => "El tipo «{$type->name}» todavía no tiene proyecto asignado. Asígnalo en la configuración de tipos de orden antes de crear órdenes de este tipo."]);
            }
        }

        $conPlantilla = $this->resolverPlantilla($type, $data);
        if ($conPlantilla instanceof JsonResponse) {
            return $conPlantilla;
        }

        // #1325: auxiliares (equipo). Sin el interruptor, pedir auxiliares se rechaza.
        $auxiliares = OrdenEquipo::resolverAuxiliares($data['auxiliares'] ?? [], $colaborador->id);
        if (is_string($auxiliares)) {
            return $this->rechazo(['auxiliares' => $auxiliares]);
        }

        // Parsear scheduled_at → start_date + start_time
        $scheduled = ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;

        // Misma regla de choque de agenda que Editar y Reprogramar (#1227). Sin hora no choca.
        if ($choque = $choques->verificar($colaborador->user, $scheduled)) {
            return response()->json(['error' => $choque['mensaje'], 'choque' => $choque['choque']], 422);
        }
        // #1325: la misma regla para cada auxiliar.
        foreach ($auxiliares as $auxiliar) {
            if ($choque = $choques->verificar($auxiliar->user, $scheduled)) {
                return response()->json(['error' => $choque['mensaje'], 'choque' => $choque['choque']], 422);
            }
        }

        // Resolver project_id: el del tipo; si no tiene, el mapeo estático de los tipos viejos y, como
        // último respaldo (solo tipos de cliente; planta externa ya se rechazó arriba), el primero.
        $projectId = $type->project_id
            ?? self::TYPE_PROJECT_MAP[$data['type_id']]
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

        $titulo = '[' . $type->name . '] ' . (! empty($data['notes']) ? mb_substr($data['notes'], 0, 60) : 'Nueva orden');
        $descripcion = $data['notes'] ?? null;
        $prioridad = 'Media';
        if ($conPlantilla) {
            [$plantilla, $esquema, $valores, $checklist] = $conPlantilla;
            $titulo = EsquemaCamposOrden::titulo($type->name, $plantilla->title, $esquema, $valores);
            // La app y el portal solo muestran tasks.description: ahí van los datos en renglones
            // "Etiqueta: valor", las instrucciones, los materiales y las notas del usuario.
            $descripcion = EsquemaCamposOrden::descripcion($esquema, $valores, $plantilla->description, $checklist, $data['notes'] ?? null);
            $prioridad = $plantilla->priority ?: 'Media';
        }

        // Tarea, asignación, datos y checklist en una sola transacción: o queda todo o nada.
        $task = DB::transaction(function () use ($tipo, $data, $type, $projectId, $clientInfoId, $ticketId, $scheduled, $titulo, $descripcion, $prioridad, $conPlantilla, $colaborador, $auxiliares) {
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
                'description'                => $descripcion,
                'status'                     => 'ToDo',
                'title'                      => $titulo,
                'priority'                   => $prioridad,
            ]);

            // Asignar al técnico y, con órdenes en equipo (#1325), a sus auxiliares (task_user + participantes).
            OrdenEquipo::asignar($task, $colaborador->user_id, $auxiliares->pluck('user_id')->all());

            if ($conPlantilla) {
                [$plantilla, $esquema, $valores, $checklist] = $conPlantilla;
                TalentoWorkOrderData::create([
                    'tarea_id'        => $task->id,
                    'template_id'     => $plantilla->id,
                    'schema_snapshot' => $esquema,
                    'values'          => (object) $valores,
                    'created_by'      => auth()->id(),
                    'updated_by'      => auth()->id(),
                ]);
                foreach ($checklist as $i => $c) {
                    TalentoWorkOrderChecklistItem::create([
                        'tarea_id'   => $task->id,
                        'label'      => $c['label'],
                        'cantidad'   => $c['cantidad'],
                        'unidad'     => $c['unidad'],
                        'sort_order' => ($i + 1) * 10,
                    ]);
                }
            }

            return $task;
        });

        // Avisos al técnico. Fuera de la transacción: un fallo aquí no deshace la orden.
        if ($colaborador->user_id) {
            // Push a la app + campanita + WhatsApp (gateway único, freno WHATSAPP_SENDER_ENABLED).
            // Mismos textos de siempre; desde #1225 viven en AvisoOrdenColaborador porque editar y
            // eliminar una orden también avisan. Campanita y WhatsApp aislados: no tumban la creación.
            $this->avisos->asignada($task, $colaborador->user, $type->name, $scheduled);
        }
        foreach ($auxiliares as $auxiliar) {
            $this->avisos->asignada($task, $auxiliar->user, $type->name, $scheduled, 'al crear orden como auxiliar');
        }

        return response()->json(
            $this->unified->showForAdmin($task->id),
            201
        );
    }

    /** 422 con un mensaje general (lo que muestra el modal) y el mapa por campo (Fase B). */
    private function rechazo(array $errors): JsonResponse
    {
        return response()->json(['error' => (string) reset($errors), 'errors' => $errors], 422);
    }

    /**
     * Item #1200: plantilla de la orden y sus datos de creación ya validados.
     *
     * - Planta externa con plantillas activas: la plantilla es obligatoria.
     * - La plantilla debe estar activa y ser del mismo tipo.
     * - `datos` se valida contra el esquema: tipos, obligatorios de creación, opciones; llaves
     *   desconocidas o de cierre se rechazan.
     *
     * @return null|JsonResponse|array{0: TalentoWorkOrderTemplate, 1: array, 2: array, 3: array}
     *         null = orden sin plantilla (como siempre); array = [plantilla, esquema, valores, checklist]
     */
    private function resolverPlantilla(TalentoWorkOrderType $type, array $data): null|JsonResponse|array
    {
        $datos = $data['datos'] ?? [];

        if (empty($data['template_id'])) {
            if ($type->esPlantaExterna() && $type->templates()->active()->exists()) {
                return $this->rechazo(['template_id' => 'Elige la plantilla de la orden.']);
            }
            if (! empty($datos)) {
                return $this->rechazo(['datos' => 'Los datos de la orden solo se capturan con una plantilla.']);
            }
            return null;
        }

        $plantilla = TalentoWorkOrderTemplate::active()->whereKey($data['template_id'])->first();
        if (! $plantilla || (int) $plantilla->type_id !== (int) $type->id) {
            return $this->rechazo(['template_id' => 'La plantilla no existe, está desactivada o no corresponde al tipo de orden elegido.']);
        }

        $esquema = EsquemaCamposOrden::normalizarEsquema($plantilla->fields ?? []);
        $checklist = EsquemaCamposOrden::normalizarChecklist($plantilla->checklist ?? []);
        if ($esquema['errors'] || $checklist['errors']) {
            Log::warning('Talento OT: plantilla con esquema inválido', [
                'template_id' => $plantilla->id, 'errores' => $esquema['errors'] + $checklist['errors'],
            ]);
            return $this->rechazo(['template_id' => "La plantilla «{$plantilla->name}» tiene un error de configuración; revísala en la configuración de plantillas."]);
        }

        $r = EsquemaCamposOrden::validarDatos($esquema['fields'], $datos, EsquemaCamposOrden::MOMENTO_CREACION);
        if ($r['errors']) {
            // Mensaje general con la etiqueta del primer campo con error ("Zona: Este campo es obligatorio.").
            $labels = array_column($esquema['fields'], 'label', 'key');
            $primera = array_key_first($r['errors']);
            $key = explode('.', $primera)[1] ?? '';
            $errores = $r['errors'];
            $general = isset($labels[$key]) ? $labels[$key] . ': ' . $errores[$primera] : $errores[$primera];
            return response()->json(['error' => $general, 'errors' => $errores], 422);
        }

        return [$plantilla, $esquema['fields'], $r['values'], $checklist['items']];
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

        // #1225: quien puede eliminar órdenes también abre las eliminadas (filtro "Ver eliminadas").
        [$ordenId, $origen] = $this->ref($id);
        $data = $this->unified->showForAdmin($ordenId, $origen, auth()->user()->can('talento.ordenes.eliminar'));
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

    /**
     * PUT /talento/api/ordenes/{id} — editar una orden mientras está Pendiente (item #1225):
     * colaborador, cliente/prospecto, tipo (recalcula puntos), fecha y hora (de hoy en adelante, sin
     * chocar con otra orden de la persona) y notas. Cada campo cambiado queda en el historial.
     * Antes (sin pantalla que lo usara) pedía talento.work_orders.manage y dejaba editar En curso.
     */
    public function update(Request $request, $id, OrdenPendienteService $ordenes)
    {
        $this->authorize('talento.ordenes.editar');

        $data = $request->validate([
            'colaborador_id' => 'sometimes|required|exists:talento_colaboradores,id',
            'type_id'        => 'sometimes|required|exists:talento_work_order_types,id',
            'client_id'      => 'sometimes|nullable|exists:clients,id',
            'crm_lead_id'    => 'sometimes|nullable|exists:crm_lead_information,id',
            'scheduled_at'   => 'sometimes|required|date',
            'notes'          => 'sometimes|nullable|string|max:5000',
            // #1200: órdenes con plantilla — todos los campos de creación.
            'datos'          => 'sometimes|nullable|array',
            // #1325: auxiliares (reemplazan a los de antes); sin la llave se conservan.
            'auxiliares'     => 'sometimes|array',
            'auxiliares.*'   => 'integer',
        ], [
            'colaborador_id.required' => 'Selecciona un colaborador.',
            'type_id.required'        => 'Selecciona un tipo de orden.',
            'scheduled_at.required'   => 'Elige el día de la orden.',
            'scheduled_at.date'       => 'El día de la orden no es una fecha válida.',
        ]);

        [$ordenId, $origen] = $this->ref($id);
        if ($antigua = $this->rechazoOrdenAntigua($ordenId, $origen)) {
            return $antigua;
        }

        $r = $ordenes->editar($ordenId, $data, (int) auth()->id());
        if (! $r['ok']) {
            return $this->respuestaFalla($r);
        }

        return response()->json($this->unified->showForAdmin($ordenId, OrdenReferencia::ORIGEN_TAREA) + ['cambios' => $r['cambios']]);
    }

    /**
     * DELETE /talento/api/ordenes/{id} — eliminar (borrado lógico) una orden mientras está Pendiente
     * (item #1225), con motivo obligatorio. No toca el prospecto, el ticket ni el cliente ligados;
     * la respuesta dice si estaba ligada a alguno para que la pantalla lo avise.
     */
    public function destroy(Request $request, $id, OrdenPendienteService $ordenes)
    {
        $this->authorize('talento.ordenes.eliminar');

        $data = $request->validate([
            'motivo'  => ['required', Rule::in(array_keys(OrdenPendienteService::MOTIVOS_ELIMINACION))],
            'detalle' => 'nullable|string|max:400|required_if:motivo,otro',
        ], [
            'motivo.required'     => 'Elige el motivo de la eliminación.',
            'motivo.in'           => 'Elige un motivo de la lista.',
            'detalle.required_if' => 'Escribe el motivo.',
        ]);

        [$ordenId, $origen] = $this->ref($id);
        if ($antigua = $this->rechazoOrdenAntigua($ordenId, $origen)) {
            return $antigua;
        }

        $r = $ordenes->eliminar($ordenId, $data['motivo'], $data['detalle'] ?? null, (int) auth()->id());
        if (! $r['ok']) {
            return $this->respuestaFalla($r);
        }

        return response()->json(['ok' => true, 'id' => $ordenId, 'ligada' => $r['ligada']]);
    }

    /**
     * POST /talento/api/ordenes/{id}/incidencia/regresar — #1326: la orden que el técnico reportó con
     * «No puedo completar» vuelve a su lista sin cambios y la incidencia queda atendida. Para cambiarla
     * de día, de persona o eliminarla están Reprogramar, Editar y Eliminar, que también la atienden.
     */
    public function regresarIncidencia($id, IncidenciasOrdenService $incidencias)
    {
        $this->authorize('talento.ordenes.editar');

        [$ordenId, $origen] = $this->ref($id);
        if ($antigua = $this->rechazoOrdenAntigua($ordenId, $origen)) {
            return $antigua;
        }
        $task = Task::whereKey($ordenId)->whereNotNull('talento_type_id')->first();
        abort_unless($task, 404, 'Orden no encontrada.');

        if (! $incidencias->atender($task->id, 'regresada', (int) auth()->id())) {
            return response()->json(['error' => 'Esta orden ya no tiene una incidencia por atender. Actualiza la lista.'], 422);
        }

        return response()->json($this->unified->showForAdmin($task->id, OrdenReferencia::ORIGEN_TAREA));
    }

    /**
     * POST /talento/api/ordenes/{id}/correccion — #1286: sobre una orden terminada, el supervisor le pide
     * al técnico fotos de corrección con un motivo. La orden no cambia de estado ni de puntos; el técnico
     * la vuelve a ver en «Mi día» de la app hasta que envíe las fotos.
     */
    public function pedirCorreccion(Request $request, $id, CorreccionEvidenciasService $correcciones, AvisoOrdenColaborador $avisos)
    {
        $this->authorize('talento.work_orders.validate');
        $data = $request->validate(['motivo' => 'required|string|min:5|max:1000']);

        $task = $this->tareaParaCorreccion($id);
        $result = $correcciones->pedir($task, trim($data['motivo']), (int) auth()->id());
        if (! $result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code']);
        }

        $tipo = $task->talentoType?->name ?? 'Orden de trabajo';
        foreach ($task->users as $usuario) {
            $avisos->correccionPedida($task, $usuario, $tipo, trim($data['motivo']));
        }

        return response()->json($this->unified->showForAdmin($task->id, OrdenReferencia::ORIGEN_TAREA));
    }

    /** POST /talento/api/ordenes/{id}/correccion/cerrar — #1286: dar por buena la corrección enviada o cancelarla. */
    public function cerrarCorreccion(Request $request, $id, CorreccionEvidenciasService $correcciones)
    {
        $this->authorize('talento.work_orders.validate');
        $data = $request->validate(['cierre' => ['required', Rule::in(['aceptada', 'cancelada'])]]);

        $task = $this->tareaParaCorreccion($id);
        $result = $correcciones->cerrar($task->id, $data['cierre'], (int) auth()->id());
        if (! $result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code']);
        }

        return response()->json($this->unified->showForAdmin($task->id, OrdenReferencia::ORIGEN_TAREA));
    }

    /** La orden (de `tasks`) a la que se le pide o cierra una corrección, con el mismo alcance de equipo que Validar. */
    private function tareaParaCorreccion($id): Task
    {
        [$ordenId, $origen] = $this->ref($id);
        if ($antigua = $this->rechazoOrdenAntigua($ordenId, $origen)) {
            abort($antigua);
        }
        $task = Task::with(['users', 'talentoType'])->whereKey($ordenId)->whereNotNull('talento_type_id')->first();
        abort_unless($task, 404, 'Orden no encontrada.');
        abort_unless($this->unified->puedeValidarEquipo($task->users->pluck('id')->all()), response()->json([
            'error' => 'Solo puedes pedir correcciones en órdenes de tu equipo.',
        ], 403));

        return $task;
    }

    /** Las órdenes de la tabla vieja (talento_work_orders) no se editan ni eliminan desde aquí. */
    private function rechazoOrdenAntigua(int $ordenId, ?string $origen)
    {
        $esTarea = $origen !== OrdenReferencia::ORIGEN_ANTIGUA
            && Task::withTrashed()->whereKey($ordenId)->whereNotNull('talento_type_id')->exists();
        if (! $esTarea && $origen !== OrdenReferencia::ORIGEN_TAREA && TalentoWorkOrder::whereKey($ordenId)->exists()) {
            return response()->json(['error' => 'Las órdenes antiguas son de solo lectura.'], 422);
        }

        return null;
    }

    private function respuestaFalla(array $r)
    {
        return response()->json(array_filter([
            'error'           => $r['mensaje'],
            'ya_no_pendiente' => $r['ya_no_pendiente'] ?? null,
            'choque'          => $r['choque'] ?? null,
            'vinculos'        => $r['vinculos'] ?? null, // #1228: por qué no se le cambia el cliente
            // #1200: errores por campo de los datos de la plantilla, para marcarlos en el formulario.
            'errors'          => $r['errors'] ?? null,
        ], fn ($v) => $v !== null), $r['codigo']);
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

    /**
     * POST /talento/api/ordenes/{id}/return — revisión del supervisor (#1286, sobre UNI-T D3b): la
     * orden terminada que todavía no valida regresa al técnico con una nota que ve en la app. Él la
     * corrige y la vuelve a terminar; sus puntos cuentan cuando se valide.
     */
    public function returnOrder(Request $request, $id, CorreccionEvidenciasService $correcciones, AvisoOrdenColaborador $avisos)
    {
        $this->authorize('talento.work_orders.validate');
        $data = $request->validate(['motivo' => 'required|string|min:5|max:1000']);

        $task = $this->tareaParaCorreccion($id);
        $result = $correcciones->devolver($task, trim($data['motivo']), (int) auth()->id());
        if (! $result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        $tipo = $task->talentoType?->name ?? 'Orden de trabajo';
        foreach ($task->users as $usuario) {
            $avisos->devuelta($task, $usuario, $tipo, trim($data['motivo']));
        }

        return response()->json($this->unified->showForAdmin($task->id, OrdenReferencia::ORIGEN_TAREA));
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

    /**
     * GET /talento/api/ordenes/asignacion — quiénes marcaron asistencia hoy, para elegir a quién se
     * asigna una orden (#1324), y si se pueden poner auxiliares (#1325). Mismo alcance que asignables(): quien gestiona órdenes ve a todos; un
     * supervisor, a su equipo directo. Con la misma forma que el buscador de colaboradores del
     * formulario ({id, type, user}), más la hora de entrada y si ya marcó salida.
     */
    public function asignacion()
    {
        $this->authorize('talento.work_orders.view');

        $query = TalentoColaborador::activos()->with('user:id,name,father_last_name,mother_last_name');
        if (! auth()->user()->can('talento.work_orders.manage')) {
            $propio = Actor::for(auth()->user())->talento();
            abort_unless($propio, 403, 'No tienes permiso para asignar órdenes de trabajo.');
            $query->where('supervisor_id', $propio->id);
        }

        $hoy = now()->toDateString();
        $marcas = DB::table('talento_attendances')
            ->whereDate('check_in_at', $hoy)
            ->orderBy('check_in_at')
            ->get(['colaborador_id', 'check_in_at', 'check_out_at'])
            ->groupBy('colaborador_id');

        $presentes = $query->whereIn('id', $marcas->keys())->get()
            ->map(function (TalentoColaborador $c) use ($marcas) {
                $suyas = $marcas[$c->id];
                return [
                    'id'      => $c->id,
                    'type'    => $c->type,
                    'user'    => $c->user?->only(['name', 'father_last_name', 'mother_last_name']),
                    'entrada' => Carbon::parse($suyas->first()->check_in_at)->format('H:i'),
                    // Ya se fue si su última marca del día tiene salida.
                    'salio'   => $suyas->last()->check_out_at !== null,
                ];
            })
            // Primero quienes siguen trabajando, por hora de llegada; al final los que ya se fueron.
            ->sortBy(fn ($p) => ($p['salio'] ? '1' : '0') . $p['entrada'])
            ->values();

        return response()->json([
            'fecha'     => $hoy,
            'presentes' => $presentes,
            // #1325: si se pueden poner auxiliares y cómo se reparten los puntos (para avisarlo al asignar).
            'equipo'    => [
                'activo'         => OrdenEquipo::activo(),
                'max_auxiliares' => OrdenEquipo::MAX_AUXILIARES,
                'reparto'        => OrdenEquipo::reparto(),
            ],
        ]);
    }

    public function types()
    {
        $this->authorize('talento.work_orders.view');
        return response()->json(TalentoWorkOrderType::orderBy('name')->get());
    }

    /**
     * GET /talento/api/order-templates?type_id= — plantillas activas de un tipo de orden (item
     * #1200): campos (ya normalizados y en orden), checklist, prioridad e instrucciones. Lo
     * consume el modal "Nueva orden" para dibujar los campos que pide al crear.
     */
    public function plantillas(Request $request)
    {
        $this->authorize('talento.work_orders.view');

        $data = $request->validate(['type_id' => 'required|integer|exists:talento_work_order_types,id']);

        return response()->json(
            TalentoWorkOrderTemplate::active()
                ->where('type_id', $data['type_id'])
                ->orderBy('sort_order')->orderBy('name')
                ->get()
                ->map(fn (TalentoWorkOrderTemplate $t) => [
                    'id'                => $t->id,
                    'type_id'           => $t->type_id,
                    'name'              => $t->name,
                    'title'             => $t->title,
                    'description'       => $t->description,
                    'priority'          => $t->priority,
                    'estimated_minutes' => $t->estimated_minutes,
                    'fields'            => EsquemaCamposOrden::normalizarEsquema($t->fields ?? [])['fields'],
                    'checklist'         => EsquemaCamposOrden::normalizarChecklist($t->checklist ?? [])['items'],
                ])
                ->values()
        );
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

}
