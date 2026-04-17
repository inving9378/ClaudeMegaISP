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
        $module->columnsDatatable->where('name', 'action')->first()->update([
            'order' => 999
        ]);
        $module->columnsDatatable()->create([
            'name' => 'nomenclature_name',
            'filter_name' => 'nomenclatures.name',
            'label' => "Nomenclatura",
            'order' => 94
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable->where('name', 'nomenclature_name')->first()->delete();
    }
};
