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
        $columnsDatatableModuele = $module->columnsDatatable()->get();
        foreach ($columnsDatatableModuele as $column) {
            if ($column->name != 'id') {
                $column->order =  $column->order + 1;
                $column->save();
            }
        }
        $module->columnsDatatable()->create([
            'name' => 'full_name',
            'label' => 'Nombre Completo',
            'order' => 1,
            'filter_name' => 'full_name',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'full_name')->delete();
    }
};
