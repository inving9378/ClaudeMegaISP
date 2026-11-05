<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\DurationContract;
use App\Models\PaymentByRuleDetails;
use App\Models\Seller;
use App\Services\CalculateBalanceSellerService;
use Carbon\Carbon;

/**
 * Resumen del estado de cuenta de un vendedor (UNI-T I1, #893). Es el cálculo que antes vivía
 * dentro de TalentoComisionSellerController::statementAccount, sin cambios; ahora lo comparten
 * esa pantalla y «Mi progreso», para que ambas muestren la misma cifra.
 */
class SellerStatementService
{
    public function resumen(Seller $seller): array
    {
        $user = $seller->user;
        $service = new CalculateBalanceSellerService();
        $contracts = DurationContract::all();
        $fromDate = $seller->created_at;
        $itemsOfPeriod = $service->getPeriodFromType('week', $fromDate);
        $appliedCommisions = ['fixed_salary', 'sales_commission', 'additional_sales_commissions', 'distributors_commission'];

        $currentBalance = 0;
        foreach ($itemsOfPeriod as $p) {
            foreach ($appliedCommisions as $commission) {
                $details = $service->getSalaryFromRange('week', $user, $p['from'], $p['to'], null, $contracts, $commission);
                if ($details['salary'] > 0) {
                    $currentBalance += $details['salary'];
                }
            }
        }

        $years = range(2024, Carbon::now()->year);
        foreach ($years as $y) {
            $itemsOfPeriod = $service->getPeriodFromType('month', $fromDate, $y);
            foreach ($itemsOfPeriod as $p) {
                $from = $p['from'];
                $to = Carbon::createFromFormat('Y-m-d', $p['to']);
                if (Carbon::createFromFormat('Y-m-d', $from)->gte(Carbon::createFromFormat('Y-m-d', '2024-06-01')) && $to->lte(Carbon::now())) {
                    $details = $service->getMonthlyBonusFromRange($user, $from, $p['to']);
                    if ($details['salary'] > 0) {
                        $currentBalance += $details['salary'];
                    }
                }
            }
        }

        $expenses = PaymentByRuleDetails::whereHas('payment', fn($q) => $q->where('seller_id', $seller->id))->sum('amount');
        $debt = $seller->getTotalDebtBySales();
        $discountBySales = $seller->getTotalDiscountBySales();

        return [
            'income'          => number_format($currentBalance + $expenses, 2, '.', ''),
            'expenses'        => number_format($expenses + $discountBySales, 2, '.', ''),
            'debt'            => number_format($debt, 2, '.', ''),
            'current_balance' => number_format($currentBalance - $discountBySales, 2, '.', ''),
            'discount'        => $discountBySales,
        ];
    }
}
