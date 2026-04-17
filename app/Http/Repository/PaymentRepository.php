<?php

namespace App\Http\Repository;

use App\Models\Bundle;
use App\Models\Internet;
use App\Models\Payment;
use Carbon\Carbon;

class PaymentRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Payment::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getPaymentsByClientId($clientId)
    {
        return $this->model->where('paymentable_id', $clientId)->get();
    }

    public function obtenerLaFechaDelUltimoPagoByClientId($clientId)
    {
        return $this->model->where('paymentable_id', $clientId)
            ->orderBy('date', 'desc')
            ->first()->date ?? null;
    }

    public function obtenerMontoDelUltimoPagoByClientId($clientId){
        return $this->model->where('paymentable_id', $clientId)
            ->orderBy('date', 'desc')
            ->first()->amount ?? null;
    }

    public function getTotalPaymentCurrentMonth()
    {
        return $this->model->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->count();
    }

    public function getTotalAmountPaymentCurrentMonth()
    {
        return $this->model->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');
    }

    public function getTotalPaymentLastMonth()
    {
        return $this->model->whereMonth('date', Carbon::now()->subMonth()->month)
            ->whereYear('date', Carbon::now()->subMonth()->year)
            ->count();
    }

    public function getTotalAmountPaymentLastMonth()
    {
        return $this->model->whereMonth('date', Carbon::now()->subMonth()->month)
            ->whereYear('date', Carbon::now()->subMonth()->year)
            ->sum('amount');
    }
}
