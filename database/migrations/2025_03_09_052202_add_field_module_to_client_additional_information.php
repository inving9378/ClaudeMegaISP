<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Livewire\after;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::where('name', 'ClientMainInformation')->first();
        $module->fields()->create([
            'name' => 'is_payment_activation_cost',
            'label' => 'Pago el Costo de Activación ?',
            'type' => 16,
            'additional_field' => false,
            'default_value' => false,
            'position' => 31
        ]);


        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->create([
            'name' => 'is_payment_activation_cost',
            'label' => 'Pago el Costo de Activación ?',
            'order' => 99,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ClientMainInformation')->first();
        $module->fields()->where('name', 'is_payment_activation_cost')->delete();

        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'is_payment_activation_cost')->delete();
    }
};
