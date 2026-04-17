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
        $module = Module::where('name', 'GeneralAccountingIncome')->first();

        $fields = [
            [
                'name' => 'description',
                'label' => 'Descripción del ingreso',
                'placeholder' => 'Describa en que consiste el ingreso',
                'type' => 5,
                'position' => 1,
            ],
            [
                'name' => 'amount',
                'label' => 'Monto',
                'placeholder' => 'Monto',
                'type' => 15,
                'position' => 2,
            ],
        ];

        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'GeneralAccountingIncome')->first();
        $module->fields()->where('name', 'description')->delete();
        $module->fields()->where('name', 'amount')->delete();
    }
};
