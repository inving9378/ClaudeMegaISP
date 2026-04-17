<?php

use App\Models\CommissionRule;
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
        $comissionRules = CommissionRule::all();

        foreach ($comissionRules as $rule) {
            $type = $rule->sellers()->first();
            if($type){
                $rule->type_of_seller = $type->type_id;
                $rule->save();
            }

        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $comissionRules = CommissionRule::all();
        foreach ($comissionRules as $rule) {
            $rule->type_of_seller = null;
            $rule->save();
        }
    }
};
