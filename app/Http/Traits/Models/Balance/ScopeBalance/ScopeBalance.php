<?php

namespace App\Http\Traits\Models\Balance\ScopeBalance;


trait ScopeBalance
{

    public function scopeRawAmountGreaterOrEqualThanPrice($query)
    {
        $query->whereRaw('amount >= price');
    }
}
