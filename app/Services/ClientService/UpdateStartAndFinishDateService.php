<?php

namespace App\Services\ClientService;

use App\Http\Repository\ClientRepository;
use App\Models\ClientCustomService;
use App\Models\TypeBilling;
use Carbon\Carbon;

class UpdateStartAndFinishDateService
{
    protected $model;
    protected $clientRepository;
    public function __construct($model)
    {
        $this->model = $model;
        $this->clientRepository = new ClientRepository();
    }


    public function actualizaServiceConElDiaQueIniciaYTerminaElContrato()
    {
        if ($this->ifEsUnBundleService()) {
            $this->model->update([
                'contract_start_date' => Carbon::now()->toDateTimeString(),
                'contract_end_date' => $this->clientRepository->getEndDate($this->model)
            ]);
        } else {
            if ($this->esUnCustomService()) {
                $this->updateFinishDateCustomService();
                dd($this->model->finish_date);
            } else {
                $this->model->update([
                    'start_date' => Carbon::now()->toDateTimeString(),
                    'finish_date' => $this->clientRepository->getEndDate($this->model)
                ]);
            }
        }
    }

    public function esUnCustomService()
    {
        return $this->model instanceof ClientCustomService && !$this->model->client_bundle_service_id;
    }

    public function ifEsUnBundleService()
    {
        return $this->model->bundle_id;
    }


    public function updateFinishDateCustomService()
    {
        $typeOfBilling = $this->model->client->client_main_information->type_of_billing_id;

        $desiredDate = null;
        if ($this->$typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT) {
            $desiredDate = $this->addYear();
            $this->model->update([
                'start_date' => Carbon::now()->toDateTimeString(),
                'finish_date' => $desiredDate->toDateTimeString()
            ]);
        } else if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM) {
            $this->model->update([
                'start_date' => Carbon::now()->toDateTimeString(),
                'finish_date' => Carbon::now()->addYear()->subDay()->endOfDay()->format('Y-m-d H:i:s')
            ]);
        } else {
            $this->model->update([
                'start_date' => Carbon::now()->toDateTimeString(),
                'finish_date' => $this->clientRepository->getEndDate($this->model)
            ]);
        }
    }


    public function addYear()
    {
        $billingConfiguration = $this->model->client->billing_configuration()->first();
        $billingDate = $billingConfiguration->billing_date;
        $billingExpiration = $billingConfiguration->billing_expiration;
        $expiration = $this->model->contract_end_date ?? $this->model->finish_date;
        // Calcular la fecha de facturación deseada
        $currentDate = $expiration ?? $this->model->client->created_at;
        $currentDate = Carbon::parse($currentDate);
        $nextMonth = $currentDate->copy()->addYear();
        $desiredDate = $nextMonth->day($billingDate)->addDays($billingExpiration);
        return $desiredDate;
    }
}
