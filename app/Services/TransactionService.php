<?php

namespace App\Services;

use App\Models\Interface\ServiceInterface;
use Carbon\Carbon;

class TransactionService
{
    protected $model;
    public function __construct(ServiceInterface $model = null)
    {
        $this->model = $model;
    }

    public function addTransactionCostInstallationPaid($paymentId = null, $invoiceId = null)
    {
        return $this->model->client->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'debit' => $this->model->instalation_cost ?? 0,
            'account_balance' => $this->model->client->balance->amount,
            'description' => 'Pago de Costo de Instalación',
            'category' => 'Pago de Costo de Instalación',
            'cantidad' => '1',
            'client_id' => $this->model->client->id,
            'type' => 'debit',
            'price' => $this->model->instalation_cost ?? 0,
            'iva' => 0,
            'total' => $this->model->instalation_cost ?? 0,
            'from_date' => null,
            'to_date' => null,
            'comment' => 'Pago de Costo de Instalación',
            'period' => null,
            'add_to_invoice' => true,
            'company_balance' => $this->model->client->balance->amount,
            'movement' => '- ' . $this->model->instalation_cost ?? 0,
            'service_name' => 'Costo de Instalación',
            'invoice' => null,
            'transactionable_id' => $this->model->client->id,
            'transactionable_type' => 'App\Models\Client',
            'is_payment' => false,
            'payment_id' => $paymentId ? $paymentId : false,
        ]);
    }

    public function addTransactionCostActivation($client, $paymentId = null)
    {
        return $client->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'debit' => $client->activation_cost ?? 0,
            'account_balance' => $client->balance->amount,
            'description' => 'Pago de Costo de Activación',
            'category' => 'Pago de Costo de Activación',
            'cantidad' => '1',
            'client_id' => $client->id,
            'type' => 'debit',
            'price' => $client->activation_cost ?? 0,
            'iva' => 0,
            'total' => $client->activation_cost ?? 0,
            'from_date' => null,
            'to_date' => null,
            'comment' => 'Pago de Costo de Activación',
            'period' => null,
            'add_to_invoice' => true,
            'company_balance' => $client->balance->amount,
            'movement' => '- ' . $client->activation_cost ?? 0,
            'service_name' => 'Costo de Activación',
            'invoice' => null,
            'transactionable_id' => $client->id,
            'transactionable_type' => 'App\Models\Client',
            'is_payment' => false,
            'payment_id' => $paymentId ? $paymentId : false,
        ]);
    }
}
