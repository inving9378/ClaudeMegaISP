<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Support\PayWeek;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TalentoLiquidacionController extends Controller
{
    public function __construct(private LiquidationService $service) {}

    public function index()
    {
        $this->authorize('talento.liquidation.view');
        return view('addon-talento::talento.liquidaciones');
    }

    public function data(Request $request)
    {
        $this->authorize('talento.liquidation.view');

        $q = TalentoLiquidation::with('colaborador.user')
            ->when($request->colaborador_id, fn($q, $v) => $q->where('colaborador_id', $v))
            ->when($request->status,         fn($q, $v) => $q->where('status', $v))
            ->when($request->from,           fn($q, $v) => $q->where('period_start', '>=', $v))
            ->when($request->to,             fn($q, $v) => $q->where('period_end', '<=', $v))
            ->orderBy('period_start', 'desc')
            ->paginate($request->per_page ?? 25);

        return response()->json($q);
    }

    public function calcular(Request $request)
    {
        $this->authorize('talento.liquidation.manage');

        $data = $request->validate([
            'colaborador_id' => 'required|exists:talento_colaboradores,id',
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after_or_equal:period_start',
        ]);

        // Guard anti-recálculo (2.2): no re-liquidar semanas ya cerradas/pagadas con el método
        // viejo. La línea es el inicio de la semana de transición (PayWeek::transitionStart(),
        // = cutover − 7d). Se compara contra el period_start CANÓNICO que realmente se liquidaría.
        $canonical = PayWeek::boundsFor(Carbon::parse($data['period_start'])->copy()->addDay());
        $minPeriod = PayWeek::transitionStart()->toDateString();
        if ($canonical['period_start'] < $minPeriod) {
            return response()->json([
                'error' => "No se puede liquidar un período anterior al {$minPeriod}: corresponde a semanas ya cerradas con el método anterior.",
            ], 422);
        }

        try {
            $liq = $this->service->calculate(
                $data['colaborador_id'],
                Carbon::parse($data['period_start']),
                Carbon::parse($data['period_end'])
            );
            return response()->json($liq->load('colaborador.user'));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show($id)
    {
        $this->authorize('talento.liquidation.view');

        $liq = TalentoLiquidation::with('colaborador.user')->findOrFail($id);

        // Attach ledger entries and validated orders for the period
        $liq->ledger = TalentoLedgerEntry::where('colaborador_id', $liq->colaborador_id)
            ->where('period_start', $liq->period_start->toDateString())
            ->where('period_end',   $liq->period_end->toDateString())
            ->orderBy('created_at')
            ->get();

        $liq->orders = TalentoWorkOrder::with('type')
            ->where('colaborador_id', $liq->colaborador_id)
            ->validatedBillable()
            ->whereBetween('validated_at', [
                $liq->period_start->startOfDay(),
                $liq->period_end->copy()->endOfDay(),
            ])
            ->get(['id','type_id','points','is_billable','validated_at','notes']);

        return response()->json($liq);
    }

    public function cerrar($id)
    {
        $this->authorize('talento.liquidation.manage');

        $liq = TalentoLiquidation::findOrFail($id);

        try {
            $closed = $this->service->close($liq);
            return response()->json($closed);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Endpoint para la app: avance semanal del colaborador.
     * Permite que el colaborador vea su propio progreso en la semana en curso.
     */
    public function avance($colaboradorId)
    {
        $this->authorize('talento.liquidation.view');

        TalentoColaborador::findOrFail($colaboradorId); // valida existencia

        // Semana en curso desde PayWeek + desglose reusable (mismo cálculo que el portal técnico).
        $w = PayWeek::current();
        $b = $this->service->breakdown($colaboradorId, $w);

        // Pending orders this week (específico del avance admin)
        $pendingCount = TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        return response()->json([
            'colaborador_id'  => $colaboradorId,
            'week_start'      => $b['period_start'],
            'week_end'        => $b['period_end'],
            'units_this_week' => $b['units'],
            'quota'           => $b['quota'],
            'pending_orders'  => $pendingCount,
            'base_salary'     => $b['base_salary'],
            'value_per_unit'  => $b['value_per_unit'],
            'projected_pay'   => $b['projected_pay'],
        ]);
    }
}
