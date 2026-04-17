<?php

namespace App\Http\Repository;

use App\Models\ConfigFinanceNotification;

class ConfigFinanceNotificationRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ConfigFinanceNotification::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getModelById($id)
    {
        return $this->model->find($id);
    }

    public function getAll()
    {
        return $this->model->get();
    }

    public function getNotificationTypePayment()
    {
        return $this->model->where('type_config', 'payment')->first();
    }

    public function getNotificationTypeInvoice()
    {
        return $this->model->where('type_config', 'invoice')->first();
    }

    public function getNotificationType($type)
    {
        return $this->model->where('type_config', $type)->first();
    }
}
