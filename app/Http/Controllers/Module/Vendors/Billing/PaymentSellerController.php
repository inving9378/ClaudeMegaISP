<?php

namespace App\Http\Controllers\Module\Vendors\Billing;

use App\Http\Controllers\Controller;
use App\Http\Repository\CompanyInformationRepository;
use App\Http\Traits\PaymentsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Commission;
use App\Models\CompanyInformation;
use App\Models\Discount;
use App\Models\DiscountSale;
use App\Models\DurationContract;
use App\Models\MethodOfPayment;
use App\Models\Payment;
use App\Models\PaymentByRule;
use App\Models\PaymentByRuleDetails;
use App\Models\TransactionSeller;
use App\Models\PaymentSeller;
use App\Models\PaymentDetail;
use App\Models\Seller;
use App\Models\User;
use App\Services\CalculateBalanceSellerService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class PaymentSellerController extends Controller
{
    use PaymentsTrait;
    // get all payments
    public function getPaymentsBySellerId(Request $request, $seller_id)
    {
        $search = $request->input('search', '');

        $payments = DB::table('payments_sellers')
            ->join('method_of_payments', 'payments_sellers.method_of_payment', '=', 'method_of_payments.id')
            ->join('users', 'payments_sellers.created_by', '=', 'users.id')
            ->where('payments_sellers.seller_id', $seller_id)
            ->where(function ($query) use ($search) {
                $query->where('payments_sellers.payment_number', 'like', "%{$search}%")
                    ->orWhere('payments_sellers.amount', 'like', "%{$search}%")
                    ->orWhere('method_of_payments.type', 'like', "%{$search}%")
                    ->orWhere('payments_sellers.comment', 'like', "%{$search}%")
                    ->orWhere('payments_sellers.commission_id', 'like', "%{$search}%")
                    ->orWhere('payments_sellers.status', 'like', "%{$search}%");
            })
            ->orderBy('payments_sellers.payment_date', 'desc')
            ->select(
                'payments_sellers.*',
                'method_of_payments.type as method_of_payment',
                'users.name as created_by_name'
            )
            ->paginate(49);

        return response()->json($payments);
    }

    public function edit($payment_id)
    {
        $payment = PaymentSeller::find($payment_id);
        return response()->json($payment);
    }

    public function getDataToEditPayment($payment_id)
    {
        $payment = PaymentSeller::find($payment_id);
        $transaction = TransactionSeller::where('seller_id', $payment->seller_id)->first();
        $commission = Commission::where('id', $payment->commission_id)->get();

        return response()->json([
            'payment' => $payment,
            'transaction' => $transaction,
            'commission' => $commission
        ]);
    }


    public function getTicket($seller_id, $payment_id)
    {
        $seller = DB::table('users')
            ->join('sellers', 'sellers.user_id', '=', 'users.id')
            ->select('users.name', 'users.father_last_name', 'users.mother_last_name', 'users.address', 'users.city_municipality', 'users.state_country', 'users.code_postal')
            ->where('sellers.id', $seller_id)
            ->first();

        $payments = DB::table('payments_sellers')
            ->join('method_of_payments', 'method_of_payments.id', '=', 'payments_sellers.method_of_payment')
            ->select('payments_sellers.id', 'payments_sellers.payment_number', 'payments_sellers.payment_date', 'payments_sellers.amount', 'method_of_payments.type as method_of_payment')
            ->where('payments_sellers.seller_id', $seller_id)
            ->where('payments_sellers.id', $payment_id)
            ->get();

        $total_amount = $payments->sum('amount');

        $result = [
            "name_complete" => $seller->name . ' ' . $seller->father_last_name . ' ' . $seller->mother_last_name,
            "address" => $seller->address,
            "city_municipality" => $seller->city_municipality,
            "state_country" => $seller->state_country,
            "code_postal" => $seller->code_postal,
            "payments" => $payments->map(function ($payment) {
                return [
                    "id" => $payment->id,
                    "payment_number" => $payment->payment_number,
                    "payment_date" => $payment->payment_date,
                    "amount" => $payment->amount,
                    "method_of_payment" => $payment->method_of_payment,
                ];
            }),
            "total_amount" => $total_amount,
        ];

        return response()->json($result);
    }

    public function downloadReceipt($seller_id, $payment_id)
    {
        $seller = DB::table('users')
            ->join('sellers', 'sellers.user_id', '=', 'users.id')
            ->select('users.name', 'users.father_last_name', 'users.mother_last_name', 'users.address', 'users.city_municipality', 'users.state_country', 'users.code_postal')
            ->where('sellers.id', $seller_id)
            ->first();

        $payments = DB::table('payments_sellers')
            ->join('method_of_payments', 'method_of_payments.id', '=', 'payments_sellers.method_of_payment')
            ->select('payments_sellers.id', 'payments_sellers.payment_number', 'payments_sellers.payment_date', 'payments_sellers.amount', 'method_of_payments.type as method_of_payment')
            ->where('payments_sellers.seller_id', $seller_id)
            ->where('payments_sellers.id', $payment_id)
            ->get();

        $total_amount = number_format($payments->sum('amount'), 2);

        $result = [
            "name_complete" => $seller->name . ' ' . $seller->father_last_name . ' ' . $seller->mother_last_name,
            "address" => $seller->address,
            "city_municipality" => $seller->city_municipality,
            "state_country" => $seller->state_country,
            "code_postal" => $seller->code_postal,
            "payments" => $payments->map(function ($payment) {
                return [
                    "id" => $payment->id,
                    "payment_number" => $payment->payment_number,
                    "payment_date" => $payment->payment_date,
                    "amount" => $payment->amount,
                    "method_of_payment" => $payment->method_of_payment,
                ];
            }),
            "total_amount" => $total_amount,
        ];

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = view('meganet.module.vendors.ticket-seller-pdf', compact('result'))->render();

        $dompdf->loadHtml($html);

        $dompdf->setPaper([0, 0, 300, 650], 'portrait');

        $dompdf->render();

        return $dompdf->stream('Ticket.pdf');
    }

    // POST
    public function registerPayment(Request $request)
    {
        DB::transaction(function () use ($request) {
            $data = $request->all();
            $totalAmount = $data['amount'] ?? 0;
            $remainingAmount = $totalAmount;

            $commissions = Commission::whereIn('id', $data['commissions_id'])
                ->orderBy('account_balance', 'asc')
                ->get();

            $seller = Seller::find($data['seller_id']);
            $previousBalance = $seller->balance;

            foreach ($commissions as $commission) {
                if ($remainingAmount > 0 || $totalAmount == 0) {
                    $paymentAmount = $totalAmount == 0 ? $commission->account_balance : min($remainingAmount, $commission->account_balance);
                    $remainingAmount -= $paymentAmount;

                    $payment = new PaymentSeller;
                    $payment->payment_number = $data['payment_number'];
                    $payment->payment_date = now()->toDateString();
                    $payment->method_of_payment = $data['method_of_payment'];
                    $payment->comment = $data['comment'];
                    $payment->created_by = auth()->id();
                    $payment->status = 'Pagado';
                    $payment->seller_id = $data['seller_id'];
                    $payment->commission_id = $commission->id;
                    $payment->amount = $paymentAmount;

                    $commission->account_balance -= $paymentAmount;
                    if ($commission->account_balance == 0) {
                        $commission->status = 'Pagado';
                    } elseif ($commission->account_balance > 0 && $commission->account_balance < $paymentAmount) {
                        $commission->status = 'Pendiente';
                    }

                    $payment->save();
                    $commission->save();

                    foreach ($data['commission_details'] as $detail) {
                        if ($detail['commission_id'] == $commission->id) {
                            $paymentDetail = new PaymentDetail;
                            $paymentDetail->type = $detail['type'];
                            $paymentDetail->payment_id = $payment->id;
                            $paymentDetail->client_id = $detail['client_id'];
                            $paymentDetail->prospect_id = $detail['prospect_id'];
                            $paymentDetail->bundle_id = $detail['bundle_id'];
                            $paymentDetail->save();
                        }
                    }

                    if ($remainingAmount <= 0 && $totalAmount != 0) {
                        break;
                    }
                }
            }

            if ($remainingAmount > 0) {
                $payment = new PaymentSeller;
                $payment->payment_number = $data['payment_number'];
                $payment->payment_date = now()->toDateString();
                $payment->method_of_payment = $data['method_of_payment'];
                $payment->comment = 'Abono de comisión';
                $payment->created_by = auth()->id();
                $payment->status = 'Pagado';
                $payment->seller_id = $data['seller_id'];
                $payment->amount = $remainingAmount;
                $payment->commission_id = null;
                $payment->save();
            }

            $totalCommissionsBalance = Commission::where('seller_id', $data['seller_id'])->sum('account_balance');

            if ($remainingAmount > 0) {
                $totalCommissionsBalance -= $remainingAmount;
            }

            $seller->balance = $totalCommissionsBalance;
            $seller->save();

            $transaction = new TransactionSeller;
            $transaction->transaction_date = now()->toDateString();
            $transaction->method_of_payment = $data['method_of_payment'];
            $transaction->seller_id = $data['seller_id'];
            $transaction->previous_balance = $previousBalance;
            $transaction->new_balance = $seller->balance;
            $transaction->save();
        });

        return response()->json(['message' => 'Pago registrado con éxito']);
    }



    // PUT
    public function updatePayment(Request $request, $payment_id)
    {
        $data = $request->all();

        $payment = PaymentSeller::find($payment_id);
        $payment->payment_number = $data['payment_number'];
        $payment->payment_date = $data['payment_date'];
        $payment->method_of_payment = $data['method_of_payment'];
        $payment->comment = $data['comment'];
        $payment->save();
    }

    // DELETE
    public function deletePayment($payment_id)
    {
        DB::transaction(function () use ($payment_id) {
            $payment = PaymentSeller::find($payment_id);

            $commission = Commission::find($payment->commission_id);
            $seller = Seller::find($payment->seller_id);
            $previousBalance = $seller->balance;

            if ($commission) {
                $commission->account_balance += $payment->amount;
                if ($commission->account_balance > 0 && $commission->account_balance < $payment->amount) {
                    $commission->status = 'Pendiente';
                } else {
                    $commission->status = 'Por pagar';
                }
                $commission->save();
            }

            $seller->balance += $payment->amount;
            $seller->save();

            $transaction = new TransactionSeller;
            $transaction->transaction_date = now()->toDateString();
            $transaction->method_of_payment = $payment->method_of_payment;
            $transaction->seller_id = $payment->seller_id;
            $transaction->previous_balance = $previousBalance;
            $transaction->new_balance = $seller->balance;
            $transaction->save();

            PaymentDetail::where('payment_id', $payment_id)->delete();
            $payment->delete();
        });

        return response()->json(['message' => 'Pago eliminado correctamente']);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $payment = new PaymentByRule();
            $paymentStore = $payment->create($request->only($payment->getFillable()));
            $user = $paymentStore->seller->user;
            $service = new CalculateBalanceSellerService();
            $contracts = DurationContract::all();
            $items_of_period = [];
            $applied_commisions = $request->general_bonus;
            $applied_monthly = $request->monthly_bonus;
            $salary = 0;
            $payment_sales = $request->payment_sales;
            $from_date = $user->seller->created_at;
            if (count($applied_commisions) > 0) {
                if (isset($request->period_date)) {
                    $items_of_period[] = [
                        'from' => substr($request->period_date[0], 0, 10),
                        'to' => substr($request->period_date[1], 0, 10),
                    ];
                } else {
                    $items_of_period = $service->getPeriodFromType('week', $from_date);
                }
                foreach ($applied_commisions as $commission) {
                    foreach ($items_of_period as $p) {
                        $details = $service->getSalaryFromRange('week', $user, $p['from'], $p['to'], null, $contracts, $commission, $payment_sales);
                        if ($details['salary'] > 0) {
                            $salary += $details['salary'];
                            $this->saveDetails($paymentStore, $commission, $details);
                        }
                    }
                }
            }
            if (count($applied_monthly)) {
                $years = [];
                if (isset($request->monthly_year)) {
                    $years[] = $request->monthly_year;
                } else {
                    $year = Carbon::now()->year;
                    for ($i = 2024; $i <= $year; $i++) {
                        $years[] = $i;
                    }
                }
                foreach ($years as $y) {
                    $items_of_period = $service->getPeriodFromType('month', $from_date, $y);
                    foreach ($items_of_period as $p) {
                        $from = $p['from'];
                        $to = Carbon::createFromFormat('Y-m-d', $p['to']);
                        $month = Carbon::createFromFormat('Y-m-d', $from)->format('F');
                        if (in_array($month, $applied_monthly) && Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01')) && $to->lte(Carbon::now())) {
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
        } catch (\Exception  $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'payment' => null]);
        }
    }

    private function saveDetails($payment, $commission, $details, $month = null)
    {
        $object = new PaymentByRuleDetails();
        $object->payment_id = $payment->id;
        $object->start_date = $details['from'];
        $object->end_date  = $details['to'];
        $object->rule_id = $details['rule_id'];
        $object->type = $commission;
        $object->amount = $details['salary'];
        $object->sales = (isset($details['applied_rules']) && isset($details['applied_rules'][$commission]['sales_amount']) && count($details['applied_rules'][$commission]['sales_amount']) > 0) > 0 ? $details['applied_rules'][$commission]['sales_amount'] : null;
        try {
            $object->data = $month != null ? $details[$month] : $details['applied_rules'][$commission];
            $object->save();
        } catch (\Throwable $th) {
        }

        return true;
    }

    public function details(Request $request)
    {
        $seller = Seller::find($request->seller_id);
        $user = $seller->user;
        $service = new CalculateBalanceSellerService();
        $contracts = DurationContract::all();
        $items_of_period = [];
        $from_date = $seller->created_at;
        if (isset($request->period_date)) {
            $items_of_period[] = [
                'from' => $request->period_date[0],
                'to' => $request->period_date[1],
            ];
        } else {
            $items_of_period = $service->getPeriodFromType('week', $from_date);
        }
        $applied_commisions = $request->general_bonus;
        $applied_monthly = $request->monthly_bonus;
        $payment_sales = $request->payment_sales;
        $data = [
            'seller' => $seller,
            'applied_commisions' => $applied_commisions,
            'applied_monthly' => $applied_monthly,
            'week_commissions' => null,
            'monthly_commissions' => null,
            'company' => CompanyInformation::first(),
            'payment_method' => MethodOfPayment::find($request->payment_method_id)->type,
            'invoice_number' => $request->invoice_number,
            'payment_date' => Carbon::createFromFormat('Y-m-d', $request->payment_date)->format('d/m/Y'),
            'total' => 0
        ];
        foreach ($items_of_period as $p) {
            $from = $p['from'];
            $to = $p['to'];
            $total_of_period = 0;
            foreach ($applied_commisions as $commission) {
                $details = $service->getSalaryFromRange('week', $user, $from, $to, null, $contracts, $commission, $payment_sales);
                if ($details['salary'] > 0) {
                    $total_of_period += $details['salary'];
                    $data['total'] += $details['salary'];
                    $data['week_commissions'][$from . ' - ' . $to][$commission] = [
                        'salary' => $details['salary'],
                        'rules' => $details['applied_rules'][$commission],
                        'original_rule' => $details['rule'],
                        'sales_amount' => isset($details['applied_rules'][$commission]['sales_amount']) ? $details['applied_rules'][$commission]['sales_amount'] : []
                    ];
                }
            }
            if ($total_of_period > 0) {
                $data['week_commissions'][$from . ' - ' . $to]['salary'] = $total_of_period;
            }
        }
        $years = [];
        if (isset($request->monthly_year)) {
            $years[] = $request->monthly_year;
        } else {
            $year = Carbon::now()->year;
            for ($i = 2024; $i <= $year; $i++) {
                $years[] = $i;
            }
        }
        foreach ($years as $y) {
            $items_of_period = $service->getPeriodFromType('month', $from_date, $y);
            foreach ($items_of_period as $p) {
                $from = $p['from'];
                $to = Carbon::createFromFormat('Y-m-d', $p['to']);
                $month = Carbon::createFromFormat('Y-m-d', $from)->format('F');
                if (in_array($month, $applied_monthly) && Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01')) && $to->lte(Carbon::now())) {
                    $to = $p['to'];
                    $month = $p['month'];
                    $details = $service->getMonthlyBonusFromRange($user, $from, $to);
                    if ($details[$month]['Total a pagar'] > 0) {
                        $data['total'] += $details[$month]['Total a pagar'];
                        $data['monthly_commissions'][$y][$month] = [
                            'salary' => $details[$month]['Total a pagar'],
                            'rules' => $details[$month]
                        ];
                    }
                }
            }
        }
        return $data;
    }

    public function detailsFromPaymentType(Request $request)
    {
        $seller = Seller::find($request->seller_id);
        $user = $seller->user;
        $service = new CalculateBalanceSellerService();
        $contracts = DurationContract::all();
        $from = $request->period_date[0];
        $to = $request->period_date[1];
        $payment_sales = $request->payment_sales;
        $commission = $request->general_bonus[0];
        $data = [
            'seller' => $seller,
            'payment' => null,
            'company' => CompanyInformation::first(),
            'payment_method' => MethodOfPayment::find($request->payment_method_id)->type,
            'invoice_number' => $request->invoice_number,
            'signature' => $request->signature,
            'payment_date' => Carbon::createFromFormat('Y-m-d', $request->payment_date)->format('d/m/Y'),
            'total' => 0,
            'type' => $this->paymentLabelByCode($commission),
            'period' => Carbon::createFromFormat('Y-m-d', $from)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $to)->format('d/m/Y')
        ];
        $details = $service->getSalaryFromRange('week', $user, $from, $to, null, $contracts, $commission, $payment_sales);
        if ($details['salary'] > 0) {
            $data['total'] = $details['salary'];
        }
        $data['total'] = number_format($data['total'], 2);
        $html = View::make('meganet.module.sellers.pdf.payment_by_type', $data)->render();
        $html = preg_replace('/>\s+</', '><', $html);
        $html = trim($html);
        return $html;
    }

    public function detailsFromDiscountType(Request $request)
    {
        $company = CompanyInformation::first();
        $seller = Seller::find($request->seller_id);
        $data = [
            'discount' => null,
            'seller' => $seller,
            'company' => $company,
            'invoice_number' => $request->invoice_number,
            'discount_date' => $request->date,
            'total' => number_format($request->discount, 2),
        ];
        $html = View::make('meganet.module.sellers.pdf.discount_by_type', $data)->render();
        $html = preg_replace('/>\s+</', '><', $html);
        $html = trim($html);
        return $html;
    }

    public function paymentReceiptByTypePDF($id)
    {
        $payment = PaymentByRule::find($id);
        $user = $payment->user->getClientNameWithFathersNamesAttribute();
        $seller = $payment->seller;
        $paymentMethod = $payment->paymentMethod->type;
        $payment->loadMissing('user', 'seller', 'seller.user');
        $company = CompanyInformation::first();
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $commission = $payment->commissions()->first();
        $pdf = PDF::loadView(
            'meganet.module.sellers.pdf.payment_by_type',
            [
                'payment' => $payment,
                'user' => $user,
                'seller' => $seller,
                'company' => $company,
                'paymentMethod' => $paymentMethod,
                'invoice_number' => $payment->invoice_number,
                'signature' => $payment->signature,
                'payment_date' => $payment->payment_date,
                'total' => $payment->amount,
                'period' => $commission->period,
                'type' => $commission->type_str
            ]
        )->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'sans-serif');
        return $pdf->stream(sprintf('Recibo de pago %s.pdf', $payment->invoice_number));
    }

    public function paymentReceiptPDF($id)
    {
        $payment = PaymentByRule::find($id);
        $user = $payment->user->getClientNameWithFathersNamesAttribute();
        $seller = $payment->seller->user->getClientNameWithFathersNamesAttribute();
        $paymentMethod = $payment->paymentMethod->type;
        $payment->loadMissing('user', 'seller', 'seller.user');
        $general_commissions = PaymentByRuleDetails::where('payment_id', $id)->where('type', '<>', 'monthly_bonus')->orderBy('start_date', 'ASC')->select('start_date', 'end_date')->distinct()->get();
        $monthly_commissions = PaymentByRuleDetails::where('payment_id', $id)->where('type', 'monthly_bonus')->orderBy('start_date', 'ASC')->select('start_date', 'end_date')->distinct()->get();
        $company = CompanyInformation::first();
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $pdf = PDF::loadView('meganet.module.sellers.payment_receipt', [
            'payment' => $payment,
            'general_commissions' => $general_commissions,
            'monthly_commissions' => $monthly_commissions,
            'user' => $user,
            'seller' => $seller,
            'company' => $company,
            'paymentMethod' => $paymentMethod
        ])->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'sans-serif');
        return $pdf->stream(sprintf('Recibo de pago %s.pdf', $payment->invoice_number),);
    }

    public function discountReceiptPDF($id)
    {
        $discount = Discount::find($id);
        $seller = $discount->seller;
        $discount->loadMissing('sales', 'sales.sale');
        $company = CompanyInformation::first();
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
        return $pdf->stream(sprintf('Recibo de pago %s.pdf', $discount->invoice_number),);
    }

    public function statementAccount($id)
    {
        $user = User::find($id);
        $service = new CalculateBalanceSellerService();
        $contracts = DurationContract::all();
        $from_date = $user->seller->created_at;
        $items_of_period = $service->getPeriodFromType('week', $from_date);
        $applied_commisions = $this->getCommissions([]);
        $expenses = 0;
        $current_balance = 0;
        foreach ($items_of_period as $p) {
            $from = $p['from'];
            $to = $p['to'];
            foreach ($applied_commisions as $commission) {
                $details = $service->getSalaryFromRange('week', $user, $from, $to, null, $contracts, $commission);
                if ($details['salary'] > 0) {
                    $current_balance += $details['salary'];
                }
            }
        }
        $years = [];
        $year = Carbon::now()->year;
        for ($i = 2024; $i <= $year; $i++) {
            $years[] = $i;
        }
        foreach ($years as $y) {
            $items_of_period = $service->getPeriodFromType('month', $from_date, $y);
            foreach ($items_of_period as $p) {
                $from = $p['from'];
                $to = Carbon::createFromFormat('Y-m-d', $p['to']);
                if (Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01')) && $to->lte(Carbon::now())) {
                    $to = $p['to'];
                    $details = $service->getMonthlyBonusFromRange($user, $from, $to);
                    if ($details['salary'] > 0) {
                        $current_balance += $details['salary'];
                    }
                }
            }
        }
        $expenses = PaymentByRuleDetails::with(['payment' => function ($query) use ($user) {
            $query->where('seller_id', $user->seller->id);
        }])->get()->sum('amount');
        $debt = $user->seller->getTotalDebtBySales();
        $discountBySales = $user->seller->getTotalDiscountBySales();
        return response()->json([
            'income' => number_format($current_balance + $expenses, 2, '.'),
            'expenses' => number_format($expenses + $discountBySales, 2, '.'),
            'debt' => number_format($debt, 2, '.'),
            'current_balance' => number_format($current_balance - $discountBySales, 2, '.'),
            'discount' => $discountBySales
        ]);
    }

    public function debtAccount($id)
    {
        $user = User::find($id);
        $debt = $user->seller->getTotalDebtBySales();
        return response()->json($debt);
    }

    public function discountAccount($id)
    {
        $user = User::find($id);
        $discountBySales = $user->seller->getTotalDiscountBySales();
        return response()->json($discountBySales);
    }

    public function expensesAccount($id)
    {
        $user = User::find($id);
        $expenses = PaymentByRuleDetails::whereHas('payment', function ($query) use ($user) {
            $query->where('seller_id', $user->seller->id);
        })->with(['payment' => function ($query) use ($user) {
            $query->where('seller_id', $user->seller->id);
        }])->sum('amount');
        return response()->json((float)$expenses);
    }

    public function incomesAccount($id)
    {
        $user = User::find($id);
        $service = new CalculateBalanceSellerService();
        $contracts = DurationContract::all();
        $from_date = $user->seller->created_at;
        $items_of_period = $service->getPeriodFromType('week', $from_date);
        $applied_commisions = $this->getCommissions([]);
        $current_balance = 0;
        foreach ($items_of_period as $p) {
            $from = $p['from'];
            $to = $p['to'];
            foreach ($applied_commisions as $commission) {
                $details = $service->getSalaryFromRange('week', $user, $from, $to, null, $contracts, $commission);
                if ($details['salary'] > 0) {
                    $current_balance += $details['salary'];
                }
            }
        }
        $years = [];
        $year = Carbon::now()->year;
        for ($i = 2024; $i <= $year; $i++) {
            $years[] = $i;
        }
        foreach ($years as $y) {
            $items_of_period = $service->getPeriodFromType('month', $from_date, $y);
            foreach ($items_of_period as $p) {
                $from = $p['from'];
                $to = Carbon::createFromFormat('Y-m-d', $p['to']);
                if (Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01')) && $to->lte(Carbon::now())) {
                    $to = $p['to'];
                    $details = $service->getMonthlyBonusFromRange($user, $from, $to);
                    if ($details['salary'] > 0) {
                        $current_balance += $details['salary'];
                    }
                }
            }
        }
        return response()->json($current_balance);
    }

    public function getMonths($monthly_bonus)
    {
        return count($monthly_bonus) > 0 ? $monthly_bonus : [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December'
        ];
    }

    public function getCommissions($general_bonus)
    {
        return count($general_bonus) > 0 ? $general_bonus : [
            'fixed_salary',
            'sales_commission',
            'additional_sales_commissions',
            'distributors_commission'
        ];
    }

    public function paymentsBySeller(Request $request, $id)
    {
        $user = User::find($id);
        $seller = $user->seller;
        $payments = PaymentByRule::whereHas('commissions', function ($query) use ($request) {
            if (isset($request->paymentType)) {
                $query->where('type', $request->paymentType);
            }
            if (isset($request->period)) {
                $query->whereBetween('start_date', $request->period);
            }
        })->with(['commissions' => function ($query) {
            if (isset($request->paymentType)) {
                $query->where('type', $request->paymentType);
            }
            if (isset($request->period)) {
                $query->whereBetween('start_date', $request->period);
            }
        }])->where('seller_id', $seller->id);
        if (isset($request->search)) {
            $payments = $payments->search(['invoice_number', $request->search]);
        }
        if (isset($request->paymentDate)) {
            $payments = $payments->paymentDate($request->paymentDate);
        }
        if (isset($request->paymentMethod)) {
            $payments = $payments->paymentMethod($request->paymentMethod);
        }
        if (isset($request->createdBy)) {
            $payments = $payments->createdBy($request->createdBy);
        }
        if (isset($request->sortBy)) {
            $payments = $payments->orderBy($request->sortBy, $request->descending ? 'DESC' : 'ASC');
        } else {
            $payments = $payments->orderBy('payment_date', 'DESC')->orderBy('id', 'DESC');
        }
        $payments = $payments->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null);
        return response()->json($payments);
    }

    public function pendingPaymentsBySeller(Request $request, $id)
    {
        $seller = Seller::find($id);
        $user = $seller->user;
        $service = new CalculateBalanceSellerService();
        $contracts = DurationContract::all();
        $items_of_period = null;
        $from_date = $seller->created_at;
        if ($request->period) {
            $items_of_period[] = [
                'from' => $request->period[0],
                'to' => $request->period[1]
            ];
        } else {
            $items_of_period = $service->getPeriodFromType('week', $from_date);
        }

        $applied_commisions = [
            'fixed_salary',
            'sales_commission',
            'additional_sales_commissions',
            'distributors_commission'
        ];
        $applied_monthly = $request->monthly_bonus;
        $data = [];
        foreach ($items_of_period as $p) {
            $from = $p['from'];
            $to = $p['to'];
            foreach ($applied_commisions as $commission) {
                $details = $service->getSalaryFromRange('week', $user, $from, $to, null, $contracts, $commission);
                if ($details['salary'] > 0) {
                    $data[] = [
                        'id' => Str::uuid(),
                        'period_str' => Carbon::createFromFormat('Y-m-d', $from)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $to)->format('d/m/Y'),
                        'period_date' => [$from, $to],
                        'amount' => $details['salary'],
                        'type' => $this->paymentLabelByCode($commission),
                        'code' => $commission,
                        'sales' => $commission == 'additional_sales_commissions' ? $details['applied_rules']['additional_sales_commissions']['LVA'] : []
                    ];
                }
            }
        }
        // $years = [];
        // if (isset($request->monthly_year)) {
        //     $years[] = $request->monthly_year;
        // } else {
        //     $year = Carbon::now()->year;
        //     for ($i = 2024; $i <= $year; $i++) {
        //         $years[] = $i;
        //     }
        // }
        // foreach ($years as $y) {
        //     $items_of_period = $service->getPeriodFromType('month', $from_date, $y);
        //     foreach ($items_of_period as $p) {
        //         $from = $p['from'];
        //         $to = Carbon::createFromFormat('Y-m-d', $p['to']);
        //         $month = Carbon::createFromFormat('Y-m-d', $from)->format('F');
        //         if (in_array($month, $applied_monthly) && Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01')) && $to->lte(Carbon::now())) {
        //             $to = $p['to'];
        //             $month = $p['month'];
        //             $details = $service->getMonthlyBonusFromRange($user, $from, $to);
        //             if ($details[$month]['Total a pagar'] > 0) {
        //                 $data['total'] += $details[$month]['Total a pagar'];
        //                 $data['monthly_commissions'][$y][$month] = [
        //                     'salary' => $details[$month]['Total a pagar'],
        //                     'rules' => $details[$month]
        //                 ];
        //             }
        //         }
        //     }
        // }
        return response()->json($data);
    }

    public function discountsBySeller(Request $request, $id)
    {
        $seller = Seller::find($id);
        $discount = Discount::with('sales')->where('seller_id', $seller->id);
        if (isset($request->search)) {
            $discount = $discount->search(['invoice_number', $request->search]);
        }
        if (isset($request->discountDate)) {
            $discount = $discount->discountDate($request->discountDate);
        }
        if (isset($request->createdBy)) {
            $discount = $discount->createdBy($request->createdBy);
        }
        if (isset($request->sortBy)) {
            $discount = $discount->orderBy($request->sortBy, $request->descending ? 'DESC' : 'ASC');
        }
        $discounts = $discount->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null);
        return response()->json($discounts);
    }

    public function pendingDiscountsBySeller($id)
    {
        $user = User::find($id);
        return response()->json($user->seller->getDebtBySales());
    }

    public function collectDebt(Request $request)
    {
        DB::beginTransaction();
        try {
            $debt = new Discount();
            $store = $debt->create($request->only($debt->getFillable()));
            foreach ($request->sales as $s) {
                DiscountSale::create([
                    'discount_id' => $store->id,
                    'sale_id' => $s['id'],
                    'rule_id' => $s['rule_id'],
                    'discount' => $s['to_pay'],
                    'type' => 'sales',
                    'data' => $s
                ]);
            }
            DB::commit();
            return response()->json(['success' => true, 'discount' => $store->id]);
        } catch (\Exception  $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'discount' => null]);
        }
    }

    public function paymentSignature(Request $request, $id)
    {
        $payment = PaymentByRule::find($id);
        $payment->signature = $request->signature;
        $payment->save();
        return response()->json($payment);
    }
}
