<?php

namespace App\Http\Repository;

use App\Models\InventoryItemMedia;

class InventoryItemMediaRepository
{

    protected $model;

    public function __construct()
    {
        $this->model = InventoryItemMedia::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function create($data)
    {
        return $this->model->create($data);
    }

    public function getMediaByItem($id)
    {
        return $this->model->where('inventory_item_id', $id)->get();
    }

    public function getMediaByItemStock($id)
    {
        return $this->model->where('inventory_item_stock_id', $id)->get();
    }


}
