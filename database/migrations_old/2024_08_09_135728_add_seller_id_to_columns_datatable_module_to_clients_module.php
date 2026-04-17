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
        $module = Module::where('name', 'Client')->first();

        $module->columnsDatatable()->where('name', 'vendor')->delete();
        $module->columnsDatatable()->create([
            'name' => 'seller_id',
            'label' => 'Vendedor',
            'order' => 25,
            'filter_name' => 'client_main_information.seller_id',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'seller_id')->delete();
    }
};
