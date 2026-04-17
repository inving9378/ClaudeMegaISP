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
        Schema::table('general_accounting_operations', function (Blueprint $table) {
            $table->dateTime('operation_date')->nullable()->after('updated_by');
        });

        $module = Module::where('name', 'GeneralAccountingOperation')->first();
        $fields = [
            [
                'name' => 'operation_date',
                'label' => 'Fecha',
                'placeholder' => 'Seleccione la fecha',
                'type' => 36,
                'additional_field' => false,
                'position' => 10,
            ]
        ];

        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_accounting_operations', function (Blueprint $table) {
            $table->dropColumn('operation_date');
        });

        $module = Module::where('name', 'GeneralAccountingOperation')->first();
        $module->fields()->where('name', 'operation_date')->delete();
    }
};
