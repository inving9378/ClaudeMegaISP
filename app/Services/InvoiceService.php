<?php

namespace App\Services;

use App\Http\Repository\ClientRepository;
use App\Jobs\Client\Invoice\ClientInvoiceJob;
use App\Models\ClientInvoice;
use App\Models\Interface\ServiceInterface;
use Carbon\Carbon;

class InvoiceService
{
    protected $model;
    public function __construct(ServiceInterface $model = null)
    {
        $this->model = $model;
    }

    public function addInvoice()
    {
        ClientInvoiceJob::dispatch($this->model);
    }


    public function addInvoiceCostInstallationPaid($paymentId = null)
    {
        $clientRepository = new ClientRepository();

        $this->model->client->client_invoices()->create([
            'number' => $clientRepository->setInvoiceNumber(),
            'total' => $this->model->instalation_cost ?? 0,
            'estado' => 'Pagar (del saldo de la cuenta)',
            'note' => 'Pago del Costo de Instalacion',
            'last_update' => Carbon::now()->toDateString(),
            'pay_up' => null,
            'use_of_transactions' => 1,
            'payment' => $paymentId ? $paymentId : 1,
            'is_sent' => false,
            'delete_transactions' => false,
            'added_by' => auth()->user()->id,
            'type' => ClientInvoice::TYPE_INVOICE_SERVICES,
            'payment_date' => Carbon::now()->toDateString()
        ]);
    }

    public function addInvoiceCostActivation($client, $paymentId = null) {
        $client->client_invoices()->create([
            'number' => $client->client_invoices()->max('number') + 1,
            'total' => $client->activation_cost ?? 0,
            'estado' => 'Pagar (del saldo de la cuenta)',
            'note' => 'Pago del Costo de Activacion',
            'last_update' => Carbon::now()->toDateString(),
            'pay_up' => null,
            'use_of_transactions' => 1,
            'payment' => $paymentId ? $paymentId : 1,
            'is_sent' => false,
            'delete_transactions' => false,
            'added_by' => auth()->user()->id ?? 0,
            'type' => ClientInvoice::TYPE_INVOICE_SERVICES,
            'payment_date' => Carbon::now()->toDateString()
        ]);
    }
}
