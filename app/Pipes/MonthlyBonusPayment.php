<?php

namespace App\Pipes;

use Closure;
use Illuminate\Support\Arr;

class MonthlyBonusPayment
{
    public function handle($data, Closure $next)
    {
        $seller = $data['user']->seller;
        if ($seller->hasPaymentByRuleInPeriod($data, 'monthly_bonus')) {
            return $next($data);
        }
        $rule = $data['rule'];
        $commission = $rule['monthly_bonus'];
        if (isset($commission)) {
            $month = $data['month'];
            $bonus = $commission['bonus'];
            $payment = 0;
            $sales = array_column($bonus, 'sales');
            array_multisort($sales, SORT_ASC, $bonus);
            $applied_commission = Arr::last($bonus, function ($object) use ($data) {
                return count($data['sales']) >= $object['sales'];
            });
            $data = $this->setValue($data, 'Ventas realizadas', count($data['sales']));
            if (isset($applied_commission)) {
                $payment = $applied_commission['bonus'];
                $data = $this->setValue($data, 'Comisión por ventas', $payment);
                $data[$month]['Regla aplicada'] = ('$' . $applied_commission['bonus'] . ' si ' . $applied_commission['sales'] . ' venta(s)');
            }
            if ($payment > 0 && $rule['iva'] > 0 && $applied_commission['iva']) {
                $discount = ($payment * $rule['iva']) / 100;
                $payment -= $discount;
                $data = $this->setValue($data, 'IVA', $discount);
            }
            $data = $this->setValue($data, 'Total a pagar', $payment);
            if (!in_array($data['rule_id'], $data[$month]['rules'])) {
                $data[$month]['rules'][] = $data['rule_id'];
            }
        }
        return $next($data);
    }

    public function setValue($data, $prop, $val)
    {
        $data[$data['month']][$prop] += $val;
        return $data;
    }
}
