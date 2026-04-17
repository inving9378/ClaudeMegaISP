<?php

namespace App\Http\Traits;

trait PaymentsTrait
{

    public function paymentLabelByCode($code)
    {
        $t = null;
        switch ($code) {
            case 'sales_commission':
                $t = 'Comisión por venta';
                break;
            case 'additional_sales_commissions':
                $t = 'Comisión por venta adicional';
                break;
            case 'distributors_commission':
                $t = 'Comisión distribuidores';
                break;
            default:
                $t = 'Salario fijo';
                break;
        }
        return $t;
    }
}
