<?php

namespace App\Listeners;

use App\Events\ProspectRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use App\Models\Commission;
use App\Models\CommissionDetail;
use App\Models\Seller;
use App\Models\CommissionRule;

class CalculateProspectCommission
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ProspectRegistered $event): void
    {
       /*  DB::transaction(function () use ($event) {
            $prospect = $event->prospect;
            $seller = Seller::where('user_id', $prospect->owner_id)->first();

            if (!$seller) {
                return;
            }

            $existingDetail = CommissionDetail::where('prospect_id', $prospect->crm_id)
                                              ->where('type', 'Prospecto')
                                              ->first();

            if ($existingDetail) {
                return;
            }

            $rule = CommissionRule::select('commissions_rules.*')
                ->join('commissions_rules_sellers', 'commissions_rules_sellers.commission_rule_id', '=', 'commissions_rules.id')
                ->where('commissions_rules_sellers.seller_id', $seller->id)
                ->first();

            if (!$rule || $rule->amount <= 0 || $rule->number_of_prospects <= 0) {
                return;
            }

            $day = $rule->amount / 6;
            $prospects_per_day = $rule->number_of_prospects / 4;
            $prospect_value = $day / $prospects_per_day;

            $commission = new Commission([
                'seller_id' => $seller->id,
                'iva' => $rule->iva,
                'account_balance' => $prospect_value,
                'monthly_bonus' => 0,
                'status' => 'Por pagar',
                'start_date' => now(),
                'end_date' => now(),
                'period' => $rule->period,
                'number_sales' => 0,
                'number_prospects' => 1,
                'zone' => $rule->zone
            ]);
            $commission->save();

            CommissionDetail::create([
                'commission_id' => $commission->id,
                'type' => 'Prospecto',
                'bonus' => 0,
                'prospect_id' => $prospect->crm_id
            ]);

            $seller->balance += $prospect_value;
            $seller->save();
        }); */
    }
}
