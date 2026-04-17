<?php

namespace App\Services;

use App\Http\Repository\ClientRepository;
use App\Http\Repository\ConfigFinanceNotificationRepository;
use App\Http\Repository\DocumentTemplateRepository;
use App\Models\Client;
use App\Models\InvoiceEmail;
use App\Models\PaymentEmail;
use App\Notifications\StandardNotification;
use App\Services\ClientService\ContractClientService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ConfigFinanceNotificationService
{
    protected $user;
    public function __construct()
    {
        $this->user = auth()->user();
    }

    /* Payment */
    public function createEmailPayment($payment)
    {
        $configFinanceNotificationPayment = (new ConfigFinanceNotificationRepository())->getNotificationTypePayment();
        if ($configFinanceNotificationPayment->auto_send_notifications == true) {
            try {
                $clientRepository = new ClientRepository();
                $client = $clientRepository->getClientById($payment->paymentable_id);
                $clientRepository = new ClientRepository();
                $client = $clientRepository->getClientById($payment->paymentable_id);
                $documentTemplateService = new DocumentTemplateService();
                $documentTemplateRepository = new DocumentTemplateRepository();
                $dataClient = (new ContractClientService())->getDataClient($client);
                $html = $documentTemplateService->validateAndReplaceTemplate($documentTemplateRepository->getHtmlById($configFinanceNotificationPayment->email_template_id), $dataClient);
                if ($html['status'] == 'fail') {
                    return Log::error($html['keys']);
                }
                $html = $this->replaceDinamicData($html['html'], $payment);
                $this->createEmail($html, $client, $configFinanceNotificationPayment->email_bcc,'App\Models\PaymentEmail');

            } catch (\Exception $e) {
                Log::error('error al enviar notificacion => ' . $e->getMessage());
            }
        }
    }

    public function replaceDinamicData($html, $payment)
    {
        $variablesDinamicas = [
            'data_dinamic.payment_id' => $payment->id,
            'data_dinamic.payment_date' => $payment->date,
            'data_dinamic.payment_amount' => $payment->amount,
            'data_dinamic.payment_period' => $payment->payment_period,
            'data_dinamic.payment_receipt' => $payment->receipt
        ];
        foreach ($variablesDinamicas as $key => $value) {
            $html = str_replace('${' . $key . '}', $value, $html);
        }
        return $html;
    }

    /* Invoice */

    public function createEmailInvoice($invoice)
    {
        $configFinanceNotificationPayment = (new ConfigFinanceNotificationRepository())->getNotificationTypeInvoice();
        if ($configFinanceNotificationPayment->auto_send_notifications == true ) {
            try {
                $invoice_id = $invoice->id;
                $client = Client::whereHas('client_invoices', function ($query) use ($invoice_id) {
                    $query->where('id', $invoice_id);
                })->first();
                $documentTemplateService = new DocumentTemplateService();
                $documentTemplateRepository = new DocumentTemplateRepository();
                $dataClient = (new ContractClientService())->getDataClient($client);
                $html = $documentTemplateService->validateAndReplaceTemplate($documentTemplateRepository->getHtmlById($configFinanceNotificationPayment->email_template_id), $dataClient);
                if ($html['status'] == 'fail') {
                    return Log::error($html['keys']);
                }
                $html = $this->replaceDinamicDataInvoice($html['html'], $invoice, $client);
                $this->createEmail($html, $client, $configFinanceNotificationPayment->email_bcc,'App\Models\InvoiceEmail');

            } catch (\Exception $e) {
                Log::error('error al enviar notificacion => ' . $e->getMessage());
            }
        }
    }

    public function replaceDinamicDataInvoice($html, $invoice, $client)
    {
        $variablesDinamicas = [
            'data_dinamic.debit' => $client->balance->amount,
            'data_dinamic.invoice_id' => $invoice->id,
            'data_dinamic.invoice_date' => $invoice->last_update,
            'data_dinamic.invoice_pay_up' => $invoice->pay_up,
            'data_dinamic.invoice_period' => '--',
        ];
        foreach ($variablesDinamicas as $key => $value) {
            $html = str_replace('${' . $key . '}', $value, $html);
        }
        return $html;
    }



    public function createEmail($html, $client, $email_bcc,$model)
    {
        $model::create([
            'client_id' => $client->id,
            'via' => 'email',
            'recipient_email' => $client->client_main_information->email,
            'cc_email' => $email_bcc,
            'recipient_phone' => $client->client_main_information->phone,
            'subject' => 'Tiket de Pago',
            'html' => $html,
        ]);
    }
}
