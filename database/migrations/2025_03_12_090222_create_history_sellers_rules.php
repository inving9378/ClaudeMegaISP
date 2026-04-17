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
        Schema::create('history_sellers_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->unsignedBigInteger('rule_id');
            $table->foreign('rule_id')->references('id')->on('commissions_rules')->cascadeOnDelete();
            $table->json('data');
            $table->timestamps();
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
        Schema::dropIfExists('history_sellers_rules');
    }
};
