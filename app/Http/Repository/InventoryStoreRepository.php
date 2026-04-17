<?php

namespace App\Http\Repository;

use App\Models\InventoryItem;
use App\Models\InventoryStore;

class InventoryStoreRepository
{

    protected $model;

    public function __construct()
    {
        $this->model = InventoryStore::query();
    }

    public function count()
    {
        return $this->model->count();
    }


    public function getModelById($id)
    {
        return $this->model->where('id', $id)->first() ?? null;
    }
}
