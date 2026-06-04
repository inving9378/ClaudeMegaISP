<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderActivity;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use Illuminate\Http\Request;

class TalentoWorkOrderController extends Controller
{
    public function index()
    {
        $this->authorize('talento.work_orders.view');
        return view('addon-talento::talento.ordenes');
    }

    public function data(Request $request)
    {
        $this->authorize('talento.work_orders.view');

        $q = TalentoWorkOrder::with(['colaborador.user', 'type', 'assignedBy'])
            ->when($request->colaborador_id, fn($q, $v) => $q->where('colaborador_id', $v))
            ->when($request->status,         fn($q, $v) => $q->where('status', $v))
            ->when($request->type_id,        fn($q, $v) => $q->where('type_id', $v))
            ->when($request->from,           fn($q, $v) => $q->where('scheduled_at', '>=', $v))
            ->when($request->to,             fn($q, $v) => $q->where('scheduled_at', '<=', $v . ' 23:59:59'))
            ->when($request->search, function ($q, $s) {
                $q->whereHas('colaborador.user', fn($u) => $u->where('name', 'like', "%$s%"));
            })
            ->orderBy('scheduled_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return response()->json($q);
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

        $type = TalentoWorkOrderType::findOrFail($data['type_id']);

        $order = TalentoWorkOrder::create(array_merge($data, [
            'points'     => $type->points,
            'is_billable'=> $type->is_billable,
            'status'     => 'pending',
        ]));

        return response()->json($order->load('colaborador.user', 'type'), 201);
    }

    public function show($id)
    {
        $this->authorize('talento.work_orders.view');
        return response()->json(
            TalentoWorkOrder::with(['colaborador.user', 'type', 'assignedBy', 'validatedBy', 'activities.recordedBy'])
                ->findOrFail($id)
        );
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

    public function validate(Request $request, $id)
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
