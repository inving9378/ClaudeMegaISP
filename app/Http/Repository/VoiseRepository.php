<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientVozService;
use App\Models\Interface\PackageRepositoryInterface;
use App\Models\Voise;

class VoiseRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Voise::query();
    }

    public function count()
    {
        return $this->model->count();
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
        $voise = $this->model->find($id);
        return $voise->promotion_enable;
    }
}
