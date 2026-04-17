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
        $columnsDatatableByModule = [
            [
                'name' => 'status',
                'filter_name' => null,
                'label' => "Estado",
                'order' => 5
            ],
            [
                'name' => 'created_at',
                'filter_name' => null,
                'label' => "Fecha de Creacion",
                'order' => 6
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        $module = Module::where('name', 'Task')->first();
        $module->columnsDatatable()->where('name', 'status')->delete();
        $module->columnsDatatable()->where('name', 'created_at')->delete();
    }
};
