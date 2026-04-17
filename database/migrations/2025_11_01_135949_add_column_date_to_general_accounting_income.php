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
        Schema::table('general_accounting_expenses', function (Blueprint $table) {
            $table->dateTime('operation_date')->nullable()->after('created_by');
        });
        Schema::table('general_accounting_incomes', function (Blueprint $table) {
            $table->dateTime('operation_date')->nullable()->after('created_by');
        });

        $module = Module::where('name', 'GeneralAccountingExpense')->first();
        $columnsDatatable = [
            [
                'name' => 'operation_date',
                'label' => 'Fecha',
                'order' => 20,
            ],

        ];
        $module->columnsDatatable()->createMany($columnsDatatable);
        $module->columnsDatatable()->where('name', 'created_at')->delete();

        $moduleIncome = Module::where('name', 'GeneralAccountingIncome')->first();
        $columnsDatatable = [
            [
                'name' => 'operation_date',
                'label' => 'Fecha',
                'order' => 20,
            ],
        ];
        $moduleIncome->columnsDatatable()->createMany($columnsDatatable);
        $moduleIncome->columnsDatatable()->where('name', 'created_at')->delete();

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_accounting_expenses', function (Blueprint $table) {
            $table->dropColumn('operation_date');
        });
        Schema::table('general_accounting_incomes', function (Blueprint $table) {
            $table->dropColumn('operation_date');
        });

        $module = Module::where('name', 'GeneralAccountingExpense')->first();
        $module->columnsDatatable()->where('name', 'operation_date')->delete();
        $moduleIncome = Module::where('name', 'GeneralAccountingIncome')->first();
        $moduleIncome->columnsDatatable()->where('name', 'operation_date')->delete();
    }
};
