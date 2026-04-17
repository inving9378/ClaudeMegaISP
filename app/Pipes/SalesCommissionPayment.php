<?php

namespace App\Pipes;

use Closure;

class SalesCommissionPayment
{
    public function handle($data, Closure $next)
    {
        $seller = $data['user']->seller;
        $general = $data['general'];
        if ($seller->hasPaymentByRuleInPeriod($data, 'sales_commission') && !$general) {
            return $next($data);
        }
        $rule = $data['rule'];
        $sales_commission = $rule['sales_commission'];
        if ($sales_commission > 0) {
            if (!isset($data['applied_rules']['sales_commission'])) {
                $data = $this->getDefault($data);
            }
            $payment = 0;
            if ($rule['sales_commission_type'] == '$') {
                $payment += $sales_commission * count($data['sales']);
            } else {
                foreach ($data['sales'] as $s) {
                    $payment += ($s['service'] * $sales_commission) / 100;
                }
            }
            $data = $this->setValue($data, 'Comisión total', $payment);
            if ($payment > 0 && $rule['iva'] > 0) {
                $discount = ($payment * $rule['iva']) / 100;
                $payment -= $discount;
                $data = $this->setValue($data, 'IVA', $discount);
            }
            $data['amount'] += $payment;
            $data = $this->setValue($data, 'Total a pagar', $payment);

            if ($general) {
                $data['applied_rules']['sales_commission']['Pagado'] = $seller->hasPaymentByRuleInPeriod($data, 'sales_commission') ? 'Si' : 'No';
            }
        }
        return $next($data);
    }

    public function getDefault($data)
    {
        $default = [
            'Comisión total' => 0,
            'IVA' => 0,
            'Total a pagar' => 0
        ];
        if (!isset($data['applied_rules']['sales_commission'])) {
            $data['applied_rules']['sales_commission'] = $default;
        }
        return $data;
    }

    public function setValue($data, $prop, $val)
    {
        $data['applied_rules']['sales_commission'][$prop] += $val;
        return $data;
    }
}
