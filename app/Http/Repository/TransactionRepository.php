<?php

namespace App\Http\Repository;

use App\Models\Bundle;
use App\Models\Internet;
use App\Models\Payment;
use App\Models\Transaction;

class TransactionRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Transaction::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getTransactionsByClientId($clientId)
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    public function lastDebitTransactionByClientId($clientId)
    {
        return $this->model
            ->where('client_id', $clientId)
            ->where('category', 'Servicio')
            ->orderBy('id', 'desc')
            ->first()->date ?? null;
    }

    public function lastCreditTransactionByClientId($clientId)
    {
        return $this->model
            ->where('client_id', $clientId)
            ->where('category', 'Pago')
            ->orderBy('id', 'desc')
            ->first()->date ?? null;
    }
    
}
