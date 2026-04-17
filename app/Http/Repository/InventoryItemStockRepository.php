<?php

namespace App\Http\Repository;

use App\Models\InventoryItemStock;

class InventoryItemStockRepository
{

    protected $model;

    public function __construct()
    {
        $this->model = InventoryItemStock::query();
    }

    public function getAll()
    {
        return $this->model->get();
    }

    public function count()
    {
        return $this->model->count();
    }


    public function getModelById($id)
    {
        return $this->model->where('id', $id)->first() ?? null;
    }


    public function getModelByItemModelableTypeAndModelableId($inventoryItemId, $modelableType, $modelableId)
    {
        return $this->model->where('modelable_type', $modelableType)
            ->where('modelable_id', $modelableId)
            ->where('inventory_item_id', $inventoryItemId)
            ->first();
    }

    public function getItemsByUser($id)
    {
        return $this->model->where('modelable_type', 'App\Models\User')
            ->where('modelable_id', $id)
            ->get();
    }

    public function getItemsByStore($id)
    {
        return $this->model->where('modelable_type', 'App\Models\InventoryStore')
            ->where('modelable_id', $id)
            ->get();
    }

    public function getItemsByClient($id)
    {
        return $this->model->where('modelable_type', 'App\Models\Client')
            ->where('modelable_id', $id)
            ->get();
    }
}
