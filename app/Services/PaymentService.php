<?php

namespace App\Services;

use App\Http\Repository\ClientRepository;
use App\Http\Repository\ReceiptRepository;
use App\Models\Interface\ServiceInterface;
use App\Models\Payment;
use App\Services\Finance\GeneralAccounting\GeneralAccountingService;
use Carbon\Carbon;

class PaymentService
{
    protected $model;
    public function __construct(ServiceInterface $model)
    {
        $this->model = $model;
    }

    public function addPaymentCostInstalationPaid($createInvoice = false, $crateTransaction = false)
    {
        $newPayment = new Payment();
        $newPayment->number = (new ReceiptRepository())->getReceipt();
        $newPayment->payment_method_id = 1;
        $newPayment->date = Carbon::now()->toDateTimeString();
        $newPayment->amount = $this->model->instalation_cost ?? 0;
        $newPayment->payment_period = 0;
        $newPayment->comment = 'Pago de Costo de Instalación';
        $newPayment->receipt = 0;
        $newPayment->send_receipt_after_payment = 0;
        $newPayment->add_by = auth()->user()->id ?? 0;
        $newPayment->paymentable_id = $this->model->client_id;
        $newPayment->paymentable_type = 'App\Models\Client';
        $newPayment->enabled_payment_promise = 0;
        $newPayment->save();

        if ($createInvoice) {
            $invoiceService = new InvoiceService($this->model);
            $invoiceService->addInvoiceCostInstallationPaid($newPayment->id);
        }

        $transaction = null;
        if ($crateTransaction) {
            $transactionService = new TransactionService($this->model);
            $transaction = $transactionService->addTransactionCostInstallationPaid($newPayment->id);
        }

        $generalAccountingService = new GeneralAccountingService();
        $generalAccountingService->setNewGeneralAccountingIncomeBeforeCostInstallationPayment($newPayment, $this->model->client, $transaction);
    }
}
