<?php

namespace App\Modules\Core\Configuracion\Repositories;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Modules\Core\Configuracion\Models\CommandConfig;

class CommandConfigRepository
{
    protected $model;

    public function __construct()
    {
        $this->model = CommandConfig::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function all()
    {
        return $this->model->get();
    }

    public function getAllCommandActive()
    {
        return $this->model->where('status', ComunConstantsController::IS_NUMERICAL_TRUE)->get();
    }

    public function getHourlyToSuspendService()
    {
        return $this->model->where('process_name', 'suspends_services_early_in_the_day:process')->first()->execution_time;
    }


    // SETTERS




}
