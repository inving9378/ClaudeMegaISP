<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderActivity;
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

        $order = TalentoWorkOrder::findOrFail($id);

        if (in_array($order->status, ['validated', 'cancelled'])) {
            return response()->json(['error' => 'No se puede editar una orden validada o cancelada.'], 422);
        }

        $data = $request->validate([
            'colaborador_id' => 'sometimes|exists:talento_colaboradores,id',
            'type_id'        => 'sometimes|exists:talento_work_order_types,id',
            'scheduled_at'   => 'nullable|date',
            'notes'          => 'nullable|string',
            'client_id'      => 'nullable|exists:clients,id',
            'olt_onu_id'     => 'nullable|exists:olt_onus,id',
        ]);

        // Refresh points/billable if type changes
        if (isset($data['type_id']) && $data['type_id'] != $order->type_id) {
            $type = TalentoWorkOrderType::find($data['type_id']);
            $data['points']      = $type->points;
            $data['is_billable'] = $type->is_billable;
        }

        $order->update($data);

        return response()->json($order->load('colaborador.user', 'type'));
    }

    public function changeStatus(Request $request, $id)
    {
        $this->authorize('talento.work_orders.manage');

        $order = TalentoWorkOrder::findOrFail($id);

        $data = $request->validate(['status' => 'required|in:pending,in_progress,completed,cancelled']);

        $transitions = [
            'pending'     => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed'   => [],   // can only go to validated via validate()
            'validated'   => [],
            'cancelled'   => [],
        ];

        if (!in_array($data['status'], $transitions[$order->status] ?? [])) {
            return response()->json(['error' => "Transición inválida: {$order->status} → {$data['status']}"], 422);
        }

        $timestamps = [
            'in_progress' => ['started_at'   => now()],
            'completed'   => ['completed_at' => now()],
        ];

        $order->update(array_merge(['status' => $data['status']], $timestamps[$data['status']] ?? []));

        return response()->json($order);
    }

    public function validateOrder(Request $request, $id)
    {
        $this->authorize('talento.work_orders.validate');

        $order = TalentoWorkOrder::findOrFail($id);

        if ($order->status !== 'completed') {
            return response()->json(['error' => 'Solo se pueden validar órdenes con estado completada.'], 422);
        }

        $order->update([
            'status'       => 'validated',
            'validated_at' => now(),
            'validated_by' => auth()->id(),
        ]);

        // Fase 4b: fire health bonus evaluation and warranty window refresh (async-safe: catch any failure)
        try {
            app(\App\Modules\Addons\Talento\Services\HealthBonusService::class)->evaluate($order->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Health bonus evaluation failed', ['order_id' => $order->id, 'err' => $e->getMessage()]);
        }

        // Refresh warranty window for billable installations/repairs
        if ($order->is_billable && $order->client_id) {
            try {
                app(\App\Modules\Addons\Talento\Services\WarrantyWindowService::class)->refreshWindow($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Warranty window refresh failed', ['order_id' => $order->id, 'err' => $e->getMessage()]);
            }
        }

        return response()->json($order->load('validatedBy'));
    }

    public function addActivity(Request $request, $id)
    {
        $this->authorize('talento.work_orders.manage');

        $order = TalentoWorkOrder::findOrFail($id);

        $data = $request->validate([
            'description'      => 'required|string',
            'duration_minutes' => 'nullable|integer|min:0',
        ]);

        $activity = TalentoWorkOrderActivity::create([
            'work_order_id'    => $order->id,
            'description'      => $data['description'],
            'duration_minutes' => $data['duration_minutes'] ?? 0,
            'recorded_by'      => auth()->id(),
        ]);

        return response()->json($activity, 201);
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
