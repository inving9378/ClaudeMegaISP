<?php

namespace App\Pipes;

use Closure;
use Illuminate\Support\Arr;

class AdditionalSalesCommissionPayment
{
    public function handle($data, Closure $next)
    {
        $seller = $data['user']->seller;
        // if ($seller->hasPaymentByRuleInPeriod($data, 'additional_sales_commissions')) {
        //     return $next($data);
        // }
        $rule = $data['rule'];
        $sales = $data['sales'];
        $minimum_sales = $rule['minimum_sales'];
        //if (!isset($data['applied_rules']['additional_sales_commissions'])) {
        $data = $this->getDefault($data);
        //}
        $payment = 0;
        $commision = $rule['additional_sales_commissions'];
        if (isset($commision) && $minimum_sales > 0) {
            $current_sales = count($sales);
            $additional_sales = $current_sales - $minimum_sales;
            $data = $this->setValue($data, 'Ventas adicionales', $additional_sales > 0 ? $additional_sales : 0);
            $data = $this->setValue($data, 'Ventas de la casa', $additional_sales > 0  ? $minimum_sales : $current_sales);
            if ($additional_sales > 0) {
                $totalSales = $sales;
                $sales = $totalSales->slice($minimum_sales);
                $sales_selected = collect($data['sales_selected']);
                foreach ($sales as $s) {
                    if ($s->hasBeenDiscount()) {
                        $data = $this->setAdditionalSale($data, $s, ($s['service'] * $commision['bonus']) / 100, 'descontada');
                    } else {
                        if ($seller->hasNotBeenPaid($s)) {
                            if (count($sales_selected) > 0) {
                                $exists = $sales_selected->where('id', $s->client_id)->first();
                                if ($exists) {
                                    if ($commision['type'] == '$') {
                                        $payment += $commision['bonus'];
                                        $data = $this->setAdditionalSale($data, $s, $commision['bonus'], 'pendiente');
                                    } else {
                                        $payment += ($s['service'] * $commision['bonus']) / 100;
                                        $data = $this->setAdditionalSale($data, $s, ($s['service'] * $commision['bonus']) / 100, 'pendiente');
                                    }
                                    $data['applied_rules']['additional_sales_commissions']['sales_amount'][] = $exists;
                                }
                            } else {
                                if ($commision['type'] == '$') {
                                    $payment += $commision['bonus'];
                                    $data = $this->setAdditionalSale($data, $s, $commision['bonus'], 'pendiente');
                                } else {
                                    $payment += ($s['service'] * $commision['bonus']) / 100;
                                    $data = $this->setAdditionalSale($data, $s, ($s['service'] * $commision['bonus']) / 100, 'pendiente');
                                }
                            }
                        } else {
                            if ($commision['type'] == '$') {
                                $data = $this->setAdditionalSale($data, $s, $commision['bonus'], 'pagada');
                            } else {
                                $data = $this->setAdditionalSale($data, $s, ($s['service'] * $commision['bonus']) / 100, 'pagada');
                            }
                        }
                    }
                }
                $sales = $totalSales->take($minimum_sales);
                foreach ($sales as $s) {
                    $data['applied_rules']['additional_sales_commissions']['LVC'][] = ($s->client_id . ' - ' . $s->client_name_with_fathers_names);
                }
            } else {
                foreach ($sales as $s) {
                    $data['applied_rules']['additional_sales_commissions']['LVC'][] = ($s->client_id . ' - ' . $s->client_name_with_fathers_names);
                }
            }
            $data = $this->setValue($data, 'Pago por venta adicional', $payment);
            if ($payment > 0 && $rule['iva'] > 0 && $commision['iva']) {
                $discount = ($payment * $rule['iva']) / 100;
                $payment -= $discount;
                $data = $this->setValue($data, 'IVA', $discount);
            }
            $data['amount'] += $payment;
            $data = $this->setValue($data, 'Total a pagar', $payment);
        }
        return $next($data);
    }

    public function getDefault($data)
    {
        $default = [
            'Ventas adicionales' => 0,
            'LVA' => [],
            'Ventas de la casa' => 0,
            'LVC' => [],
            'Pago por venta adicional' => 0,
            'IVA' => 0,
            'Total a pagar' => 0,
            'sales_amount' => [],
        ];
        $data['applied_rules']['additional_sales_commissions'] = $default;
        return $data;
    }

    public function setValue($data, $prop, $val)
    {
        $data['applied_rules']['additional_sales_commissions'][$prop] += $val;
        return $data;
    }

    public function setAdditionalSale($data, $sale, $amount, $state)
    {
        $data['applied_rules']['additional_sales_commissions']['LVA'][] = [
            'id' => $sale->client_id,
            'client' => $sale->client_name_with_fathers_names,
            'label' => sprintf('%d - %s - ($%d)', $sale->client_id, $sale->client_name_with_fathers_names, $amount),
            'selected' => false,
            'amount' => $amount,
            'state' => $state
        ];
        return $data;
    }
}
