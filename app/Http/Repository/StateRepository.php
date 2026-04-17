<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\State;

class StateRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = State::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getStateNameById($id)
    {
        try {
            $model = $this->model->findOrFail($id);
            return $model->name;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return "";
        }
    }

    // SETTERS




}
