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
            'name' => 'add_by',
            'filter_name' => null,
            'label' => "Agregado por",
            'active' => true,
            'order' => 6
        ]);
        $module->columnsDatatable()->where('name', 'action')->update([
            'order' => 7
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', Module::FINANCE_PAYMENT_MODULE_NAME)->first();

        $module->columnsDatatable()->where('name', 'action')->update([
            'order' => 6
        ]);
        $module->columnsDatatable()->where('name', 'add_by')->delete();
    }
};
