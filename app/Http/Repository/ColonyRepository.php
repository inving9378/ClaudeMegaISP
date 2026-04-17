<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientMainInformation;
use App\Models\Colony;

class ColonyRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Colony::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getColonyNameById($id)
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
