<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoLocationPing;
use App\Modules\Addons\Talento\Models\TalentoRoute;
use App\Modules\Addons\Talento\Models\TalentoRouteStop;
use App\Modules\Addons\Talento\Services\RouteDeviationService;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TalentoRouteController extends Controller
{
    public function __construct(private RouteDeviationService $deviationService) {}

    /**
     * Ver rutas de un colaborador puntual: uno mismo, su supervisor
     * directo, o el permiso de STAFF — mismo criterio que
     * TalentoCompensacionController::puedeVerCompensacionDe().
     */
    private function puedeVerRutasDe($colaboradorId): bool
    {
        if (auth()->user()->can('talento.routes.view')) {
            return true;
        }

        $miPropioColaborador = Actor::for(auth()->user())->talento();
        if (! $miPropioColaborador) {
            return false;
        }

        return (string) $miPropioColaborador->id === (string) $colaboradorId
            || $miPropioColaborador->subordinados()->where('id', $colaboradorId)->exists();
    }

    public function index()
    {
        $this->authorize('talento.routes.view');
        return view('addon-talento::talento.rutas');
    }

    public function data(Request $request)
    {
        // Sin filtro por colaborador_id = listado global → exige el
        // permiso de STAFF de verdad. Con filtro = uno mismo o supervisor
        // directo también pueden verlo.
        if ($request->colaborador_id) {
            abort_unless($this->puedeVerRutasDe($request->colaborador_id), 403);
        } else {
            $this->authorize('talento.routes.view');
        }

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
        $data = $request->validate([
            'colaborador_id' => 'required|exists:talento_colaboradores,id',
            'date'           => 'required|date',
            'work_order_ids' => 'required|array|min:1|max:8',
            'work_order_ids.*' => 'exists:talento_work_orders,id',
        ]);

        // David (28-sep): "esa ruta debería hacerla el superior o
        // superiores" — mismo patrón que TalentoWorkOrderController::store():
        // permiso general (admin/DESARROLLADOR) o supervisor DIRECTO del
        // colaborador para el que se arma la ruta.
        if (! auth()->user()->can('talento.routes.manage')) {
            $esSuSupervisor = Actor::for(auth()->user())->talento()
                ?->subordinados()
                ->where('id', $data['colaborador_id'])
                ->exists();

            abort_unless($esSuSupervisor, 403, 'No tienes permiso para crear rutas para este colaborador.');
        }

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
        $route = TalentoRoute::with([
            'colaborador.user',
            'stops.workOrder',
            'deviations',
        ])->findOrFail($id);

        abort_unless($this->puedeVerRutasDe($route->colaborador_id), 403);

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
