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
        $module = Module::where('name', 'Task')->first();
        $module->columnsDatatable()->create([
            'name' => 'client_main_information_id',
            'label' => 'Cliente Asignado',
            'order' => 3
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        $module->columnsDatatable()->where('name','client_main_information_id')->delete();
    }
};
