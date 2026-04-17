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

        $module->columnsDatatable()->where('name', 'created_at')->update([
            'order' => 11
        ]);

        $columnsDatatableByModule = [
            [
                'name' => 'start_time',
                'filter_name' => null,
                'label' => "Fecha de Inicio",
                'order' => 6
            ],
            [
                'name' => 'assigned_to',
                'filter_name' => null,
                'label' => "Asignado a",
                'order' => 7
            ],
            [
                'name' => 'priority',
                'filter_name' => null,
                'label' => "Prioridad",
                'order' => 8
            ],
            [
                'name' => 'estimated_time',
                'filter_name' => null,
                'label' => "Tiempo estimado",
                'order' => 9
            ],
            [
                'name' => 'dedicated_time',
                'filter_name' => null,
                'label' => "Tiempo Dedicado",
                'order' => 10
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [
            'assigned_to',
            'priority',
            'estimated_time',
            'estimated_time',
            'dedicated_time'
        ];
        $module = Module::where('name', 'Task')->first();
        foreach ($columns as $col) {
            $module->columnsDatatable()->where('name', $col)->delete();
        }
    }
};
