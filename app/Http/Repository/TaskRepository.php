<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientVozService;
use App\Models\Task;
use App\Models\Voise;
use Carbon\Carbon;

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
        return $this->model
            ->where(fn ($q) => $q->whereDate('start_time', '<', now())->orWhereNull('start_time'))
            // Item #1158: con las atrasadas encendidas, una orden de Talento conserva su día (sale en
            // rojo en «Mi día» hasta que se termine o se reprograme con motivo); no se mueve a hoy.
            ->when(config('talento.ordenes_atrasadas_en_mi_dia', true), fn ($q) => $q->whereNull('talento_type_id'))
            ->get();
    }

    public function getTasksByIdInArray($taskIds){
        return $this->model->whereIn('id', $taskIds)->get();
    }

    public function getStartingTodayTasks($taskIds){
        return $this->model->whereIn('id', $taskIds)
            ->whereDate('start_time', Carbon::today())
            ->pluck('id')
            ->toArray();
    }
}
