<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientVozService;
use App\Models\Task;
use App\Models\Voise;

class TaskRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Task::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getModelById($id)
    {
        return $this->model->find($id);
    }

    public function getAllTasks()
    {
        if (auth()->user()->isAdmin()) {
            return $this->model->get();
        }
        return $this->model->where('assigned_to', auth()->user()->id)->get();
    }


    public function getTasksToUpdate()
    {
        return $this->model->whereDate('start_time', '<', now())->orWhereNull('start_time')->get();
    }
}
