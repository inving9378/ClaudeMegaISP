<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientMainInformation;
use Illuminate\Support\Facades\Log;

class ClientMainInformationRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientMainInformation::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function setClientMainInformationByClientId($clientId)
    {
        $this->model = $this->model->where('client_id', $clientId);
    }

    public function getModelById($id)
    {
        return $this->model->where('id', $id)->first();
    }


    // GETTERS

    public function getClientMainInformationByClientId($id)
    {
        return $this->model->where('client_id', $id)->first();
    }

    public function getClientMainInformationByClientIdGet($id)
    {
        return $this->model->where('client_id', $id)->get();
    }

    public function getClientIdByClientMainInformationId($id)
    {
        return $this->model->where('id', $id)->first()->client_id ?? null;
    }

    public function  getTypeOfBillingIdByClientId($clientId)
    {
        return $this->model->where('client_id', $clientId)->whereHas('type_billing')->first()->type_of_billing_id ?? null;
    }


    public function getClientsBySellerId($id)
    {
        return $this->model->where('seller_id', $id)->get();
    }

    // SETTERS

    public function setStateBlocked()
    {
        ClientMainInformation::find($this->model->first()->id)->update(['estado' => ClientMainInformation::STATE_BLOCKED]);
    }

    public function setStateActive()
    {
        ClientMainInformation::find($this->model->first()->id)->update(['estado' => ComunConstantsController::STATE_ACTIVE]);
    }

    public function setStateInactive()
    {
        ClientMainInformation::find($this->model->first()->id)->update(['estado' => ClientMainInformation::STATE_INACTIVE]);
    }
}
