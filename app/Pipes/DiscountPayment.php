<?php

namespace App\Pipes;

use Closure;

class DiscountPayment
{
    public function handle($data, Closure $next)
    {
        // $rule = $data['rule'];
        // if ($rule->iva > 0) {
        //     $data['amount'] -= ($data['amount'] * $rule->iva) / 100;
        // }
        return $next($data);
    }
}