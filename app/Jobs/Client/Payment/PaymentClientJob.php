<?php

namespace App\Jobs\Client\Payment;

use App\Http\Repository\ClientRepository;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ClientService\ClientBillingService;
use App\Services\Finance\GeneralAccounting\GeneralAccountingService;
use App\Services\Finance\Invoice\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PaymentClientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payment;
    protected $oldPayment;
    protected $action;
    protected $clientRepository;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Payment $payment, $action, $oldPayment = null)
    {
        $this->payment = $payment;
        $this->action = $action;
        $this->oldPayment = $oldPayment;
    }

    public function handle(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
        $action = $this->action;
        $client = $clientRepository->getClientFilteredByPaymentableId($this->payment->paymentable_id);
        $this->$action($client);
    }

    // ClientRepository::getClientFilteredByPaymentableId() devuelve la clase
    // modular (\App\Modules\Core\Clientes\Models\Client), no el proxy legacy
    // App\Models\Client. Type-hint amplio a la clase parent — App\Models\Client
    // extends modular, así que callers que pasan el proxy siguen funcionando.
    public function created(\App\Modules\Core\Clientes\Models\Client $client)
    {
        // Idempotencia: si ya existe un crédito para este payment_id, el job ya
        // corrió exitosamente (o fue reintentado tras un commit parcial previo).
        // Salir sin duplicar balance ni transactions.
        $yaAcreditado = DB::table('transactions')
            ->where('payment_id', $this->payment->id)
            ->where('type', 'credit')
            ->where('is_payment', 1)
            ->exists();

        if ($yaAcreditado) {
            return;
        }

        // Transacción única: si cualquier paso falla, el rollback revierte
        // updateClientBalance + addTransaction, así el próximo reintento vuelve
        // a encontrar el guard de idempotencia en false y repite limpiamente.
        DB::transaction(function () use ($client) {
            $client->updateClientBalance($this->payment);

            $client->load('balance');
            $newBalance = $client->balance->amount;
            $transaction = $client->addTransaction($this->payment, $newBalance);

            if ($this->payment->is_first_payment) {
                $client->client_main_information->activation_date = $this->payment->date;
                $client->client_main_information->save();
            }

            $billingService = new ClientBillingService();
            $billingService->billing($client, $newBalance, $transaction);

            $generalAccountingService = new GeneralAccountingService();
            $generalAccountingService->setNewGeneralAccountingIncomeBeforePayment($this->payment, $client, $transaction);
        });
    }
}
