<?php

namespace App\Services;

use App\Http\Repository\ClientRepository;
use App\Jobs\Client\BillingService\RectifyBalanceAndCreateTransaction;
use App\Models\Interface\ServiceInterface;
use Carbon\Carbon;

class BillingService
{
    protected $service;
    protected $model;
    public function __construct(ServiceInterface $model)
    {
        $this->model = $model;
    }

    public function chargeService()
    {
        $client = $this->model->getClientWithBalanceAndBillingConfiguration();

        if ($this->checkIfNeverHasPaidService() ||
            $this->checkIfItTimeToPaidService($client)) {
            $this->paidService();
        }
    }

    private function checkIfNeverHasPaidService()
    {
        return !$this->model->haveTransaction();
    }

    private function checkIfItTimeToPaidService($client)
    {
        $fechaPago = Carbon::parse($client->fecha_pago)->toDateString();

        return $fechaPago <= Carbon::now()->toDateString();
    }

    private function paidService()
    {
        RectifyBalanceAndCreateTransaction::dispatch($this->model);
    }
}
