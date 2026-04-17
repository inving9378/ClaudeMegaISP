<?php

namespace App\Http\Repository;

use App\Models\Receipt;
use Carbon\Carbon;

class ReceiptRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Receipt::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getReceipt()
    {
        $today = Carbon::now()->toDateString();
        $countReceipt = $this->model->where('receiptable_type', 'App\Models\Client')->count() + 1;
        return '01 ' . $today . $countReceipt;
    }
}
