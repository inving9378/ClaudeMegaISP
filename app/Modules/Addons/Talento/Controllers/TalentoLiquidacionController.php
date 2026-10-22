<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Addons\Talento\Support\PayWeek;
use App\Modules\Core\Security\Traits\ChecksActionPermission;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TalentoLiquidacionController extends Controller
{
    use ChecksActionPermission;

    public function __construct(private LiquidationService $service) {}

    public function index()
    {
        abort_unless(auth()->user()->canAny(['talento.liquidation.view', 'talento.liquidation.totales']), 403);
        return view('addon-talento::talento.liquidaciones');
    }

    public function data(Request $request)
    {
        // Mismo criterio que TalentoReglaDesempenoController::evaluationsIndex()
        // (hoy mismo, reglas de desempeño): quien gestiona liquidaciones en
        // general ve lo que pida por filtro; un colaborador SIN ese permiso
        // (ej. un técnico viendo su propia ficha) solo puede ver su propia
        // fila y la de sus subordinados directos — nunca la de un extraño,
        // aunque manipule el parámetro colaborador_id a mano.
        $esStaff = auth()->user()->can('talento.liquidation.view');

        $miFicha = Actor::for(auth()->user())->talento();
        $idsVisibles = $miFicha
            ? $miFicha->subordinados()->pluck('id')->push($miFicha->id)->unique()->values()->all()
            : [];

        // Quien solo puede ver cuánto cobra cada quien (Mostrador, 2026-10-22) ve la lista de todos
        // con el total a pagar, sin desglose ni cálculo. Lo suyo (y lo de su equipo) lo ve completo,
        // como cualquier colaborador.
        $soloTotales = ! $esStaff
            && auth()->user()->can('talento.liquidation.totales')
            && ! ($request->filled('colaborador_id') && in_array((int) $request->colaborador_id, $idsVisibles, true));

        $q = TalentoLiquidation::with('colaborador.user')
            // El input "Buscar colaborador…" de la UI nunca se leía aquí — en
            // la pantalla admin se escribía y no hacía nada (dead param desde
            // siempre). Se conecta de paso (item #hoy: ocultarlo en la ficha
            // propia hizo evidente que tampoco servía donde SÍ se muestra).
            ->when($request->search, fn($q, $s) => $q->whereHas(
                'colaborador.user',
                fn($u) => $u->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")
            ))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->from,   fn($q, $v) => $q->where('period_start', '>=', $v))
            ->when($request->to,     fn($q, $v) => $q->where('period_end', '<=', $v))
            ->orderBy('period_start', 'desc');

        if ($esStaff || $soloTotales) {
            $q->when($request->colaborador_id, fn($q, $v) => $q->where('colaborador_id', $v));
        } else {
            abort_if(! $miFicha, 403);

            $q->whereIn('colaborador_id', $idsVisibles);

            if ($request->filled('colaborador_id')) {
                abort_unless(in_array((int) $request->colaborador_id, $idsVisibles, true), 403);
                $q->where('colaborador_id', $request->colaborador_id);
            }
        }

        $page = $q->paginate($request->per_page ?? 25);
        $puedeCerrar = auth()->user()->can('talento.liquidation.manage');
        $noCierra = $this->fichaQueNoPuedeCerrar();
        $page->getCollection()->each(fn ($l) => $l->puede_cerrar = $puedeCerrar
            && $l->status === 'draft'
            && (int) $l->colaborador_id !== $noCierra);

        if ($soloTotales) {
            $page->getCollection()->each(function ($l) {
                $l->setVisible(['id', 'colaborador_id', 'colaborador', 'period_start', 'period_end', 'gross_pay', 'status', 'puede_cerrar']);
                $l->colaborador?->setVisible(['id', 'user']);
                $l->colaborador?->user?->setVisible(['id', 'name', 'father_last_name', 'mother_last_name']);
            });
        }

        return response()->json(array_merge($page->toArray(), ['solo_totales' => $soloTotales]));
    }

    public function calcular(Request $request)
    {
        $this->authorize('talento.liquidation.manage');
        $this->verificarPermisoAccion('talento.liquidacion.calcular', 'liquidacion.calcular');

        $data = $request->validate([
            'colaborador_id' => 'required|exists:talento_colaboradores,id',
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after_or_equal:period_start',
        ]);

        // Guard anti-recálculo: no re-liquidar semanas ya cerradas/pagadas con el método
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
        $liq = TalentoLiquidation::with('colaborador.user')->findOrFail($id);

        if (! auth()->user()->can('talento.liquidation.view')) {
            $miPropioColaborador = Actor::for(auth()->user())->talento();
            $idsVisibles = $miPropioColaborador
                ? $miPropioColaborador->subordinados()->pluck('id')->push($miPropioColaborador->id)->unique()->all()
                : [];
            abort_unless(in_array((int) $liq->colaborador_id, $idsVisibles, true), 403);
        }

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

        $liq->puede_cerrar = $liq->status === 'draft'
            && auth()->user()->can('talento.liquidation.manage')
            && (int) $liq->colaborador_id !== $this->fichaQueNoPuedeCerrar();

        return response()->json($liq);
    }

    public function cerrar($id)
    {
        $this->authorize('talento.liquidation.manage');

        $liq = TalentoLiquidation::findOrFail($id);

        if ((int) $liq->colaborador_id === $this->fichaQueNoPuedeCerrar()) {
            return response()->json(['error' => 'Tu propia liquidación la cierra un administrador.'], 403);
        }

        try {
            $closed = $this->service->close($liq);
            return response()->json($closed);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Ficha cuya liquidación no puede cerrar quien hace la petición: la suya, salvo que sea
     * administrador (2026-10-21: un Mostrador con ficha administra Talento y calcula pagos,
     * pero la suya la cierra el admin). null = puede cerrar cualquiera.
     */
    private function fichaQueNoPuedeCerrar(): ?int
    {
        $actor = Actor::for(auth()->user());
        if ($actor->esSuperAdminTalento()) {
            return null;
        }

        return $actor->talento()?->id;
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
