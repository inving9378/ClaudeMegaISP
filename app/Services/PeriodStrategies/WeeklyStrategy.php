<?php

namespace App\Services\PeriodStrategies;

use App\Services\CalculateBalanceSellerService;
use Carbon\Carbon;

class WeeklyStrategy implements PeriodStrategyInterface
{
    public function calculateSaldo($user, $from = null, $to = null, $general = false): array
    {
        $calculateBalanceSeller = new CalculateBalanceSellerService();
        return $calculateBalanceSeller->getSalary(
            CalculateBalanceSellerService::WEEK_PERIOD,
            $user,
            $from,
            $to,
            $general
        );
    }
}
