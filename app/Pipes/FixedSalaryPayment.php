<?php

namespace App\Pipes;

use App\Models\PaymentByRuleDetails;
use Closure;

class FixedSalaryPayment
{
    public function handle($data, Closure $next)
    {
        $seller = $data['user']->seller;
        $rule = $data['rule'];
        $general = $data['general'];
        if ($seller->hasPaymentByRuleInPeriod($data, 'fixed_salary') && !$general) {
            return $next($data);
        }
        $data = $this->getDefault($data, $rule);
        if ($data['fixed_salary_in_range']) {
            $payment = 0;
            $salary = $rule['fixed_salary'];
            $is_fixed = $rule['is_fixed_salary'];
            $minimum_sales = $rule['minimum_sales'];
            $minimum_prospects = $rule['number_of_prospects'];
            $current_sales = count($data['sales']);
            $current_prospects = $data['prospects'];
            $data = $this->setValue($data, 'Ventas realizadas', $minimum_sales < $current_sales ? $current_sales : $minimum_sales);
            $data = $this->setValue($data, 'Prospectos realizados', $minimum_prospects < $current_prospects ? $current_prospects : $minimum_prospects);
            $data = $this->setValue($data, 'Ventas requeridas', $minimum_sales);
            $data = $this->setValue($data, 'Prospectos requeridos', $minimum_prospects);
            if ($salary > 0) {
                if ($is_fixed) {
                    $payment += $salary;
                } else {
                    if (($minimum_sales > 0 && $minimum_prospects > 0 && ($current_sales * 20 + $current_prospects >= 120)) ||
                        ($current_sales >= $minimum_sales && $minimum_prospects == 0) ||
                        ($current_prospects >= $minimum_prospects && $minimum_sales == 0) ||
                        ($minimum_prospects == 0 && $minimum_sales == 0)
                    ) {
                        $payment += $salary;
                    } else {
                        $payment += ($current_sales * 300 + $current_prospects * 15);
                    }
                }
            }
            $data['amount'] += $payment;
            $data = $this->setValue($data, 'Total a pagar', $payment);

            if ($general) {
                $data['applied_rules']['fixed_salary']['Pagado'] = $seller->hasPaymentByRuleInPeriod($data, 'fixed_salary') ? 'Si' : 'No';
            }
        }
        return $next($data);
    }

    public function getDefault($data, $rule)
    {
        $default = [
            'Ventas requeridas' => 0,
            'Ventas realizadas' => 0,
            'Prospectos requeridos' => 0,
            'Prospectos realizados' => 0,
            'Total a pagar' => 0,
        ];
        $data['applied_rules']['fixed_salary'] = $default;
        return $data;
    }

    public function setValue($data, $prop, $val)
    {
        $data['applied_rules']['fixed_salary'][$prop] += $val;
        return $data;
    }
}
