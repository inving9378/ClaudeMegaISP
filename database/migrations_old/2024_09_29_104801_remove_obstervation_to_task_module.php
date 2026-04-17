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
        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $moduleLeft->fields()->where('name', 'observation')->first()->delete();

        $module = Module::where('name', 'Task')->first();
        $columnsDatatableByModule = [
            [
                'name' => 'description',
                'filter_name' => null,
                'label' => "Descripcion",
                'order' => 3
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $fieldsLeft = [
            [
                'name' => 'observation',
                'label' => 'Observaciones',
                'placeholder' => 'Observaciones',
                'type' => 42,
                'position' => 14,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
        ];
        $moduleLeft->fields()->createMany($fieldsLeft);

        $module = Module::where('name', 'Task')->first();

        $module->columnsDatatable()->where('name', 'description')->delete();
    }
};
