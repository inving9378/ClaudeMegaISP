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
        $module = Module::where('name', "Task")->first();
        $module->fields()->where('name', 'time_to_task_location')->first()->update([
            'label' => 'Tiempo hasta la Tarea'
        ]);
        $module->fields()->where('name', 'time_from_task_location')->first()->update([
            'label' => 'Tiempo desde la Tarea'
        ]);
        $module->fields()->where('name', 'status')->first()->update([
            'options' => json_encode([
                'ToDo' => 'Por Hacer',
                'InProgress' => 'En Progreso',
                'Done' => 'Terminado'
            ])
        ]);

        $module->fields()->where('name', 'assigned_to')->first()->update([
            'search' => json_encode([
                'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'notClientRole'
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', "Task")->first();
        $module->fields()->where('name', 'time_to_task_location')->first()->update([
            'label' => 'Tiempo hasta la Tarea'
        ]);

        $module->fields()->where('name', 'time_from_task_location')->first()->update([
            'label' => 'Travel time from task location'
        ]);

        $module->fields()->where('name', 'status')->first()->update([
            'options' => json_encode([
                'ToDo' => 'To Do',
                'InProgress' => 'In Progress',
                'Done' => 'Done'
            ])
        ]);
        
        $module->fields()->where('name', 'assigned_to')->first()->update([
            'search' => json_encode([
                'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'leadProject'
            ])
        ]);
    }
};
