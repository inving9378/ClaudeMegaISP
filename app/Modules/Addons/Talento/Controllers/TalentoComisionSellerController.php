<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaymentsTrait;
use App\Models\CompanyInformation as LegacyCompanyInformation;
use App\Models\Discount;
use App\Models\DiscountSale;
use App\Models\DurationContract;
use App\Models\MethodOfPayment;
use App\Models\PaymentByRule;
use App\Models\PaymentByRuleDetails;
use App\Models\Seller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Support\SellerResolver;
use App\Services\CalculateBalanceSellerService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fase D del plan Vendedores→Talento: UI de comisiones replicada dentro de
 * Talento, SIN motor de dinero paralelo — lee/escribe las MISMAS tablas y
 * reusa el MISMO motor de cálculo que ya usa Vendedores
 * (CalculateBalanceSellerService, PaymentByRule, PaymentByRuleDetails,
 * Discount, DiscountSale). El único cambio real respecto al original es que
 * cada escritura agrega `colaborador_id` (columna ya aditiva en esas tablas,
 * sin observer que la llene sola).
 *
 * Identidad: sellers.user_id == talento_colaboradores.user_id (SellerResolver).
 * Si el colaborador no tiene fila en `sellers`, se bloquea con 422 y mensaje
 * claro — decisión de Irving, nunca se crea un Seller al vuelo.
 */
class TalentoComisionSellerController extends Controller
{
    use PaymentsTrait;

    // ── Web view ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.comisiones');
    }

    // ── Resolución de identidad ──────────────────────────────────────────────

    private function resolveSeller(int $colaboradorId): Seller|JsonResponse
    {
        $seller = SellerResolver::forColaborador($colaboradorId);
        if (! $seller) {
            return response()->json([
                'message' => 'Este colaborador no tiene una cuenta de vendedor asociada. Actívalo como vendedor desde su ficha de usuario para poder ver/gestionar sus comisiones.',
            ], 422);
        }
        return $seller;
    }

    // ── Reglas asignadas (solo lectura) ──────────────────────────────────────

    public function rules(int $colaboradorId)
    {
        $seller = $this->resolveSeller($colaboradorId);
        if ($seller instanceof JsonResponse) return $seller;

        $rules = DB::table('commissions_rules_sellers')
            ->join('commissions_rules', 'commissions_rules.id', '=', 'commissions_rules_sellers.commission_rule_id')
            ->where('commissions_rules_sellers.seller_id', $seller->id)
            ->get([
                'commissions_rules.id',
                'commissions_rules.name',
                'commissions_rules.commission_percentage',
                'commissions_rules.fixed_salary',
                'commissions_rules.is_fixed_salary',
                'commissions_rules.period',
            ]);

        return response()->json($rules);
    }

    // ── Estado de cuenta (resumen) ────────────────────────────────────────────

    public function statementAccount(int $colaboradorId)
    {
        $seller = $this->resolveSeller($colaboradorId);
        if ($seller instanceof JsonResponse) return $seller;

        $user = $seller->user;
        $service = new CalculateBalanceSellerService();
        $contracts = DurationContract::all();
        $fromDate = $seller->created_at;
        $itemsOfPeriod = $service->getPeriodFromType('week', $fromDate);
        $appliedCommisions = ['fixed_salary', 'sales_commission', 'additional_sales_commissions', 'distributors_commission'];

        $currentBalance = 0;
        foreach ($itemsOfPeriod as $p) {
            foreach ($appliedCommisions as $commission) {
                $details = $service->getSalaryFromRange('week', $user, $p['from'], $p['to'], null, $contracts, $commission);
                if ($details['salary'] > 0) {
                    $currentBalance += $details['salary'];
                }
            }
        }

        $years = range(2024, Carbon::now()->year);
        foreach ($years as $y) {
            $itemsOfPeriod = $service->getPeriodFromType('month', $fromDate, $y);
            foreach ($itemsOfPeriod as $p) {
                $from = $p['from'];
                $to = Carbon::createFromFormat('Y-m-d', $p['to']);
                if (Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01')) && $to->lte(Carbon::now())) {
                    $details = $service->getMonthlyBonusFromRange($user, $from, $p['to']);
                    if ($details['salary'] > 0) {
                        $currentBalance += $details['salary'];
                    }
                }
            }
        }

        $expenses = PaymentByRuleDetails::whereHas('payment', fn($q) => $q->where('seller_id', $seller->id))->sum('amount');
        $debt = $seller->getTotalDebtBySales();
        $discountBySales = $seller->getTotalDiscountBySales();

        return response()->json([
            'income'          => number_format($currentBalance + $expenses, 2, '.', ''),
            'expenses'        => number_format($expenses + $discountBySales, 2, '.', ''),
            'debt'            => number_format($debt, 2, '.', ''),
            'current_balance' => number_format($currentBalance - $discountBySales, 2, '.', ''),
            'discount'        => $discountBySales,
        ]);
    }

    // ── Comisiones pendientes de cobrar (por periodo) ────────────────────────

    public function pendingPayments(Request $request, int $colaboradorId)
    {
        $seller = $this->resolveSeller($colaboradorId);
        if ($seller instanceof JsonResponse) return $seller;

        $user = $seller->user;
        $service = new CalculateBalanceSellerService();
        $contracts = DurationContract::all();
        $fromDate = $seller->created_at;

        if ($request->period) {
            $itemsOfPeriod = [['from' => $request->period[0], 'to' => $request->period[1]]];
        } else {
            $itemsOfPeriod = $service->getPeriodFromType('week', $fromDate);
        }

        $appliedCommisions = ['fixed_salary', 'sales_commission', 'additional_sales_commissions', 'distributors_commission'];
        $data = [];
        foreach ($itemsOfPeriod as $p) {
            $from = $p['from'];
            $to = $p['to'];
            foreach ($appliedCommisions as $commission) {
                $details = $service->getSalaryFromRange('week', $user, $from, $to, null, $contracts, $commission);
                if ($details['salary'] > 0) {
                    $data[] = [
                        'id'          => (string) \Illuminate\Support\Str::uuid(),
                        'period_str'  => Carbon::createFromFormat('Y-m-d', $from)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $to)->format('d/m/Y'),
                        'period_date' => [$from, $to],
                        'amount'      => $details['salary'],
                        'type'        => $this->paymentLabelByCode($commission),
                        'code'        => $commission,
                    ];
                }
            }
        }

        return response()->json($data);
    }

    // ── Historial de pagos ────────────────────────────────────────────────────

    public function payments(Request $request, int $colaboradorId)
    {
        $seller = $this->resolveSeller($colaboradorId);
        if ($seller instanceof JsonResponse) return $seller;

        $query = PaymentByRule::where('seller_id', $seller->id);
        if ($request->filled('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('period')) {
            $query->whereBetween('payment_date', $request->period);
        }
        $query->orderBy('payment_date', 'DESC')->orderBy('id', 'DESC');

        $payments = $query->paginate($request->integer('per_page', 20));
        return response()->json($payments);
    }

    // ── Registrar pago (reusa CalculateBalanceSellerService) ─────────────────

    public function registerPayment(Request $request, int $colaboradorId)
    {
        $seller = $this->resolveSeller($colaboradorId);
        if ($seller instanceof JsonResponse) return $seller;

        $col = TalentoColaborador::findOrFail($colaboradorId);

        DB::beginTransaction();
        try {
            $payment = new PaymentByRule();
            $data = $request->only($payment->getFillable());
            $data['seller_id'] = $seller->id;
            $paymentStore = $payment->create($data);
            $paymentStore->colaborador_id = $col->id; // aditivo, sin observer que lo llene solo
            $paymentStore->save();

            $user = $seller->user;
            $service = new CalculateBalanceSellerService();
            $contracts = DurationContract::all();
            $itemsOfPeriod = [];
            $appliedCommisions = $request->general_bonus ?? [];
            $appliedMonthly = $request->monthly_bonus ?? [];
            $salary = 0;
            $paymentSales = $request->payment_sales ?? [];
            $fromDate = $seller->created_at;

            if (count($appliedCommisions) > 0) {
                if ($request->filled('period_date')) {
                    $itemsOfPeriod[] = [
                        'from' => substr($request->period_date[0], 0, 10),
                        'to'   => substr($request->period_date[1], 0, 10),
                    ];
                } else {
                    $itemsOfPeriod = $service->getPeriodFromType('week', $fromDate);
                }
                foreach ($appliedCommisions as $commission) {
                    foreach ($itemsOfPeriod as $p) {
                        $details = $service->getSalaryFromRange('week', $user, $p['from'], $p['to'], null, $contracts, $commission, $paymentSales);
                        if ($details['salary'] > 0) {
                            $salary += $details['salary'];
                            $this->saveDetails($paymentStore, $commission, $details);
                        }
                    }
                }
            }

            if (count($appliedMonthly) > 0) {
                $years = $request->filled('monthly_year') ? [$request->monthly_year] : range(2024, Carbon::now()->year);
                foreach ($years as $y) {
                    $itemsOfPeriod = $service->getPeriodFromType('month', $fromDate, $y);
                    foreach ($itemsOfPeriod as $p) {
                        $from = $p['from'];
                        $to = Carbon::createFromFormat('Y-m-d', $p['to']);
                        $month = Carbon::createFromFormat('Y-m-d', $from)->format('F');
                        if (
                            in_array($month, $appliedMonthly)
                            && Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01'))
                            && $to->lte(Carbon::now())
                        ) {
                            $details = $service->getMonthlyBonusFromRange($user, $from, $p['to']);
                            if ($details['salary'] > 0) {
                                $salary += $details['salary'];
                                $this->saveDetails($paymentStore, 'monthly_bonus', $details, $month);
                            }
                        }
                    }
                }
            }

            $paymentStore->amount = $salary;
            $paymentStore->save();
            DB::commit();
            return response()->json(['success' => true, 'payment' => $paymentStore]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function saveDetails($payment, $commission, $details, $month = null)
    {
        $object = new PaymentByRuleDetails();
        $object->payment_id = $payment->id;
        $object->start_date = $details['from'];
        $object->end_date = $details['to'];
        $object->rule_id = $details['rule_id'];
        $object->type = $commission;
        $object->amount = $details['salary'];
        $object->sales = (isset($details['applied_rules'][$commission]['sales_amount']) && count($details['applied_rules'][$commission]['sales_amount']) > 0)
            ? $details['applied_rules'][$commission]['sales_amount']
            : null;
        try {
            $object->data = $month !== null ? $details[$month] : $details['applied_rules'][$commission];
            $object->save();
        } catch (\Throwable $th) {
            // Mismo comportamiento tolerante del original: un detalle que no
            // pudo armar su `data` no debe tumbar el registro del pago.
        }
        return true;
    }

    // ── Recibo PDF de un pago ─────────────────────────────────────────────────

    public function paymentReceiptPdf(int $paymentId)
    {
        $payment = PaymentByRule::findOrFail($paymentId);
        $user = $payment->user->getClientNameWithFathersNamesAttribute();
        $seller = $payment->seller->user->getClientNameWithFathersNamesAttribute();
        $paymentMethod = $payment->paymentMethod->type;
        $payment->loadMissing('user', 'seller', 'seller.user');
        $generalCommissions = PaymentByRuleDetails::where('payment_id', $paymentId)->where('type', '<>', 'monthly_bonus')->orderBy('start_date', 'ASC')->select('start_date', 'end_date')->distinct()->get();
        $monthlyCommissions = PaymentByRuleDetails::where('payment_id', $paymentId)->where('type', 'monthly_bonus')->orderBy('start_date', 'ASC')->select('start_date', 'end_date')->distinct()->get();
        $company = LegacyCompanyInformation::first();
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $pdf = PDF::loadView('meganet.module.sellers.payment_receipt', [
            'payment' => $payment,
            'general_commissions' => $generalCommissions,
            'monthly_commissions' => $monthlyCommissions,
            'user' => $user,
            'seller' => $seller,
            'company' => $company,
            'paymentMethod' => $paymentMethod,
        ])->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'sans-serif');
        return $pdf->stream(sprintf('Recibo de pago %s.pdf', $payment->invoice_number));
    }

    // ── Deudas / descuentos ────────────────────────────────────────────────────

    public function discounts(Request $request, int $colaboradorId)
    {
        $seller = $this->resolveSeller($colaboradorId);
        if ($seller instanceof JsonResponse) return $seller;

        $query = Discount::with('sales')->where('seller_id', $seller->id);
        if ($request->filled('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('period')) {
            $query->whereBetween('date', $request->period);
        }
        $discounts = $query->orderByDesc('id')->paginate($request->integer('per_page', 20));
        return response()->json($discounts);
    }

    public function pendingDebt(int $colaboradorId)
    {
        $seller = $this->resolveSeller($colaboradorId);
        if ($seller instanceof JsonResponse) return $seller;

        return response()->json($seller->getDebtBySales());
    }

    public function collectDebt(Request $request, int $colaboradorId)
    {
        $seller = $this->resolveSeller($colaboradorId);
        if ($seller instanceof JsonResponse) return $seller;

        $col = TalentoColaborador::findOrFail($colaboradorId);

        DB::beginTransaction();
        try {
            $debt = new Discount();
            $data = $request->only($debt->getFillable());
            $data['seller_id'] = $seller->id;
            $store = $debt->create($data);
            $store->colaborador_id = $col->id;
            $store->save();

            foreach ($request->sales ?? [] as $s) {
                DiscountSale::create([
                    'discount_id' => $store->id,
                    'sale_id'     => $s['id'],
                    'rule_id'     => $s['rule_id'],
                    'discount'    => $s['to_pay'],
                    'type'        => 'sales',
                    'data'        => $s,
                ]);
            }
            DB::commit();
            return response()->json(['success' => true, 'discount' => $store->id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function discountReceiptPdf(int $discountId)
    {
        $discount = Discount::findOrFail($discountId);
        $seller = $discount->seller;
        $discount->loadMissing('sales', 'sales.sale');
        $company = LegacyCompanyInformation::first();
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $pdf = PDF::loadView('meganet.module.sellers.pdf.discount_by_type', [
            'discount' => $discount,
            'seller' => $seller,
            'company' => $company,
            'invoice_number' => $discount->invoice_number,
            'discount_date' => $discount->date,
            'total' => $discount->discount,
        ])->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'sans-serif');
        return $pdf->stream(sprintf('Recibo de descuento %s.pdf', $discount->invoice_number));
    }

    // ── Catálogo de métodos de pago (para los formularios) ────────────────────

    public function paymentMethods()
    {
        return response()->json(MethodOfPayment::all(['id', 'type']));
    }
}
