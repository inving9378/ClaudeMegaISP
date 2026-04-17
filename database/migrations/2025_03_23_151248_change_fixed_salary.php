<?php

use App\Models\HistorySellerRule;
use App\Models\Seller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->boolean('is_fixed_salary')->default(false)->after('fixed_salary');
        });

        DB::delete('delete from history_sellers_rules');

        $sellers = Seller::all();
        foreach ($sellers as $s) {
            $rule = $s->commissionRules()->first();
            if ($rule) {
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
            $table->dropColumn('is_fixed_salary');
        });
    }
};