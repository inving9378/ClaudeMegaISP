<?php

namespace App\Services\PeriodStrategies;

interface PeriodStrategyInterface
{
    public function calculateSaldo($comissionRules, $vendedor): array;
}
