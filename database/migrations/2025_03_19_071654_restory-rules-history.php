<?php

use App\Models\CommissionRule;
use App\Models\HistorySellerRule;
use App\Models\Seller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->json('additional_sales_commissions')->nullable()->after('sales_commission_type');
        });
        $rules = HistorySellerRule::all();
        foreach ($rules as $r) {
            $r->delete();
        }
        $rules = CommissionRule::all();
        foreach ($rules as $r) {
            $r->distributors_commission = null;
            $r->monthly_bonus = null;
            if ($r->additional_sales_commission > 0) {
                $data = [
                    'iva' => false,
                    'type' => $r->additional_sales_commission_type,
                    'bonus' => $r->additional_sales_commission
                ];
                $r->additional_sales_commissions = $data;
                $selected = $r->selected_fields;
                $selected = array_map(function ($v) {
                    return $v == 'additional_sales_commission' ? 'additional_sales_commissions' : $v;
                }, $selected);
                $r->selected_fields = $selected;
            }
            $r->save();
        }
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->dropColumn('additional_sales_commission', 'additional_sales_commission_type');
        });
        $sellers = Seller::all();
        foreach ($sellers as $s) {
            $rule = $s->commissionRules()->first();
            if ($rule) {
                if ($rule->monthly_bonus) {
                    $bonus = [];
                    foreach ($rule->monthly_bonus as $b) {
                        $monthly_bonus = $b;
                        unset($monthly_bonus['service']);
                        $bonus[] = $monthly_bonus;
                    }
                    $rule->monthly_bonus = $bonus;
                    $rule->save();
                }
                HistorySellerRule::create([
                    'rule_id' => $rule->id,
                    'seller_id' => $s->id,
                    'data' => $rule,
                    'created_at' => $rule->created_at,
                    'updated_at' => $rule->updated_at
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->decimal('additional_sales_commission', $precision = 8, $scale = 2)->nullable()->default(0)->after('sales_commission_type');
            $table->string('additional_sales_commission_type')->nullable()->after('additional_sales_commission');
        });
        $rules = CommissionRule::all();
        foreach ($rules as $r) {
            if (isset($r->additional_sales_commissions)) {
                $r->additional_sales_commission_type = $r->additional_sales_commissions['type'];
                $r->additional_sales_commission = $r->additional_sales_commissions['bonus'];
                $selected = $r->selected_fields;
                $selected = array_map(function ($v) {
                    return $v == 'additional_sales_commissions' ? 'additional_sales_commission' : $v;
                }, $selected);
                $r->selected_fields = $selected;
                $r->save();
            }
        }
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->dropColumn('additional_sales_commissions');
        });
    }
};
