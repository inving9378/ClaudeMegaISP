<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Services\LevelService;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TalentoWorkOrderController extends Controller
{
    // Mapeo tipo_id → project_id para tareas campo (Capa 6 refinará con lógica dinámica)
    private const TYPE_PROJECT_MAP = [
        1 => 2,  // Instalación nueva → INSTALACIONES
        2 => 5,  // Soporte/reparación → REPARACION
        3 => 3,  // Cambio de equipo → ORDEN DE SERVICIO
        4 => 4,  // Reubicación → CAMBIO DE DOMICILIO
        5 => 9,  // Baja/retiro → Planta Externa Garantias
    ];

    public function __construct(private OrdenTrabajoUnifiedService $unified) {}

    public function index()
    {
        $this->authorize('talento.work_orders.view');
        return view('addon-talento::talento.ordenes');
    }

    public function data(Request $request)
    {
        $this->authorize('talento.work_orders.view');

        $result = $this->unified->listForAdmin(
            $request->only(['colaborador_id', 'status', 'type_id', 'from', 'to', 'search']),
            (int)($request->per_page ?? 25),
            (int)($request->query('page', 1))
        );

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $this->authorize('talento.work_orders.manage');

        $data = $request->validate([
            'colaborador_id' => 'required|exists:talento_colaboradores,id',
            'type_id'        => 'required|exists:talento_work_order_types,id',
            'assigned_by'    => 'nullable|exists:users,id',
            'client_id'      => 'nullable|exists:clients,id',
            'olt_onu_id'     => 'nullable|exists:olt_onus,id',
            'scheduled_at'   => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        $type        = TalentoWorkOrderType::findOrFail($data['type_id']);
        $colaborador = TalentoColaborador::findOrFail($data['colaborador_id']);

        // Gating por nivel (Fase 7b): si el tipo requiere un nivel, validar rank del colaborador
        if ($type->required_level_id) {
            $levelSvc = app(LevelService::class);
            if (!$levelSvc->satisfiesRequirement($colaborador->level_id, $type->required_level_id)) {
                $reqLevel = \App\Modules\Addons\Talento\Models\TalentoLevel::find($type->required_level_id);
                return response()->json([
                    'error'          => "El colaborador no tiene el nivel requerido para este tipo de orden ({$reqLevel?->name}).",
                    'required_level' => $reqLevel,
                ], 422);
            }
        }

        // Parsear scheduled_at → start_date + start_time
        $scheduled = $data['scheduled_at'] ? Carbon::parse($data['scheduled_at']) : null;

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
            'olt_onu_id'                 => $data['olt_onu_id'] ?? null,
            'start_date'                 => $scheduled?->toDateString(),
            'start_time'                 => $scheduled?->format('H:i:s'),
            'description'                => $data['notes'] ?? null,
            'status'                     => 'ToDo',
            'title'                      => '[' . $type->name . '] ' . ($data['notes'] ? mb_substr($data['notes'], 0, 60) : 'Nueva orden'),
            'priority'                   => 'Media',
        ]);

        // Asignar al técnico (task_user pivot)
        if ($colaborador->user_id) {
            $task->users()->sync([$colaborador->user_id]);

            // Push al técnico si tiene tokens FCM registrados
            \App\Modules\Addons\Talento\Controllers\TalentoMobileApiController::sendPushToUser(
                $colaborador->user_id,
                'Nueva orden de trabajo',
                "Se te asignó: {$type->name}" . ($scheduled ? ' para el ' . $scheduled->format('d/m H:i') : ''),
                ['type' => 'ot_assigned', 'ot_id' => $task->id]
            );
        }

        return response()->json(
            $this->unified->showForAdmin($task->id),
            201
        );
    }

    public function show($id)
    {
        $this->authorize('talento.work_orders.view');

        $data = $this->unified->showForAdmin((int)$id);
        if (! $data) {
            abort(404);
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

        $result = $this->unified->actualizarAdmin((int) $id, $data);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        return response()->json($result['data']);
    }

    public function changeStatus(Request $request, $id)
    {
        $this->authorize('talento.work_orders.manage');

        $data = $request->validate(['status' => 'required|in:pending,in_progress,completed,cancelled']);

        $result = $this->unified->cambiarEstadoAdmin((int) $id, $data['status']);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], $result['status_code'] ?? 422);
        }

        return response()->json($result['data']);
    }

    public function validateOrder(Request $request, $id)
    {
        $this->authorize('talento.work_orders.validate');

        $result = $this->unified->validarAdmin((int) $id);

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
            'points'              => 'sometimes|integer|min:0',
            'is_billable'         => 'sometimes|boolean',
            'requires_validation' => 'sometimes|boolean',
            'active'              => 'sometimes|boolean',
        ]);

        $type->update($data);
        return response()->json($type);
    }
}
