<?php

use App\Models\CommissionRule;
use App\Models\HistorySellerRule;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $diana = CommissionRule::firstWhere('name', 'Diana');
        $diana->selected_fields = ['fixed_salary', 'minimum_sales', 'installation_cost'];
        $diana->fixed_salary = 2300;
        $diana->is_fixed_salary = true;
        $diana->minimum_sales = 3;
        $diana->distributors_commission = null;
        $diana->monthly_bonus = null;
        $diana->number_of_prospects = 0;
        $diana->installation_cost = 1500;
        $diana->sales_commission_type = null;
        $diana->additional_sales_commissions = [
            'iva' => false,
            'type' => '$',
            'bonus' => 300
        ];
        $diana->created_at = Carbon::createFromFormat('Y-m-d', '2024-06-01');
        $diana->updated_at = Carbon::createFromFormat('Y-m-d', '2024-06-01');
        $diana->save();
        DB::table('history_sellers_rules')->where('rule_id', $diana->id)->delete();
        $sellers = $diana->sellers()->get();
        foreach ($sellers as $s) {
            $history = new HistorySellerRule();
            $history->seller_id = $s->id;
            $history->rule_id = $diana->id;
            $history->data = $diana;
            $history->created_at = Carbon::createFromFormat('Y-m-d', '2024-06-01');
            $history->updated_at = Carbon::createFromFormat('Y-m-d', '2024-06-01');
            $history->save();
        }
        $diana->minimum_sales = 2;
        $diana->updated_at = Carbon::createFromFormat('Y-m-d', '2025-01-01');
        $diana->save();
        foreach ($sellers as $s) {
            $history = new HistorySellerRule();
            $history->seller_id = $s->id;
            $history->rule_id = $diana->id;
            $history->data = $diana;
            $history->created_at = Carbon::createFromFormat('Y-m-d', '2025-01-01');
            $history->updated_at = Carbon::createFromFormat('Y-m-d', '2025-01-01');
            $history->save();
        }
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};