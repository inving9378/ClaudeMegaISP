<?php

namespace App\Http\Repository;

use App\Models\Bundle;
use App\Models\Interface\PackageRepositoryInterface;

class BundleRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Bundle::query();
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

    public function getPlanesInternetByBundleId($id)
    {
        return $this->model->where('id', $id)->first()->planes_internet ?? null;
    }

    public function getPlanesVozByBundleId($id)
    {
        return $this->model->where('id', $id)->first()->planes_voz ?? null;
    }

    public function getPlanesCustomByBundleId($id)
    {
        return $this->model->where('id', $id)->first()->planes_custom ?? null;
    }


    public function getCountOptionsByBundle($id)
    {
        // Obtener el Bundle de referencia
        $bundle = $this->model->with(['planes_internet', 'planes_voz', 'planes_custom'])->find($id);

        $internetCounts = $bundle->planes_internet->count();
        $vozCounts = $bundle->planes_voz->count();
        $customCounts = $bundle->planes_custom->count();

        return [
            'internet' => $internetCounts,
            'voz' => $vozCounts,
            'custom' => $customCounts
        ];
    }


    public function getSimilarBundlesByCountPlanes($arrayCounts, $id)
    {
        $countInternet = $arrayCounts['internet'];
        $countVoz = $arrayCounts['voz'];
        $countCustom = $arrayCounts['custom'];

        $bundlesSimilar = $this->model
            ->with(['planes_internet', 'planes_voz', 'planes_custom'])
            ->get()
            ->filter(function ($similarBundle) use ($countInternet, $countVoz, $countCustom) {
                return $similarBundle->planes_internet->count() === $countInternet;
            });
        return $bundlesSimilar;
    }

    public function getIfModelHasPromotion($id)
    {
        $bundle = $this->model->find($id);
        return $bundle->promotion_enable;
    }
}
