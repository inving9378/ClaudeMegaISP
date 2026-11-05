<?php

namespace App\Services;

use Carbon\Carbon;
use App\Http\Repository\TaskRepository;
use App\Models\Module;
use App\Models\Task;

class TaskService
{

    public function getTasksToEvents($filters = [])
    {
        $taskRepository = new TaskRepository();
        $columns = Module::where('name', 'Task')->first()->columnsDatatable->pluck('name')->toArray();
        $filters['archived']= [
            false
        ];
        $query = Task::filters($columns, null, $filters);

        // Misma restricción que el listado de tareas: sin task_view_full_task (ni admin) solo las asignadas
        $user = auth()->user();
        if (!$user || (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->can('task_view_full_task'))) {
            $query->whereHas('users', fn($q) => $q->where('user_id', $user?->id));
        }

        // UNI-T F4: con el interruptor encendido y un rango visible, solo se leen las tareas de ese
        // rango y se excluyen las canceladas. Sin rango (llamadas previas) el comportamiento no cambia.
        if (config('unificacion.interruptores.calendario_f4') && !empty($filters['rango_inicio']) && !empty($filters['rango_fin'])) {
            $desde = Carbon::parse($filters['rango_inicio'])->toDateString();
            $hasta = Carbon::parse($filters['rango_fin'])->toDateString();
            $query->whereBetween('tasks.start_date', [$desde, $hasta])
                ->whereNotIn('tasks.status', ['Cancelled', 'Canceled', 'Cancelado', 'Cancelada']);
        }

        return $query->get();
    }

    /**
     * #698: acceso por id a una tarea. Misma regla que el listado y el calendario: administradores,
     * super-administradores y task_view_full_task ven todas; el resto solo las suyas (task_user).
     * Una tarea ajena o inexistente responde 404 (no revela que existe).
     */
    public static function tareaAutorizada($id): Task
    {
        $task = Task::find($id);
        $user = auth()->user();
        abort_if(!$task || !$user, 404);
        if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->can('task_view_full_task')) {
            abort_unless($task->users()->where('users.id', $user->id)->exists(), 404);
        }
        return $task;
    }
}
