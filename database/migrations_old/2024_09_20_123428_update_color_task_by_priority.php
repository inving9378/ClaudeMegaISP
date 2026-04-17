<?php

use App\Models\Task;
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
        $statusColor = [
            'ToDo' => 'rgb(173, 216, 230)',     // Azul claro (Light Blue)
            'InProgress' => 'rgb(255, 223, 186)', // Amarillo pastel (Light Peach)
            'Done' => 'rgb(144, 238, 144)',      // Verde claro (Light Green)
        ];

        $tasks = Task::all(); // Obtén todas las tareas

        foreach ($tasks as $task) {
            // Asigna el color correspondiente al estado de la tarea
            if (isset($statusColor[$task->status])) {
                $task->task_color = $statusColor[$task->status];
                $task->save(); // Guarda los cambios
            } else {
                $task->status = 'ToDo';
                $task->task_color = 'rgb(173, 216, 230)';
                $task->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Aquí podrías revertir los colores si fuera necesario, por ejemplo, eliminando el color o estableciendo otro valor por defecto.
    }
};
