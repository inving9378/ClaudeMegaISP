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
        $module = Module::where('name', 'Internet')->first();
        $module->columnsDatatable()->create([
            'name' => 'associated_clients',
            'label' => 'Clientes asociados',
            'order' => 20
        ]);
        $module = Module::where('name', 'Internet')->first();
        $module->columnsDatatable()->where('name', 'action')->update([
            'order' => 9999
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Internet')->first();
        $module->columnsDatatable()->where('name', 'associated_clients')->delete();
    }
};
