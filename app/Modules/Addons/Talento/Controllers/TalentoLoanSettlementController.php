<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use App\Modules\Addons\Talento\Models\TalentoSettlement;
use App\Modules\Addons\Talento\Models\TalentoSettlementItem;
use App\Modules\Addons\Talento\Services\LoanService;
use App\Modules\Addons\Talento\Services\SettlementService;
use Illuminate\Http\Request;

class TalentoLoanSettlementController extends Controller
{
    public function __construct(
        private LoanService       $loanSvc,
        private SettlementService $settlementSvc
    ) {}

    // ── Web view ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.finiquito');
    }

    // ── Loans ──────────────────────────────────────────────────────────────

    public function loansIndex(Request $request)
    {
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
        $loan     = TalentoLoan::findOrFail($id);
        $resolved = $this->loanSvc->authorize($loan, auth()->id());
        return response()->json($resolved->load('colaborador.user'));
    }

    public function loansForColaborador(int $colaboradorId)
    {
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
        $date       = $request->settlement_date ?? now()->toDateString();
        $settlement = $this->settlementSvc->draft($colaboradorId, $date);
        return response()->json($settlement->load('items'));
    }

    public function showSettlement(int $id)
    {
        return response()->json(
            TalentoSettlement::with(['colaborador.user', 'items'])->findOrFail($id)
        );
    }

    public function updateSettlementItem(Request $request, int $itemId)
    {
        $item = TalentoSettlementItem::with('settlement')->findOrFail($itemId);
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
        $closed     = $this->settlementSvc->close($settlement);
        return response()->json($closed->load('items'));
    }

    public function settlementsIndex(Request $request)
    {
        $q = TalentoSettlement::with('colaborador.user')
            ->orderByDesc('settlement_date');
        if ($request->filled('status')) $q->where('status', $request->status);
        return response()->json($q->paginate(25));
    }
}
