<?php

namespace App\Http\Repository;

use App\Models\InventoryItem;

class InventoryItemRepository
{

    protected $model;

    public function __construct()
    {
        $this->model = InventoryItem::query();
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
