<?php

namespace App\Http\Controllers\Module\Vendors\Billing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Models\ClientMainInformation;
use App\Models\HistorySellerRule;
use App\Models\Seller;
use App\Models\SellerType;
use App\Models\User;
use App\Services\CalculateBalanceSellerService;
use App\Services\FormatDateService;
use App\Services\PeriodStrategies\MonthlyStrategy;
use App\Services\PeriodStrategies\WeeklyStrategy;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentClientController extends Controller
{
    public function index()
    {
        return view('meganet.module.vendors.payment');
    }

    public function getListPaymentsOfCustomersBySeller(Request $request, $id)
    {
        $search = $request->input('search', '');
        $dir = $request->dir == "false" ? 'DESC' : 'ASC';
        $clientMainInformationRepository = new ClientMainInformationRepository;
        $total = $clientMainInformationRepository->getClientsBySellerId($id)->count();
        $start = $request->start;
        $limit = $request->rowsPerPage == 0 ? $total : $request->rowsPerPage;
        $order = $request->order;
        if ($limit == 0) {
            set_time_limit(0);
            ini_set('memory_limit', '8912M');
        }

        $filters = $this->getFiltersRequest($request);

        $order = $this->getOrderColumnModifiedName($order);

        $query = ClientMainInformation::select('*')
            ->whereHas('client')
            ->where('seller_id', $id)
            ->orderBy($order, $dir)->distinct();

        if ($search != '') {
            $query->where(function ($q) use ($search) {
                $columns = Schema::getColumnListing('client_main_information');
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%$search%");
                }
            });
        }

        if (!empty($filters)) {
            if (isset($filters['activation_date'])) {
                $query->whereDate('client_main_information.activation_date', '>=', $filters['activation_date']['from'])
                    ->whereDate('client_main_information.activation_date', '<=', $filters['activation_date']['to']);
            }
        } else {
            $query->whereDate('client_main_information.activation_date', '>=', '2024-06-01');
        }

        $clients = $query->get();

        $client_payments = [];
        foreach ($clients as $client) {
            $payments_in_three_first_five_months = $client->getPaymentsInThreeFirstFiveMonths();
            if ($payments_in_three_first_five_months) {
                $completed = $payments_in_three_first_five_months['completed'];
                if ($completed < 3) {
                    $full_name = $client->name . ' ' . $client->father_last_name . ' ' . $client->mother_last_name;
                    $client_payments[] = [
                        'id' => $client->client_id,
                        'full_name' => $full_name,
                        'date_activation' => Carbon::createFromFormat('Y-m-d', substr($client->activation_date, 0, 10))->format('d/m/Y'),
                        'completed_payment' => $completed,
                        'pending_payment' => 3 - $completed,
                        'package_price' => $payments_in_three_first_five_months['service'],
                        'payments' => $payments_in_three_first_five_months['payments'],
                        'state' => $payments_in_three_first_five_months['state'],
                        'client_state' => $client->estado
                    ];
                }
            }
        }
        $total = count($client_payments);

        // Aplicar la paginación al arreglo de client_payments
        $client_payments_paginated = array_slice($client_payments, $start, $limit);

        return response()->json([
            'client_payments' => $client_payments_paginated,
            'total' => $total
        ]);
    }

    public function getFiltersRequest($request)
    {
        $filters = [];
        if (isset($request['filtersCustomers']['activation_date'])) {

            $from = $request['filtersCustomers']['activation_date'][0];
            $to = $request['filtersCustomers']['activation_date'][1];
            if (empty($to)) {
                $to = Carbon::now()->format("Y-m-d");
            }
            $filters['activation_date'] = [
                'from' => $from,
                'to' => $to
            ];
        }
        return $filters;
    }

    public function getPeriodsFromSeller($id)
    {
        $latestUpdates = DB::table('history_sellers_rules')->where('seller_id', $id)
            ->select(
                'rule_id',
                DB::raw('MAX(created_at) as max_created_at')
            )
            ->groupBy(
                'rule_id',
                DB::raw('DATE(created_at)')
            );

        $rules = HistorySellerRule::joinSub($latestUpdates, 'latest', function ($join) {
            $join->on('history_sellers_rules.rule_id', '=', 'latest.rule_id')
                ->on('history_sellers_rules.created_at', '=', 'latest.max_created_at');
        })->where('seller_id', $id)
            ->orderBy('history_sellers_rules.created_at')
            ->select('history_sellers_rules.*')
            ->get();

        $rulesArray = $rules->values()->all();

        foreach ($rulesArray as $index => $rule) {
            $startDate = Carbon::parse($rule->created_at);
            $periodStart = $startDate->copy()->next(Carbon::SUNDAY);
            $periodEnd = null;
            if (isset($rulesArray[$index + 1])) {
                $nextRuleStartDate = Carbon::parse($rulesArray[$index + 1]->created_at);
                $nextPeriodStart = $nextRuleStartDate->copy()->next(Carbon::SUNDAY);
                $periodEnd = $nextPeriodStart->copy()->subDay();
            } else {
                $periodEnd = Carbon::now()->endOfWeek(Carbon::SATURDAY);
            }
            $rule->start = $periodStart->toDateString();
            $rule->end = $periodEnd->toDateString();
        }

        return response()->json([
            'rules' => $rules,
            'sellers_type' => SellerType::select('id as value', 'name as label')->get()
        ]);
    }

    public function getRuleDataSeller(Request $request, $id)
    {
        $user = User::find($id);
        $data = null;
        $strategy = $this->getStrategy("Semanal");
        if (isset($strategy)) {
            $data = $strategy->calculateSaldo($user, $request->from, $request->to, $request->general);
        }
        return response()->json([
            'result' => $data,
            'sellers_type' => SellerType::select('id as value', 'name as label')->get()
        ]);
    }

    public function getMontlyCommissionsBySeller(Request $request, $id)
    {
        $user = User::find($id);
        $data = null;
        $strategy = $this->getStrategy('Mensual');
        if (isset($strategy)) {
            $data = $strategy->calculateSaldo($user, $request->year);
        }
        return response()->json([
            'result' => $data,
        ]);
    }

    public function getDataSeller($id)
    {
        $vendedor = User::find($id);
        $seller = $vendedor->seller;
        $datosVendedor = [];

        if ($seller) {
            $comissionRules = $seller->commissionRules()->first() ?? null;
            $totalSaldoAnual = 0;

            if ($comissionRules) {
                $strategy = $this->getStrategy($comissionRules->period);
                $results = [];
                if ($strategy) {
                    $data = $strategy->calculateSaldo($comissionRules, $vendedor);
                    $ventasSaldo = $data['ventasSaldo'];
                    $results = $data['results'];
                    $mesesArray = array_filter($ventasSaldo, function ($key) {
                        return in_array($key, ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']);
                    }, ARRAY_FILTER_USE_KEY);

                    $semanasArray = array_diff_key($ventasSaldo, $mesesArray);

                    $totalSaldoAnualPorSemanas = $this->calculateTotalSaldo($semanasArray);
                    $totalSaldoAnualBonusMensual = $this->calculateTotalSaldo($mesesArray);
                    $totalSaldoAnual = $totalSaldoAnualPorSemanas + $totalSaldoAnualBonusMensual;

                    $datosVendedor = [
                        'saldoTotal' => $totalSaldoAnual,
                        'ventas' => $ventasSaldo,
                        'saldo' => array_column($semanasArray, 'total'),
                        'period' => $comissionRules->period,
                        'bonusMensual' => $mesesArray,
                    ];
                }

                if ($seller->type_id == ComunConstantsController::TYPE_SELLER_DISTRIBUITOR) {
                    $calculateBalanceSeller = new CalculateBalanceSellerService();
                    $ventasSaldo = $calculateBalanceSeller->calculateSaldoDistribuitor($comissionRules, $vendedor);

                    $totalSaldoAnual = 0;

                    $datosVendedor = [
                        'saldoTotal' => $totalSaldoAnual,
                        'ventas' => $ventasSaldo,
                        'saldo' => array_column($semanasArray, 'total'),
                        'period' => $comissionRules->period,
                        'bonusMensual' => $mesesArray,
                    ];
                }
                $datosVendedor['results'] = $results;
                $datosVendedor['rule'] = $comissionRules;
                $datosVendedor['sellers_type'] = SellerType::select('id as value', 'name as label')->get();
            }
        }

        return response()->json($datosVendedor);
    }

    private function getStrategy($period)
    {
        $strategies = [
            'Semanal' => new WeeklyStrategy(),
            'Mensual' => new MonthlyStrategy(),
        ];

        return $strategies[$period] ?? null;
    }

    // Definir calculateTotalSaldo dentro del controlador
    private function calculateTotalSaldo($salesData)
    {
        return array_reduce($salesData, function ($carry, $item) {
            return $carry + $item['total'];
        }, 0);
    }


    public function getOrderColumnModifiedName($order)
    {
        if ($order == 'full_name') {
            return 'name';
        } elseif ($order == 'date_activation') {
            return 'activation_date';
        } else {
            return $order;
        }
    }

    public function getPaymentsOfCustomersBySeller($id)
    {
        $clients = ClientMainInformation::whereHas('client')->where('seller_id', $id)->get();
        $client_payments = [];

        foreach ($clients as $client) {
            $activation_date = date('Y-m-d', strtotime($client->activation_date));

            $payments_count = DB::table('payments')
                ->join('transactions', 'payments.id', '=', 'transactions.payment_id')
                ->where('transactions.client_id', $client->client_id)
                ->whereDate('transactions.date', '>=', $activation_date)
                ->count();

            $clientRepository = new ClientRepository();
            $costAllService = $clientRepository->getCostAllService($client->client_id);

            $full_name = $client->name . ' ' . $client->father_last_name . ' ' . $client->mother_last_name;
            $client_payments[] = [
                'id' => $client->client_id,
                'full_name' => $full_name,
                'payment_1' => $payments_count >= 1,
                'payment_2' => $payments_count >= 2,
                'payment_3' => $payments_count >= 3,
                'payment_4' => $payments_count >= 4,
                'payment_5' => $payments_count >= 5,
                'package_price' => $costAllService ? number_format($costAllService, 2, '.', '') : null,
                'date_activation' => $client->activation_date,
            ];
        }

        return response()->json($client_payments);
    }
}
