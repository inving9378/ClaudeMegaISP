<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Services\LiquidationStateService;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Addons\Talento\Support\PayWeek;
use App\Modules\Core\Configuracion\Models\CompanyInformation;
use App\Modules\Core\Security\Traits\ChecksActionPermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PDF;

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

        if ($soloTotales) {
            return response()->json($this->totalesDeLaSemana($request));
        }

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

        if ($esStaff) {
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
        // foreach y no each(fn): each() se detiene cuando la función devuelve false, y la primera fila
        // que no se podía cerrar dejaba a las siguientes sin la bandera (la pantalla les mostraba Cerrar).
        foreach ($page->getCollection() as $l) {
            $l->puede_cerrar = $puedeCerrar
                && $l->status === 'draft'
                && (int) $l->colaborador_id !== $noCierra;
        }

        return response()->json(array_merge($page->toArray(), [
            'solo_totales'      => false,
            'puede_comprobante' => $this->puedeImprimirComprobante(),
        ]));
    }

    /**
     * Lo que ve quien solo puede ver cuánto cobra cada quien (Mostrador, 2026-10-23): de una
     * semana (por omisión la que toca pagar), cada colaborador activo, y cualquiera con liquidación
     * esa semana, con su monto a pagar. Una liquidación cerrada muestra su monto; si no, se calcula
     * en ese momento como lo haría «Calcular», sin guardar nada. Sin desglose.
     */
    private function totalesDeLaSemana(Request $request): array
    {
        $actual  = PayWeek::current();
        $minimo  = PayWeek::transitionStart()->toDateString();
        $semanas = collect(range(0, 8))
            ->map(fn ($i) => PayWeek::boundsFor(Carbon::parse($actual['period_start'])->subWeeks($i)->addDay()))
            ->filter(fn ($w) => $w['period_start'] >= $minimo)
            ->values();
        $semana = $semanas->firstWhere('period_start', $request->semana)
            ?? $semanas->firstWhere('period_start', PayWeek::lastCompleted()['period_start'])
            ?? $semanas->first();
        $enCurso = $semana['period_start'] === $actual['period_start'];

        $cerradas = TalentoLiquidation::where('period_start', $semana['period_start'])
            ->where('period_end', $semana['period_end'])
            ->get()
            ->keyBy('colaborador_id');

        $colaboradores = TalentoColaborador::with('user')
            ->where(fn ($q) => $q->activos()->orWhereIn('id', $cerradas->keys()))
            ->when($request->search, fn ($q, $s) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%$s%")
                ->orWhere('father_last_name', 'like', "%$s%")
                ->orWhere('mother_last_name', 'like', "%$s%")))
            ->get()
            ->sortBy(fn ($c) => mb_strtolower(trim(($c->user->name ?? '') . ' ' . ($c->user->father_last_name ?? ''))))
            ->values();

        $porPagina = 25;
        $pagina    = max(1, (int) $request->page);
        $filas = $colaboradores->forPage($pagina, $porPagina)->map(function (TalentoColaborador $c) use ($cerradas, $semana, $enCurso) {
            $liq = $cerradas->get($c->id);
            if (! $liq || $liq->status !== 'closed') {
                $liq = $this->service->simular($c->id, Carbon::parse($semana['period_start']), Carbon::parse($semana['period_end']));
                $liq->status = $enCurso ? 'en_curso' : 'por_cerrar';
            }
            $liq->setRelation('colaborador', $c);
            $liq->puede_cerrar = false;
            // Sin regla de pago el cálculo no tiene sueldo base: el monto puede salir en cero sin que
            // eso quiera decir que no se le debe nada.
            $liq->sin_regla = ! TalentoCompensationRuleHistory::where('colaborador_id', $c->id)
                ->where('assigned_at', '<=', Carbon::parse($semana['period_end']))
                ->exists();
            $liq->setVisible(['id', 'colaborador_id', 'colaborador', 'period_start', 'period_end', 'gross_pay', 'status', 'puede_cerrar', 'sin_regla']);
            $c->setVisible(['id', 'user']);
            $c->user?->setVisible(['id', 'name', 'father_last_name', 'mother_last_name']);

            return $liq;
        })->values();

        return [
            'data'         => $filas,
            'current_page' => $pagina,
            'last_page'    => max(1, (int) ceil($colaboradores->count() / $porPagina)),
            'per_page'     => $porPagina,
            'total'        => $colaboradores->count(),
            'solo_totales' => true,
            'puede_comprobante' => $this->puedeImprimirComprobante(),
            'semana'       => ['period_start' => $semana['period_start'], 'period_end' => $semana['period_end'], 'en_curso' => $enCurso],
            'semanas'      => $semanas->map(fn ($w) => [
                'period_start' => $w['period_start'],
                'period_end'   => $w['period_end'],
                'en_curso'     => $w['period_start'] === $actual['period_start'],
            ]),
        ];
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

        $liq->ajustes = $this->service->ajustesPendientes($liq);

        $liq->puede_cerrar = $liq->status === 'draft'
            && auth()->user()->can('talento.liquidation.manage')
            && (int) $liq->colaborador_id !== $this->fichaQueNoPuedeCerrar();

        return response()->json($liq);
    }

    /**
     * Comprobante de pago de una persona en una semana (2026-10-28, pedido de Irving: que el
     * Mostrador sepa cuánto pagarle a cada quien y le imprima su ticket de pago). Ticket de 80 mm,
     * como el de caja, con el mismo monto de la lista (lo cerrado o lo que pagaría «Calcular»), de qué
     * se compone y la firma de quien recibe. No guarda nada.
     */
    public function comprobante(Request $request)
    {
        abort_unless($this->puedeImprimirComprobante(), 403);
        $data = $request->validate([
            'colaborador_id' => 'required|integer',
            'semana'         => 'required|date_format:Y-m-d',
        ]);

        $semana = PayWeek::boundsFor(Carbon::parse($data['semana'])->addDay());
        $actual = PayWeek::current();
        abort_unless(
            $semana['period_start'] === $data['semana']
                && $semana['period_start'] >= PayWeek::transitionStart()->toDateString()
                && $semana['period_start'] <= $actual['period_start'],
            404,
            'Esa semana de pago no existe.'
        );

        // Las mismas personas que la lista: activas, o con liquidación esa semana.
        $colaborador = TalentoColaborador::with('user')->findOrFail($data['colaborador_id']);
        $conLiquidacion = TalentoLiquidation::where('colaborador_id', $colaborador->id)
            ->where('period_start', $semana['period_start'])
            ->where('period_end', $semana['period_end'])
            ->exists();
        abort_unless($conLiquidacion || TalentoColaborador::activos()->whereKey($colaborador->id)->exists(), 404);
        abort_unless(
            TalentoCompensationRuleHistory::where('colaborador_id', $colaborador->id)
                ->where('assigned_at', '<=', Carbon::parse($semana['period_end']))
                ->exists(),
            422,
            'Esta persona no tiene sueldo dado de alta: no hay nada que pagarle todavía.'
        );

        $pago = $this->service->pagoDeLaSemana($colaborador->id, $semana);
        $faltas = TalentoAttendance::where('colaborador_id', $colaborador->id)
            ->where('day_type', 'absent')
            ->forPeriod($semana['start_instant'], $semana['end_instant'])
            ->count();
        $user = $colaborador->user;
        $nombre = trim(implode(' ', array_filter([$user?->name, $user?->father_last_name, $user?->mother_last_name])));
        $fin = Carbon::parse($semana['period_end']);
        $folio = sprintf('PAGO-%06d-%s', $colaborador->id, $fin->format('Ymd'));

        $datos = [
            'folio'    => $folio,
            'company'  => CompanyInformation::with(['state', 'colony', 'municipality'])->first(),
            'nombre'   => $nombre ?: "Colaborador #{$colaborador->id}",
            'puesto'   => $colaborador->puestoNombre(),
            'desde'    => Carbon::parse($semana['period_start'])->format('d/m/Y'),
            'hasta'    => $fin->format('d/m/Y'),
            'enCurso'  => $semana['period_start'] === $actual['period_start'],
            'pago'     => $pago,
            'faltas'   => $faltas,
            'enLetra'  => $this->montoEnLetra($pago['a_pagar']),
            'entrego'  => trim(auth()->user()->name . ' ' . auth()->user()->father_last_name),
            'impreso'  => now()->format('d/m/Y H:i'),
        ];

        // Papel de 80 mm (impresora de caja), como el ticket de mostrador; el alto crece con las líneas
        // que aplican (faltas, bonos, descuentos, préstamo y fondo).
        $lineas = count(array_filter([$faltas > 0, $pago['bonos'] > 0, $pago['descuentos'] > 0, $pago['prestamo_y_fondo'] > 0]));
        $pdf = PDF::loadView('addon-talento::talento.comprobante-pago', $datos)
            ->setPaper([0, 0, 226.77, 315 + 12 * $lineas])
            ->setOption('isFontSubsettingEnabled', true);
        $archivo = 'comprobante-pago-' . Str::slug($datos['nombre']) . '-' . $fin->format('Y-m-d') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $archivo . '"',
        ]);
    }

    /**
     * El comprobante abierto desde la app (2026-10-30): el enlace lo firma
     * TalentoMobileEquipoController::liquidacionComprobanteUrl con quién lo entrega; aquí se vuelven
     * a revisar sus permisos como si lo pidiera desde la web.
     */
    public function comprobanteFirmado(Request $request)
    {
        $entrega = \App\Models\User::findOrFail((int) $request->query('entrega'));
        auth()->setUser($entrega);

        return $this->comprobante($request);
    }

    /** Quien ve la lista de pagos (Mostrador o administración) puede imprimir el comprobante. */
    private function puedeImprimirComprobante(): bool
    {
        return auth()->user()->canAny(['talento.liquidation.view', 'talento.liquidation.totales']);
    }

    /** 1850.5 → «MIL OCHOCIENTOS CINCUENTA PESOS 50/100 M.N.» */
    private function montoEnLetra(float $monto): string
    {
        $monto = max(0, round($monto, 2));
        $pesos = (int) floor($monto);
        $centavos = (int) round(($monto - $pesos) * 100);
        $letra = (new \NumberFormatter('es', \NumberFormatter::SPELLOUT))->format($pesos);
        // «veintiuno pesos» → «veintiún pesos», «uno pesos» → «un peso»
        $letra = preg_replace(['/veintiuno$/u', '/uno$/u'], ['veintiún', 'un'], $letra);

        return mb_strtoupper($letra) . ($pesos === 1 ? ' PESO ' : ' PESOS ') . sprintf('%02d', $centavos) . '/100 M.N.';
    }

    public function cerrar(\Illuminate\Http\Request $request, $id)
    {
        $this->authorize('talento.liquidation.manage');

        $liq = TalentoLiquidation::findOrFail($id);

        if ((int) $liq->colaborador_id === $this->fichaQueNoPuedeCerrar()) {
            return response()->json(['error' => 'Tu propia liquidación la cierra un administrador.'], 403);
        }

        try {
            // J3: con el interruptor encendido, primero se previsualizan las diferencias y hay que confirmar.
            if (config('unificacion.ajustes_semana_cerrada.activo', false) && !$request->boolean('confirmar')) {
                return response()->json(['requiere_confirmacion' => true] + $this->service->previewClose($liq));
            }
            // J4: con el interruptor encendido el cierre pasa por el servicio de estados (deja evento).
            $closed = LiquidationStateService::activo()
                ? app(LiquidationStateService::class)->cerrar($liq, auth()->id())
                : $this->service->close($liq);
            return response()->json($closed);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** UNI-T J4 F2a: confirma el pago (quién/cuándo/referencia). Una pagada no se reescribe (422). */
    public function pagar(Request $request, $id)
    {
        abort_unless(LiquidationStateService::activo(), 404);
        $this->authorize('talento.liquidation.pagar');
        $data = $request->validate(['referencia' => 'nullable|string|max:191']);

        $liq = TalentoLiquidation::findOrFail($id);
        if ((int) $liq->colaborador_id === $this->fichaQueNoPuedeCerrar()) {
            return response()->json(['error' => 'Tu propia liquidación la paga un administrador.'], 403);
        }

        try {
            $paid = app(LiquidationStateService::class)->pagar($liq, auth()->id(), $data['referencia'] ?? null);
            return response()->json($paid);
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
