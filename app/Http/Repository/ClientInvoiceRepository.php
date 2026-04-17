<?php

namespace App\Http\Repository;

use App\Models\Bundle;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Internet;
use App\Models\Payment;
use Carbon\Carbon;

class ClientInvoiceRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = ClientInvoice::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function getInvoicesByClientId($clientId)
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    //CURRENT MONTH
    public function getTotalInvoiceCurrentMonth()
    {
        //Clientes recurrentes
        return $this->model->whereHas('client', function ($query) {
            $query->typeBillingRecurrent();
        })->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
    }

    public function getTotalAmountInvoiceCurrentMonth()
    {
        //Clientes recurrentes
        return $this->model->whereHas('client', function ($query) {
            $query->typeBillingRecurrent();
        })->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total');
    }

    public function getTotalInvoicePendingCurrentMonth()
    {
        return Client::with('client_invoices')->active()
            ->typeBillingRecurrent()
            ->whereDoesntHave('client_invoices', function ($query) {
                $query->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
            })->count();
    }

    public function getTotalAmountInvoicePendingCurrentMonth()
    {
        //clientes recurrentes que estan activos y no tienen facturas de este mes sumar el costo de los servicios
        $clientRepository = new ClientRepository();
        $sum = 0;
        $clients = Client::with('client_invoices')->active()
            ->typeBillingRecurrent()
            ->whereDoesntHave('client_invoices', function ($query) {
                $query->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
            })->get();
        foreach ($clients as $client) {
            $sum += $clientRepository->getCostAllService($client->id);
        }

        return $sum;
    }


//LAST MONTH
    public function getTotalInvoiceLastMonth()
    {
        return $this->model->whereHas('client', function ($query) {
            $query->typeBillingRecurrent();
        })->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->count();
    }


    public function getTotalAmountInvoiceLastMonth()
    {
        return $this->model->whereHas('client', function ($query) {
            $query->typeBillingRecurrent();
        })->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->sum('total');
    }

    public function getTotalInvoicePendingLastMonth()
    {
        return Client::with('client_invoices')->active()
            ->typeBillingRecurrent()
            ->whereDoesntHave('client_invoices', function ($query) {
                $query->whereMonth('created_at', Carbon::now()->subMonth()->month)
                    ->whereYear('created_at', Carbon::now()->subMonth()->year);
            })->count();
    }

    public function getTotalAmountInvoicePendingLastMonth()
    {
        //clientes recurrentes que estan activos y no tienen facturas de este mes sumar el costo de los servicios
        $clientRepository = new ClientRepository();
        $sum = 0;
        $clients = Client::with('client_invoices')->active()
            ->typeBillingRecurrent()
            ->whereDoesntHave('client_invoices', function ($query) {
                $query->whereMonth('created_at', Carbon::now()->subMonth()->month)
                    ->whereYear('created_at', Carbon::now()->subMonth()->year);
            })->get();
        foreach ($clients as $client) {
            $sum += $clientRepository->getCostAllService($client->id);
        }

        return $sum;
    }



}
