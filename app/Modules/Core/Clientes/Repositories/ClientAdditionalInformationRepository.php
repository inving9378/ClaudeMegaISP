<?php

namespace App\Modules\Core\Clientes\Repositories;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Modules\Core\Clientes\Models\ClientAdditionalInformation;
use App\Modules\Core\Clientes\Models\ClientMainInformation;

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
