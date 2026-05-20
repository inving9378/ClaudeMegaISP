<?php

namespace App\Modules\Core\Layout\Repositories;

use App\Modules\Core\Layout\Models\AppLayoutConfiguration;

class AppLayoutConfigurationRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = AppLayoutConfiguration::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getModelByAuthUserId()
    {
        return $this->model->where('user_id', auth()->user()->id)->first();
    }

    public function create($array)
    {
        return $this->model->create($array);
    }
}
