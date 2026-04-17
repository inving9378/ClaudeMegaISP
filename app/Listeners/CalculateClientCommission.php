<?php

namespace App\Listeners;

use App\Events\ClientRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use App\Models\Commission;
use App\Models\CommissionDetail;
use App\Models\Seller;
use App\Models\CommissionRule;

class CalculateClientCommission
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
    public function handle(ClientRegistered $event): void
    {
      /*   DB::transaction(function () use ($event) {
            $client = $event->client;
            $crmId = $event->crmId;

            $seller = Seller::where('user_id', $client->seller_id)->first();

            if (!$seller) {
                return;
            }

            $rule = CommissionRule::select('commissions_rules.*')
                ->join('commissions_rules_sellers', 'commissions_rules_sellers.commission_rule_id', '=', 'commissions_rules.id')
                ->where('commissions_rules_sellers.seller_id', $seller->id)
                ->first();

            if ($rule && $rule->amount !== null && $rule->amount > 0) {
                $sale_value = $rule->amount / 6; // Valor de la venta según la regla de comisión

                $prospectCommissionDetail = DB::table('commissions_details')
                    ->where('prospect_id', $crmId)
                    ->where('type', 'Prospecto')
                    ->first();

                if ($prospectCommissionDetail) {
                    $prospectCommissionId = $prospectCommissionDetail->commission_id;
                    $prospectValue = DB::table('commissions')
                        ->where('id', $prospectCommissionId)
                        ->value('account_balance'); // Valor original del prospecto

                    DB::table('commissions_details')
                        ->where('prospect_id', $crmId)
                        ->update([
                            'type' => 'Venta',
                            'prospect_id' => null,
                            'client_id' => $client->id,
                            'bonus' => 0
                        ]);

                    DB::table('commissions')
                        ->where('id', $prospectCommissionId)
                        ->update([
                            'account_balance' => $sale_value,
                            'number_sales' => 1,
                            'number_prospects' => 0,
                            'start_date' => now(),
                            'end_date' => now(),
                            'status' => 'Por pagar'
                        ]);

                    $seller->balance -= $prospectValue; // Retirar el valor del prospecto del balance
                } else {
                    $commission = Commission::create([
                        'seller_id' => $seller->id,
                        'iva' => $rule->iva,
                        'account_balance' => $sale_value,
                        'monthly_bonus' => 0,
                        'status' => 'Por pagar',
                        'start_date' => now(),
                        'end_date' => now(),
                        'period' => $rule->period,
                        'number_sales' => 1,
                        'number_prospects' => 0,
                        'zone' => $rule->zone
                    ]);

                    CommissionDetail::create([
                        'commission_id' => $commission->id,
                        'type' => 'Venta',
                        'bonus' => 0,
                        'client_id' => $client->id
                    ]);
                }

                $seller->balance += $sale_value; // Añadir el valor de la venta al balance
                $seller->save();
            }
        }); */
    }
}
