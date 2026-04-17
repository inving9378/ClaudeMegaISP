<?php

namespace App\Services;

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
        return $query->get();
    }
}
