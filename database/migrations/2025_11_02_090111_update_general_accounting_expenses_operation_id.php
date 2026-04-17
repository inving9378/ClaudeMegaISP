<?php

use App\Models\GeneralAccountingExpense;
use App\Models\GeneralAccountingOperation;
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
        $generalAccountingExpenses = GeneralAccountingExpense::where('operation_id', null)->get();
        foreach ($generalAccountingExpenses as $generalAccountingExpense) {
            $generalAccountingOperation = GeneralAccountingOperation::where(
                'description',
                $generalAccountingExpense->description
            )
                ->where(
                    'amount',
                    $generalAccountingExpense->amount

                )->first();
            if ($generalAccountingOperation) {
                $generalAccountingExpense->update(['operation_id' => $generalAccountingOperation->id]);
            }
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
