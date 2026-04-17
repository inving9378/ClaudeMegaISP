<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Municipality;

class MunicipalityRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Municipality::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getMunicipalityNameById($id)
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
