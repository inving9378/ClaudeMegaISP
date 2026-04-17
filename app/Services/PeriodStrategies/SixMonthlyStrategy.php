<?php

namespace App\Services\PeriodStrategies;

use App\Services\CalculateBalanceSellerService;
use Carbon\Carbon;

class SixMonthlyStrategy implements PeriodStrategyInterface
{
    public function calculateSaldo($comissionRules, $vendedor): array
    {
        $calculateBalanceSeller = new CalculateBalanceSellerService();
        return $calculateBalanceSeller->obtenerDatosSegunNumeroDeMeses(
            $calculateBalanceSeller->getMesesYear(Carbon::now()->year,6),
            $vendedor,
            $comissionRules
        );
    }
}
