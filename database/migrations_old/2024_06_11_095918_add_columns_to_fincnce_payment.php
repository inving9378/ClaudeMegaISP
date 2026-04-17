<?php

use App\Models\Module;
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
        $module = Module::where('name', Module::FINANCE_PAYMENT_MODULE_NAME)->first();
        $module->columnsDatatable()->create([
            'name' => 'id',
            'label' => 'Id',
            'order' => 0,
            'active' => true
        ]);

        $columns = [
            'payment_method_id',
            'paymentable_id',
            'date',
            'amount',
            'payment_period',
            'comment',
            'add_by',
            'action',
        ];
        $order = 1;
        foreach ($columns as $value) {
            $module->columnsDatatable()->where('name', $value)->update([
                'order' => $order++
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', Module::FINANCE_PAYMENT_MODULE_NAME)->first();
        $module->columnsDatatable()->where('name', 'id')->delete();

        $columns = [
            'payment_method_id',
            'paymentable_id',
            'date',
            'amount',
            'payment_period',
            'comment',
            'add_by',
            'action',
        ];
        $order = 0;
        foreach ($columns as $value) {
            $module->columnsDatatable()->where('name', $value)->update([
                'order' => $order++
            ]);
        }
    }
};
