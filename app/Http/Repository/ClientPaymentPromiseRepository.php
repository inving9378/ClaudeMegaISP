<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientPaymentPromise;
use App\Models\State;

class ClientPaymentPromiseRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientPaymentPromise::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS
    public function getPromiseAndCourtDateAndAmountPaymentNotPaid($clientId)
    {
        $promisePayment = $this->model->where('client_id', $clientId)->first();
        $numberAmountPaid = [
            'first' => 'first_amount_is_pay',
            'second' => 'second_amount_is_pay',
            'third' => 'third_amount_is_pay'
        ];
        $data = [];

        foreach ($numberAmountPaid as $key => $field) {
            if (!$promisePayment->$field) {
                $nextDate = null;
                if ($key == 'first') {
                    $nextDate = $promisePayment->second_court_date;
                } elseif ($key == 'second') {
                    $nextDate = $promisePayment->third_court_date;
                }

                $data['field'] = $field;
                $data['amount'] = $promisePayment->{$key . '_amount'};
                $data['date'] = $promisePayment->{$key . '_court_date'};
                $data['promise'] = $promisePayment;
                $data['nextDate'] = $nextDate;
                break;
            }
        }

        return $data;
    }


    // SETTERS

    public function create($data)
    {
        return $this->model->create($data);
    }
}
