<?php

namespace App\Http\Repository;

use App\Models\Custom;
use App\Models\Interface\PackageRepositoryInterface;

class CustomRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Custom::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS
    public function getModelById($id){
        return $this->model->findOrFail($id);
    }

    public function getModelFilterById($id)
    {
        return $this->model->find($id);
    }

    public function getTitleById($id)
    {
        return $this->model->where('id', $id)->first()->title ?? '';
    }

    public function getPriceById($id)
    {
        return $this->model->where('id', $id)->first()->price ?? 0;
    }

    public function getIfModelHasPromotion($id)
    {
        $custom = $this->model->find($id);
        return $custom->promotion_enable;
    }

}
