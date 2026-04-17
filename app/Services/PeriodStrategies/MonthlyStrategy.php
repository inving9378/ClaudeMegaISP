<?php

namespace App\Services\PeriodStrategies;

use App\Services\CalculateBalanceSellerService;
use Carbon\Carbon;

class MonthlyStrategy implements PeriodStrategyInterface
{
    public function calculateSaldo($user, $year = null): array
    {
        $calculateBalanceSeller = new CalculateBalanceSellerService();
        return $calculateBalanceSeller->getMonthlyBonus(
            $user,
            $year
        );
    }
}
