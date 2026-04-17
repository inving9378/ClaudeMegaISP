<?php

namespace App\Pipes;

use App\Models\Payment;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class DistributorsCommissionPayment
{
    public function handle($data, Closure $next)
    {
        $seller = $data['user']->seller;
        $general = $data['general'];
        if ($seller->hasPaymentByRuleInPeriod($data, 'distributors_commission') && !$general) {
            return $next($data);
        }
        $rule = $data['rule'];
        $commission = $rule['distributors_commission'];
        if (isset($commission) && count($data['sales']) >= $commission['sales']) {
            if (!isset($data['applied_rules']['distributors_commission'])) {
                $data = $this->getDefault($data);
            }
            $data = $this->setValue($data, 'Ventas mínimas', $commission['sales']);
            $data = $this->setValue($data, 'Ventas realizadas', count($data['sales']));
            $payment = 0;
            $sales = $data['sales'];
            $bonus = collect($commission['bonus'])->sortByDesc('commission')->groupBy('contract');
            $applied = [];
            foreach ($bonus as $k => $v) {
                $applied[] = $v[0];
            }
            $rules = [];
            $sales_amount = [];
            foreach ($applied as $r) {
                $filtered = $sales->where('duration_contract_id', $r['contract']);
                if (count($filtered) > 0) {
                    $rules[] = [
                        'name' => $r['commission'] . '% si contrato de ' . $data['contracts']->firstWhere('id', $r['contract'])->name,
                        'sales' => count($filtered)
                    ];
                    foreach ($filtered as $s) {
                        $config = $this->getConfigDistributorsCommissions($s, $commission['initial'], $r['commission'], $commission['iva'] ? $rule['iva'] : 0);
                        $s->setDistributionCommission($config);
                        if ($this->hasFirstPaymentInRange($data, $s->client_id)) {
                            $sales_amount[] = [
                                'client' => $s->client_id,
                                'initial' => true,
                                'amount' => $commission['initial']
                            ];
                            $data = $this->setValue($data, 'Ventas por concepto de pago inicial', 1);
                            $data = $this->setValue($data, 'Comisión por concepto de pago inicial', $commission['initial']);
                            $payment += $commission['initial'];
                        }
                    }
                }
            }
            $othersPayments = $this->getSecuentialPaymentsInRange($data);
            foreach ($othersPayments as $p) {
                if (!$p->client_main_information->endPaymentByDistributtorsCommission()) {
                    $dc = $p->client_main_information->distribution_commission;
                    $data = $this->setValue($data, 'Ventas por concepto de pago secuencial', 1);
                    $data = $this->setValue($data, 'Comisión por concepto de pago secuencial', $dc->total_amount_per_week);
                    $data = $this->setValue($data, 'IVA por concepto de pago secuencial', $dc->discount_per_week);
                    $data = $this->setValue($data, 'Total a pagar por concepto de pago secuencial', $dc->amount_per_week);
                    $payment += $dc->amount_per_week;
                    $sales_amount[] = [
                        'client' => $p->paymentable_id,
                        'initial' => false,
                        'amount' => $dc->amount_per_week
                    ];
                }
            }
            $data['applied_rules']['distributors_commission']['Regla(s) aplicada(s)'] = $rules;
            $data['amount'] += $payment;
            $data['applied_rules']['distributors_commission']['sales_amount'] = $sales_amount;
            $data = $this->setValue($data, 'Total a pagar', $payment);

            if ($general) {
                $data['applied_rules']['distributors_commission']['Pagado'] = $seller->hasPaymentByRuleInPeriod($data, 'fixed_salary') ? 'Si' : 'No';
            }
        }
        return $next($data);
    }

    public function getDefault($data)
    {
        $default = [
            'Ventas mínimas' => 0,
            'Ventas realizadas' => 0,
            'Ventas por concepto de pago inicial' => 0,
            'Comisión por concepto de pago inicial' => 0,
            'Ventas por concepto de pago secuencial' => 0,
            'Comisión por concepto de pago secuencial' => 0,
            'IVA por concepto de pago secuencial' => 0,
            'Total a pagar por concepto de pago secuencial' => 0,
            'Regla(s) aplicada(s)' => [],
            'Total a pagar' => 0,
            'sales_amount' => [],
        ];
        $data['applied_rules']['distributors_commission'] = $default;
        return $data;
    }

    public function setValue($data, $prop, $val)
    {
        $data['applied_rules']['distributors_commission'][$prop] += $val;
        return $data;
    }

    public function getSecuentialPaymentsInRange($data)
    {
        $user = $data['user'];
        $from = $data['from'];
        $to = $data['to'];
        $payments = Payment::whereHas('client_main_information', function (Builder $query) use ($user) {
            $query->where('seller_id', $user->id)->whereDate('activation_date', '>=', '2024-06-01')->whereHas('distribution_commission');
        })->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->where('is_first_payment', false)->get();
        return $payments;
    }

    public function hasFirstPaymentInRange($data, $client)
    {
        $from = $data['from'];
        $to = $data['to'];
        $first = Payment::where('paymentable_id', $client)->where('is_first_payment', true)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->first();
        return $first != null;
    }

    public function getConfigDistributorsCommissions($s, $initialCommission, $percent, $iva)
    {
        $duration = $s->contract_months_number;
        $total_amount = $s->service * $percent / 100;
        $total_amount_per_week = ($total_amount - $initialCommission) / ($duration - 1);
        $total_discount = $total_amount * $iva / 100;
        $discount_per_week = $total_discount / ($duration - 1);
        return [
            'sale_id' => $s->id,
            'date' => $s->activation_date,
            'duration' => $duration,
            'initial' => $initialCommission,
            'percent' => $percent,
            'iva' => $iva,
            'service' => $s->service,
            'total_amount' => $total_amount,
            'total_amount_per_week' => $total_amount_per_week,
            'total_discount' => $total_discount,
            'discount_per_week' => $discount_per_week,
            'amount_per_week' => $total_amount_per_week - $discount_per_week,
        ];
    }
}
