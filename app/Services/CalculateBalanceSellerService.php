<?php

namespace App\Services;

use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Modules\Core\CRM\Models\Crm;
use App\Models\DurationContract;
use App\Models\HistorySellerRule;
use App\Models\Payment;
use App\Pipes\AdditionalSalesCommissionPayment;
use App\Pipes\DiscountPayment;
use App\Pipes\DistributorsCommissionPayment;
use App\Pipes\FixedSalaryPayment;
use App\Pipes\MonthlyBonusPayment;
use App\Pipes\SalesCommissionPayment;
use Carbon\Carbon;
use Illuminate\Pipeline\Pipeline;

class CalculateBalanceSellerService
{

    public $pipes = [
        FixedSalaryPayment::class,
        SalesCommissionPayment::class,
        AdditionalSalesCommissionPayment::class,
        DistributorsCommissionPayment::class,
        DiscountPayment::class
    ];

    public $pipesKey = [
        'fixed_salary' => FixedSalaryPayment::class,
        'sales_commission' => SalesCommissionPayment::class,
        'additional_sales_commissions' => AdditionalSalesCommissionPayment::class,
        'distributors_commission' => DistributorsCommissionPayment::class
    ];

    const MONTH_PERIOD = 'month';
    const WEEK_PERIOD = 'week';

    /**
     * Memoización POR INSTANCIA (no persiste entre requests — cada controller
     * hace `new CalculateBalanceSellerService()`, así que no hay riesgo de
     * fuga entre peticiones/usuarios). getRule()/getSales() se llamaban antes
     * una vez por cada semana×tipo de comisión del histórico completo del
     * vendedor (cientos de queries idénticas salvo por el rango de fechas) —
     * aquí se trae el dato completo UNA vez por vendedor y se filtra/busca en
     * memoria para cada rango, sin cambiar ningún cálculo ni el resultado de
     * ninguna consulta individual.
     */
    private array $ruleHistoryCache = [];
    private array $salesHistoryCache = [];

    public function getSalary($period, $user, $from, $to, $general = false)
    {
        $contracts = $this->getDurationsContracts();
        $items_of_period = $this->getPeriodFromType($period, $user->seller->created_at, $from, $to);
        // if (isset($from) && isset($to)) {
        //     $start = Carbon::createFromFormat('Y-m-d', '2023-01-10');
        //     $end = Carbon::createFromFormat('Y-m-d', '2023-01-20');
        //     $dif = $start->diffInDays($end);
        //     if ($dif === 6) {
        //         return $this->getSalaryFromRange($period, $user, $from, $to, null, $contracts);
        //     }
        // }
        $results = [
            'prospects' => 0,
            'sales' => 0,
            'salary' => 0,
            'applied_rules' => [],
            'rule' => [],
        ];
        $rules = [];
        $payments = [];

        $items_of_period = array_filter($items_of_period, function ($period) use ($from, $to) {
            $start = Carbon::parse($period['from']);
            $end = Carbon::parse($period['to']);
            return $start->gte(Carbon::parse($from)) && $end->lte(Carbon::parse($to));
        });

        $index = 0;

        foreach ($items_of_period as $p) {
            $from = $p['from'];
            $to = $p['to'];
            $salary = $this->getSalaryFromRange($period, $user, $from, $to, $results['applied_rules'], $contracts, null, [], $general);
            $results['prospects'] += $salary['prospects'];
            $results['sales'] += $salary['sales'];
            $results['salary'] += $salary['salary'];
            $results['applied_rules'] = $salary['applied_rules'];
            if (!in_array($salary['rule_id'], $results['rule'])) {
                $results['rule'][] = $salary['rule_id'];
                $rules[] = $salary['rule'];
            }
            $index++;
            $payments[] = [
                'index' => $index,
                'period' => Carbon::parse($from)->format('d/m/Y') . ' - ' . Carbon::parse($to)->format('d/m/Y'),
                'from' => $from,
                'to' => $to,
                'prospects' => $salary['prospects'],
                'sales' => $salary['sales'],
                'salary' => $salary['salary'],
                'fixed_salary' => $results['applied_rules']['fixed_salary'] ?? null,
                'sales_commission' => $results['applied_rules']['sales_commission'] ?? null,
                'additional_sales_commissions' => $results['applied_rules']['additional_sales_commissions'] ?? null,
                'distributors_commission' => $results['applied_rules']['distributors_commission'] ?? null
            ];
        }
        //$results['rule'] = count($rules) > 1 ? $rules : $rules[0];
        $results['payments'] = $payments;
        return $results;
    }

    public function getSalaryFromRange($period, $user, $from, $to, $history = null, $contracts, $pipeKey = null, $sales_selected = [], $general = false)
    {
        $year = Carbon::now()->year;
        $prospects = 0;
        $sales = [];
        $applied_rules = [];
        $rule_id = [];
        $amount = 0;
        $rule = $this->getRule($user, $from);
        if ($rule && Carbon::createFromFormat('Y-m-d', $to)->gte($rule->created_at)) {
            $prospects = $this->getProspects($user, $from, $to);
            $sales = $this->getSales($user, $from, $to);
            $rule_id = $rule->id;
            $applied_rules = $this->getAppliedRules($rule->data);
            $data = [
                'general' => $general,
                'amount' => 0,
                'prospects' => $prospects,
                'sales_count' => count($sales),
                'rule' => $rule->data,
                'sales' => $sales,
                'from' => $from,
                'to' => $to,
                'user' => $user,
                'contracts' => $contracts,
                'sales_selected' => $sales_selected,
                'applied_rules' => isset($history) ? $history : $applied_rules,
                'fixed_salary_in_range' => ($period == 'week' && ($year > 2024 || ($year == 2024 && $from->weekOfYear() > 23)))
                    || ($period == 'month' && ($year > 2024 || ($year == 2024 && $from->monthOfYear() >= 6)))
            ];
            $salary = app(Pipeline::class)
                ->send($data)
                ->through($pipeKey ? $this->pipesKey[$pipeKey] : $this->pipes)
                ->thenReturn();
            $amount = $salary['amount'];
            $applied_rules = $salary['applied_rules'];
        }

        return [
            'prospects' => $prospects,
            'sales' => count($sales),
            'salary' => round($amount, 2),
            'applied_rules' => $applied_rules,
            'rule' => $rule->data ?? null,
            'from' => $from,
            'to' => $to,
            'rule_id' => $rule_id
        ];
    }

    public function getMonthlyBonus($user, $year)
    {
        $years = [];
        if (isset($year)) {
            $years[] = $year;
        } else {
            $year = Carbon::now()->year;
            for ($i = 2024; $i <= $year; $i++) {
                $years[] = $i;
            }
        }
        $results = [
            'prospects' => 0,
            'sales' => 0,
            'total' => 0,

            'rule' => []
        ];
        $results = $this->getDefaultMonthyValues($results);
        $rules = [];
        foreach ($years as $y) {
            $items_of_period = $this->getPeriodFromType('month', $user->seller->created_at, $y);
            foreach ($items_of_period as $p) {
                $from = $p['from'];
                $to = Carbon::createFromFormat('Y-m-d', $p['to']);
                if (Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01')) && $to->lte(Carbon::now())) {
                    $to = $p['to'];
                    $month = $p['month'];
                    $salary = $this->getMonthlyBonusFromRange($user, $from, $to);
                    $results['prospects'] += $salary['prospects'];
                    $results['sales'] += $salary['sales'];
                    $results[$month]['Ventas realizadas'] += $salary[$month]['Ventas realizadas'];
                    $results[$month]['Regla aplicada'] = $salary[$month]['Regla aplicada'];
                    $results[$month]['Comisión por ventas'] += $salary[$month]['Comisión por ventas'];
                    $results[$month]['IVA'] += $salary[$month]['IVA'];
                    $results[$month]['Total a pagar'] += $salary[$month]['Total a pagar'];
                    $results[$month]['rules'] = $salary[$month]['rules'];
                    $results['total'] += $salary[$month]['Total a pagar'];
                    if (!in_array($salary['rule_id'], $results['rule'])) {
                        $results['rule'][] = $salary['rule_id'];
                        $rules[] = $salary['rule'];
                    }
                }
            }
        }
        $results['rule'] = count($rules) > 1 ? $rules : $rules[0];
        return $results;
    }

    public function getMonthlyBonusFromRange($user, $from, $to)
    {
        $prospects = 0;
        $sales = [];
        $rule_id = null;
        $rule = $this->getRule($user, $from);
        $month = Carbon::createFromFormat('Y-m-d', $from)->format('F');
        $amount = null;
        if ($rule && Carbon::createFromFormat('Y-m-d', $to)->gte($rule->created_at)) {
            $prospects = $this->getProspects($user, $from, $to);
            $sales = $this->getSales($user, $from, $to);
            $rule_id = $rule->id;
            $rule = $rule->data;
            $data = [
                'prospects' => $prospects,
                'sales_count' => count($sales),
                'rule' => $rule,
                'sales' => $sales,
                'month' => $month,
                'user' => $user,
                'from' => $from,
                'to' => $to,
                'rule_id' => $rule_id,
            ];
            $data = $this->getDefaultMonthyValues($data);
            $salary = app(Pipeline::class)
                ->send($data)
                ->through(MonthlyBonusPayment::class)
                ->thenReturn();
            $amount = $salary[$month];
        } else {
            $amount = [
                'Ventas realizadas' => 0,
                'Comisión por ventas' => 0,
                'Regla aplicada' => '...',
                'IVA' => 0,
                'Total a pagar' => 0,
                'rules' => []
            ];
        }

        return [
            'prospects' => $prospects,
            'sales' => count($sales),
            'rule_id' => $rule_id,
            'rule' => $rule,
            'from' => $from,
            'to' => $to,
            'salary' => $amount['Total a pagar'],
            $month => $amount,
        ];
    }

    public function getDefaultMonthyValues($data, $type = 'monthly_bonus')
    {
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        foreach ($months as $m) {
            $data[$m] = $type == 'monthly_bonus' ? [
                'Ventas realizadas' => 0,
                'Comisión por ventas' => 0,
                'Regla aplicada' => '...',
                'IVA' => 0,
                'Total a pagar' => 0,
                'rules' => [],
            ] : [
                'Ventas realizadas' => 0,
                'Ventas con pago inicial' => 0,
                'Comisión inicial por venta' => 0,
                'Ventas mínimas' => 0,
                'Comisión adicional' => 0,
                'Regla(s) aplicada(s)' => [],
                'Comisión total' => 0,
                'IVA' => 0,
                'Total a pagar' => 0
            ];
        }
        return $data;
    }

    public function getAppliedRules($rule)
    {
        $applied_rules = [];
        foreach ($rule['selected_fields'] as $f) {
            $applied_rules[$f] = null;
        }
        return $applied_rules;
    }

    public function getProspects($user, $start, $end)
    {
        return Crm::with('crm_lead_information')
            ->whereHas('crm_lead_information', function ($query) use ($user, $start, $end) {
                $query->where('owner_id', $user->id)
                    ->whereDate('created_at', '>=', $start)
                    ->whereDate('created_at', '<=', $end);
            })->count();
    }

    public function getSales($user, $from, $to)
    {
        if (!isset($this->salesHistoryCache[$user->id])) {
            // Trae TODO el historial de ventas del vendedor una sola vez (misma
            // condición whereHas('client')+seller_id+orden que el original, sin
            // el rango de fechas) — cada llamada de aquí en adelante filtra este
            // mismo conjunto en memoria en vez de repetir la consulta.
            $this->salesHistoryCache[$user->id] = ClientMainInformation::whereHas('client')
                ->where('seller_id', $user->id)
                ->orderByRaw("STR_TO_DATE(client_main_information.activation_date, '%Y-%m-%d') ASC, client_id ASC")
                ->get();
        }

        $sales = $this->salesHistoryCache[$user->id]->filter(function ($cmi) use ($from, $to) {
            if (!$cmi->activation_date) {
                return false;
            }
            $activation = substr($cmi->activation_date, 0, 10);
            return $activation >= substr($from, 0, 10) && $activation <= substr($to, 0, 10);
        })->values();

        return $sales->where('service', '>', 0);
    }

    public function getDurationsContracts()
    {
        return DurationContract::all();
    }

    public function getRule($user, $from)
    {
        $sellerId = $user->seller->id;

        if (!isset($this->ruleHistoryCache[$sellerId])) {
            // Trae TODAS las reglas históricas del vendedor una sola vez, ordenadas
            // ascendente — de aquí en adelante cada llamada busca en memoria en vez
            // de repetir la consulta por cada semana/mes del histórico.
            $this->ruleHistoryCache[$sellerId] = HistorySellerRule::where('seller_id', $sellerId)
                ->orderBy('created_at', 'ASC')
                ->get();
        }

        $rules = $this->ruleHistoryCache[$sellerId];
        if ($rules->isEmpty()) {
            return null;
        }

        $fromDate = Carbon::parse($from);
        // Misma semántica que el original: la regla más reciente cuyo created_at
        // sea <= $from (equivalente a ->where('created_at','<=',$from)->latest()->first()).
        $rule = $rules->filter(fn($r) => $r->created_at->lte($fromDate))->last();

        // Mismo fallback que el original: si ninguna regla es anterior a $from,
        // usar la más antigua registrada.
        return $rule ?? $rules->first();
    }

    public function getPeriodFromType($period, $from, $year = null)
    {
        if ($period === 'month') {
            return $this->getValidMonths($year ?? Carbon::now()->year);
        }
        return $this->getValidWeeks($from);
    }

    public function getValidWeeks($from)
    {
        $firstValid = Carbon::createFromDate(2024, 6, 2);
        $fromDate = $from->lt($firstValid) ? $firstValid : $from->startOfWeek(Carbon::SUNDAY);
        $currenDate = Carbon::now();
        $weeks = [];
        while ($fromDate->lte($currenDate)) {
            $to = $fromDate->copy()->addDays(6);
            if ($to->lte($currenDate)) {
                $weeks[] = [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $to->format('Y-m-d')
                ];
            }
            $fromDate->addWeek();
        }
        return $weeks;
    }

    public function getValidMonths($year)
    {
        $fromDate = Carbon::create($year, 1, 1);
        $toDate = Carbon::create($year, 12, 31);
        $months = [];
        while ($fromDate->lte($toDate)) {
            $firstDate = $fromDate->copy()->startOfMonth();
            $lastDate = $fromDate->copy()->endOfMonth();
            $months[] = [
                'from' => $firstDate->format('Y-m-d'),
                'to' => $lastDate->format('Y-m-d'),
                'month' => $firstDate->format('F')
            ];
            $fromDate->addMonthNoOverflow();
        }
        return $months;
    }
}
