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
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('title');
            $table->index('address');
        });
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'start_time')->first()->update([
            'label' => 'Fecha de Inicio',
        ]);
        $module->fields()->where('name', 'end_time')->first()->update([
            'label' => 'Fecha Fin'
        ]);

        $module->fields()->where('name', 'estimated_time')->first()->update([
            'type' => 3
        ]);

        $module->fields()->where('name', 'dedicated_time')->first()->update([
            'type' => 3
        ]);

        $module->fields()->where('name', 'time_to_task_location')->first()->update([
            'type' => 3
        ]);

        $module->fields()->where('name', 'time_from_task_location')->first()->update([
            'type' => 3
        ]);



        $columnsDatatableByModule = [
            [
                'name' => 'address',
                'filter_name' => null,
                'label' => "Dirección",
                'order' => 12
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);

        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $moduleLeft->fields()->where('name', 'dedicated_time')->first()->update([
            'type' => 3
        ]);

        $moduleRight = Module::where('name', 'TaskRight')->first();
        $moduleRight->fields()->where('name', 'time_to_task_location')->first()->update([
            'type' => 3
        ]);
        $moduleRight->fields()->where('name', 'time_from_task_location')->first()->update([
            'type' => 3
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['title']);
            $table->dropIndex(['address']);
        });


        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'start_time')->first()->update([
            'label' => 'Start Time'
        ]);
        $module->fields()->where('name', 'end_time')->first()->update([
            'label' => 'End Time'
        ]);


        $module->fields()->where('name', 'estimated_time')->first()->update([
            'type' => 1
        ]);

        $module->fields()->where('name', 'dedicated_time')->first()->update([
            'type' => 1
        ]);

        $module->fields()->where('name', 'time_to_task_location')->first()->update([
            'type' => 37
        ]);

        $module->fields()->where('name', 'time_from_task_location')->first()->update([
            'type' => 37
        ]);

        $module->columnsDatatable()->where('name', 'address')->delete();

        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $moduleLeft->fields()->where('name', 'dedicated_time')->first()->update([
            'type' => 1
        ]);

        $moduleRight = Module::where('name', 'TaskRight')->first();
        $moduleRight->fields()->where('name', 'time_to_task_location')->first()->update([
            'type' => 37
        ]);
        $moduleRight->fields()->where('name', 'time_from_task_location')->first()->update([
            'type' => 37
        ]);
    }
};
