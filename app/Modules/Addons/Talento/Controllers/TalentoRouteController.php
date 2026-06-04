<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoLocationPing;
use App\Modules\Addons\Talento\Models\TalentoRoute;
use App\Modules\Addons\Talento\Models\TalentoRouteStop;
use App\Modules\Addons\Talento\Services\RouteDeviationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TalentoRouteController extends Controller
{
    public function __construct(private RouteDeviationService $deviationService) {}

    public function index()
    {
        $this->authorize('talento.routes.view');
        return view('addon-talento::talento.rutas');
    }

    public function data(Request $request)
    {
        $this->authorize('talento.routes.view');

        $q = TalentoRoute::with(['colaborador.user'])
            ->when($request->colaborador_id, fn($q, $v) => $q->where('colaborador_id', $v))
            ->when($request->date,           fn($q, $v) => $q->where('date', $v))
            ->when($request->status,         fn($q, $v) => $q->where('status', $v))
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($q);
    }

    public function store(Request $request)
    {
        $this->authorize('talento.routes.manage');

        $data = $request->validate([
            'colaborador_id' => 'required|exists:talento_colaboradores,id',
            'date'           => 'required|date',
            'work_order_ids' => 'required|array|min:1|max:8',
            'work_order_ids.*' => 'exists:talento_work_orders,id',
        ]);

        $route = TalentoRoute::updateOrCreate(
            ['colaborador_id' => $data['colaborador_id'], 'date' => $data['date']],
            ['status' => 'draft']
        );

        // Replace stops
        TalentoRouteStop::where('route_id', $route->id)->delete();

        foreach (array_values($data['work_order_ids']) as $seq => $woId) {
            TalentoRouteStop::create([
                'route_id'       => $route->id,
                'work_order_id'  => $woId,
                'sequence'       => $seq + 1,
            ]);
        }

        return response()->json($route->load('stops.workOrder'), 201);
    }

    public function show($id)
    {
        $this->authorize('talento.routes.view');

        $route = TalentoRoute::with([
            'colaborador.user',
            'stops.workOrder',
            'deviations',
        ])->findOrFail($id);

        // Fetch today's pings for the map
        $attendanceIds = DB::table('talento_attendances')
            ->where('colaborador_id', $route->colaborador_id)
            ->whereDate('check_in_at', $route->date)
            ->pluck('id');

        $pings = [];
        if ($attendanceIds->isNotEmpty()) {
            $pings = TalentoLocationPing::whereIn('attendance_id', $attendanceIds)
                ->orderBy('recorded_at')
                ->get(['latitude', 'longitude', 'accuracy_m', 'recorded_at'])
                ->toArray();
        }

        return response()->json(array_merge($route->toArray(), ['pings' => $pings]));
    }

    public function activate($id)
    {
        $this->authorize('talento.routes.manage');

        $route = TalentoRoute::findOrFail($id);
        $route->update(['status' => 'active']);
        return response()->json($route);
    }

    /** Run deviation analysis on demand */
    public function analyzeDeviations($id)
    {
        $this->authorize('talento.routes.manage');

        $route = TalentoRoute::with('stops.workOrder')->findOrFail($id);
        $deviations = $this->deviationService->analyze($route);

        return response()->json([
            'deviations_found' => count($deviations),
            'deviations'       => $deviations,
        ]);
    }

    public function reorderStops(Request $request, $id)
    {
        $this->authorize('talento.routes.manage');

        $data = $request->validate([
            'order' => 'required|array',
            'order.*.stop_id' => 'required|integer',
            'order.*.sequence' => 'required|integer|min:1',
        ]);

        foreach ($data['order'] as $item) {
            TalentoRouteStop::where('route_id', $id)->where('id', $item['stop_id'])
                ->update(['sequence' => $item['sequence']]);
        }

        return response()->json(['ok' => true]);
    }
}
