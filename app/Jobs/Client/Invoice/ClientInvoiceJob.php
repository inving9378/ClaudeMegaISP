<?php

namespace App\Jobs\Client\Invoice;

use App\Models\ClientInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Repository\ClientRepository;
use Carbon\Carbon;

class ClientInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $clientRepository;
    protected $clientService;
    protected $payment;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientService, $payment = true)
    {
        $this->clientService = $clientService;
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->addInvoiceService();
    }


    public function addInvoiceService()
    {
        $clientRepository = new ClientRepository();

        $this->clientService->client->client_invoices()->create([
            'number' => $clientRepository->setInvoiceNumber(),
            'total' => $this->clientService->price ?? 0,
            'estado' => 'Pagar (del saldo de la cuenta)',
            'last_update' => Carbon::now()->toDateString(),
            'pay_up' => $this->clientService->client->fecha_corte,
            'use_of_transactions' => 1,
            'payment' => $this->payment,
            'is_sent' => false,
            'delete_transactions' => false,
            'added_by' => '0',
            'type' => ClientInvoice::TYPE_INVOICE_SERVICES,
            'payment_date' => Carbon::now()->toDateString()
        ]);
    }
}
