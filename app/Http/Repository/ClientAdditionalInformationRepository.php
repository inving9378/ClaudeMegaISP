<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientAdditionalInformation;
use App\Models\ClientMainInformation;

class ClientAdditionalInformationRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientAdditionalInformation::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getClientAdditionalInformationByClientId($id)
    {
        return $this->model->where('client_id', $id)->get();
    }


    public function setNomenclatureByClient($client_id, $nomenclature)
    {
        $model = $this->model->where('client_id', $client_id)->first();
        if ($model) {
            $model->box_nomenclator = $nomenclature;
            $model->save();
        }
    }
}
