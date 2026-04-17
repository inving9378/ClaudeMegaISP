<?php

namespace App\Http\Repository;

use App\Models\Interface\PackageRepositoryInterface;
use App\Models\Internet;

class InternetRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Internet::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getModelFilterById($id)
    {
        return $this->model->find($id);
    }
    public function getModelFilterByTitle($title)
    {
        return $this->model->where('title', $title)->first();
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
        $internet = $this->model->find($id);
        return $internet->promotion_enable;
    }
}
