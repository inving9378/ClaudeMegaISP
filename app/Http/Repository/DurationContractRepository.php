<?php

namespace App\Http\Repository;

use App\Models\DurationContract;

class DurationContractRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = DurationContract::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS


    public function getModelById($id)
    {
        return $this->model->findOrFail($id);
    }

    public function getIdByDurationContract($duration)
    {
        $model = $this->model->where('duration', $duration)->first();
        if ($model) {
            return $model->id;
        }
        return null;
    }
}
