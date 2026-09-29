<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use App\Modules\Addons\Talento\Models\TalentoSettlement;
use App\Modules\Addons\Talento\Models\TalentoSettlementItem;
use App\Modules\Addons\Talento\Services\LoanService;
use App\Modules\Addons\Talento\Services\SettlementService;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Core\Security\Traits\ChecksActionPermission;
use Illuminate\Http\Request;

class TalentoLoanSettlementController extends Controller
{
    use ChecksActionPermission;

    public function __construct(
        private LoanService       $loanSvc,
        private SettlementService $settlementSvc
    ) {}

    // ── Web view ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.finiquito');
    }

    /**
     * Préstamos: uno mismo puede VER su saldo (como Penalizaciones — se
     * entera, no gestiona), su supervisor directo o el permiso de STAFF.
     */
    private function puedeVerPrestamosDe($colaboradorId): bool
    {
        if (auth()->user()->can('talento.loans.view')) {
            return true;
        }
        $miPropioColaborador = Actor::for(auth()->user())->talento();
        if (! $miPropioColaborador) return false;
        return (string) $miPropioColaborador->id === (string) $colaboradorId
            || $miPropioColaborador->subordinados()->where('id', $colaboradorId)->exists();
    }

    /** Registrar/autorizar un préstamo es gestión — supervisor o staff, SIN autoservicio. */
    private function esGestorDePrestamosDe($colaboradorId): bool
    {
        if (auth()->user()->can('talento.loans.manage')) return true;
        return (bool) Actor::for(auth()->user())->talento()
            ?->subordinados()->where('id', $colaboradorId)->exists();
    }

    /**
     * Finiquito: a diferencia de Préstamos, SIN excepción de autoservicio ni
     * siquiera para ver — calcular/cerrar el propio finiquito no tiene
     * sentido para un colaborador activo (mismo criterio que "Por
     * colaborador" en Credenciales). Supervisor directo o permiso de STAFF.
     */
    private function esSuperiorDeFiniquito($colaboradorId): bool
    {
        if (auth()->user()->can('talento.settlement.view')
            || auth()->user()->can('talento.liquidation.view')) {
            return true;
        }
        return (bool) Actor::for(auth()->user())->talento()
            ?->subordinados()->where('id', $colaboradorId)->exists();
    }

    /** Mismo criterio que esSuperiorDeFiniquito(), para las acciones de escritura. */
    private function esGestorDeFiniquito($colaboradorId): bool
    {
        if (auth()->user()->can('talento.settlement.manage')
            || auth()->user()->can('talento.liquidation.manage')) {
            return true;
        }
        return (bool) Actor::for(auth()->user())->talento()
            ?->subordinados()->where('id', $colaboradorId)->exists();
    }

    // ── Loans ──────────────────────────────────────────────────────────────

    public function loansIndex(Request $request)
    {
        // Sin filtro por colaborador_id = listado global → exige el
        // permiso de STAFF de verdad. Con filtro = uno mismo, supervisor
        // directo también pueden verlo — mismo criterio que
        // TalentoRouteController::data().
        if ($request->filled('colaborador_id')) {
            abort_unless($this->puedeVerPrestamosDe($request->colaborador_id), 403);
        } else {
            $this->authorize('talento.loans.view');
        }

        $q = TalentoLoan::with(['colaborador.user', 'authorizedByUser'])
            ->orderByDesc('created_at');
        if ($request->filled('colaborador_id')) $q->where('colaborador_id', $request->colaborador_id);
        if ($request->filled('status'))          $q->where('status', $request->status);
        return response()->json($q->paginate(25));
    }

    public function storeLoan(Request $request)
    {
        $data = $request->validate([
            'colaborador_id'   => 'required|integer|exists:talento_colaboradores,id',
            'amount'           => 'required|numeric|min:1',
            'repayment_weekly' => 'nullable|numeric|min:1',
            'reason'           => 'nullable|string|max:1000',
        ]);

        abort_unless($this->esGestorDePrestamosDe($data['colaborador_id']), 403);

        $loan = TalentoLoan::create(array_merge($data, [
            'balance'    => $data['amount'],
            'authorized' => false,
            'status'     => 'active',
            'created_by' => auth()->id(),
        ]));

        return response()->json($loan->load('colaborador.user'), 201);
    }

    public function authorizeLoan(int $id)
    {
        $loan = TalentoLoan::findOrFail($id);
        abort_unless($this->esGestorDePrestamosDe($loan->colaborador_id), 403);
        $resolved = $this->loanSvc->authorize($loan, auth()->id());
        return response()->json($resolved->load('colaborador.user'));
    }

    public function loansForColaborador(int $colaboradorId)
    {
        abort_unless($this->puedeVerPrestamosDe($colaboradorId), 403);

        $loans = TalentoLoan::with('authorizedByUser')
            ->where('colaborador_id', $colaboradorId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($l) => array_merge($l->toArray(), ['weeks_remaining' => $l->weeksRemaining()]));
        return response()->json($loans);
    }

    // ── Settlements ────────────────────────────────────────────────────────

    public function draftSettlement(Request $request, int $colaboradorId)
    {
        abort_unless($this->esGestorDeFiniquito($colaboradorId), 403);
        $date       = $request->settlement_date ?? now()->toDateString();
        $settlement = $this->settlementSvc->draft($colaboradorId, $date);
        return response()->json($settlement->load('items'));
    }

    public function showSettlement(int $id)
    {
        $settlement = TalentoSettlement::with(['colaborador.user', 'items'])->findOrFail($id);
        abort_unless($this->esSuperiorDeFiniquito($settlement->colaborador_id), 403);
        return response()->json($settlement);
    }

    public function updateSettlementItem(Request $request, int $itemId)
    {
        $item = TalentoSettlementItem::with('settlement')->findOrFail($itemId);
        abort_unless($this->esGestorDeFiniquito($item->settlement->colaborador_id), 403);
        $data = $request->validate([
            'disposition'  => 'required|in:returned,damaged,missing',
            'debit_amount' => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        $settlement = $this->settlementSvc->updateItem(
            $item, $data['disposition'], $data['debit_amount'] ?? null, $data['notes'] ?? null
        );
        return response()->json($settlement->load('items'));
    }

    public function closeSettlement(int $id)
    {
        $settlement = TalentoSettlement::with(['items', 'colaborador.user'])->findOrFail($id);
        abort_unless($this->esGestorDeFiniquito($settlement->colaborador_id), 403);
        $this->verificarPermisoAccion('talento.finiquito.cerrar', 'finiquito.close');
        $closed = $this->settlementSvc->close($settlement);
        return response()->json($closed->load('items'));
    }

    public function settlementsIndex(Request $request)
    {
        // Listado global (sin colaborador_id) → siempre exige el permiso de
        // STAFF completo, no hay filtro que un supervisor pueda usar aquí.
        $this->authorize('talento.settlement.view');
        $q = TalentoSettlement::with('colaborador.user')
            ->orderByDesc('settlement_date');
        if ($request->filled('status')) $q->where('status', $request->status);
        return response()->json($q->paginate(25));
    }
}
