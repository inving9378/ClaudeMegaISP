<?php


namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\HelpersModule\module\client\ClientPaymentDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\ConfigFinanceNotificationRepository;
use App\Http\Repository\DocumentTemplateRepository;
use App\Http\Requests\module\client\ClientPaymentRequest;
use App\Models\Client;
use App\Models\ClientPaymentMetadata;
use App\Models\GeneralAccountingIncome;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\MethodOfPayment;
use App\Models\Transaction;
use App\MyLibrary\Utility;
use App\Services\ClientService\ContractClientService;
use App\Services\ConfigFinanceNotificationService;
use App\Services\DocumentTemplateService;
use App\Services\Finance\Invoice\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClientPaymentController extends Controller
{
    private $helper;
    private $clientRepository;

    public function __construct(ClientPaymentDatatableHelper $helper, ClientRepository $clientRepository)
    {
        $this->helper = $helper;
        $this->clientRepository = $clientRepository;
        $this->data['model'] = 'App\Models\Payment';
        $this->data['module'] = 'ClientPayment';
    }

    public function store(ClientPaymentRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        ///TODO aqui falta implementar la parte de las promesas de pago
        $client = Client::find($id);
        try {
            DB::beginTransaction();
            if ($client) {
                $clientBalance = $client->balance->amount;
                $status = $client->client_main_information->estado;
                $fechaDeCorte = $client->fecha_corte;
                $fechaDePago = $client->fecha_pago;
                $fechaFinPeriodoGracia = $client->fecha_fin_periodo_gracia;
                if ($request->enabled_payment_promise == "true" || $client->active_promise_payment == true) {
                    $this->processPromiseOfPayment($client, $request);
                } else {
                    $payment = $client->clientCreatePayment($request);
                    $invoiceService = new InvoiceService();
                    $invoiceService->updateProformaInvoicePendingDespuesDeUnPago($client, $payment);

                    ClientPaymentMetadata::create([
                        'payment_id' => $payment->id,
                        'client_id' => $client->id,
                        'previous_balance' => $clientBalance,
                        'previous_status' => $status,
                        'previous_fecha_pago' => $fechaDePago,
                        'previous_fecha_corte' => $fechaDeCorte,
                        'notes' => 'Estado antes del pago',
                        'previous_fecha_fin_periodo_gracia' => $fechaFinPeriodoGracia
                    ]);

                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'El pago se ha realizado con éxito.',
                        'data' => $payment
                    ], 200);
                }
            } else {
                Log::info('No existe el cliente para este id: ' . $id);
                DB::rollBack();
                return response()->json(['error' => 'No existe el cliente para este id: ' . $id], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info('Error al iniciar la transaccion: ' . $e->getMessage());
            return response()->json(['error' => 'Error al iniciar la transaccion: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $model->updateFileUpload($request->file('file'));

        $request = collect($request->except(['file', 'date_payment']));
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');

        $input = Utility::modifyValueForCheckbox($input, 'ClientPayment');

        return $model->update($input);
    }


    public function processPromiseOfPayment($client, $request)
    {
        // TODO Las Promesas de pago deben revisarse con calma
        //        $service = new PromisePaymentClientService($client, $request->all());
        //        return $service->billingOrCreatePromisePayment();
    }



    public function destroy($id)
    {
        try {
            Model::withoutEvents(function () use ($id) {
                DB::beginTransaction();
                $model = $this->data['model']::find($id);
                if (!$model) {
                    throw new \Exception('Registro no encontrado');
                }
                $client = $model->client;
                //revisar si es el ultimo pago
                $payments = $client->payments;
                $payment = $payments->last();
                if ($payment->id != $model->id) {
                    throw new \Exception('Solo se puede eliminar el ultimo pago');
                }

                $invoices = $model->invoices;
                // Facturas
                foreach ($invoices as $key => $invoice) {
                    $invoice->update([
                        'transaction_id' => null,
                        'payment_id' => null,
                        'status' => Invoice::STATUS_DRAFT,
                        'payment_date' => null,
                        'notes' => 'Pago Eliminado por el usuario ' . auth()->user()->id . " el id del pago es: " . $model->id,
                        'pending_balance' => $invoice->total
                    ]);

                    $invoice->save();
                }
                $client->load('balance');
                $client->load('client_main_information');
                $balance = $client->balance;

                //ajustar el balance y fecha a como estaba antes
                $paymentClientMetadata = ClientPaymentMetadata::where('payment_id', $model->id)->first();
                if ($paymentClientMetadata) {
                    $balance->amount = $paymentClientMetadata->previous_balance;
                    $client->client_main_information->estado = $paymentClientMetadata->previous_status;
                    $client->fecha_pago = $paymentClientMetadata->previous_fecha_pago;
                    $client->fecha_corte = $paymentClientMetadata->previous_fecha_corte;
                    $balance->save();
                    $client->client_main_information->save();
                    $client->save();
                    $paymentClientMetadata->delete();
                }

                // Eliminar las transacciones
                $model->load('transactions');
                $transactions = $model->transactions;
                foreach ($transactions as $transaction) {
                    $transaction->delete();
                }

                // eliminar GeneralAccounting Incomes
                $generalAccountingIncomes = GeneralAccountingIncome::where('payment_id', $model->id)->where('client_id', $client->id)->get();
                foreach ($generalAccountingIncomes as $generalAccountingIncome) {
                    $generalAccountingIncome->delete();
                }

                // Eliminar el pago
                $model->delete();
                DB::commit();
                return redirect()->back()->with('message', 'Payment Eliminado Correctamente');
            });
        } catch (\Exception $e) {
            Log::info('Error al eliminar el pago: ' . $e->getMessage());
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar el pago: ' . $e->getMessage()], 500);
        }
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function getTotals($clientId)
    {
        $client = Client::where('id', $clientId)->first();
        $allPayments = MethodOfPayment::pluck('type', 'id')->toArray();

        $paymentsForClient = $client->payments()
            ->get()
            ->groupBy('payment_method_id');

        $payments = [];
        foreach ($paymentsForClient as $payment_method_id => $value) {
            $payments[$payment_method_id] = [
                'type' => $allPayments[$payment_method_id],
                'value' => $value->sum('amount')
            ];
        }
        return $payments;
    }

    public function getCostAllServiceActive($clientId)
    {
        return $this->clientRepository->getCostAllServiceActive($clientId);
    }
    public function getCostAllService($clientId)
    {
        return $this->clientRepository->getCostAllService($clientId);
    }

    public function getActiveServiceExpiration($clientId)
    {
        return $this->clientRepository->getActiveServiceExpiration($clientId);
    }

    public function getPrintPdf($payment_id)
    {
        $payment = Payment::find($payment_id);
        $configFinanceNotificationPayment = (new ConfigFinanceNotificationRepository())->getNotificationTypePayment();
        if ($configFinanceNotificationPayment->auto_send_notifications == true) {
            $clientRepository = new ClientRepository();
            $client = $clientRepository->getClientById($payment->paymentable_id);
            $clientRepository = new ClientRepository();
            $client = $clientRepository->getClientById($payment->paymentable_id);
            $documentTemplateService = new DocumentTemplateService();
            $documentTemplateRepository = new DocumentTemplateRepository();
            $dataClient = (new ContractClientService())->getDataClient($client);
            $html = $documentTemplateService->validateAndReplaceTemplate($documentTemplateRepository->getHtmlById($configFinanceNotificationPayment->email_template_id), $dataClient);
            if ($html['status'] == 'fail') {
                Log::error($html['keys']);
            } else {
                $html = (new ConfigFinanceNotificationService())->replaceDinamicData($html['html'], $payment);
                return $documentTemplateService->returnPath($html);
            }
        } else {
            $serviceActive = $this->clientRepository->getClientWithServiceActive($payment->paymentable_id);
            $services = [
                'bundle_service' => $serviceActive['bundle_service'],
                'internet_service' => $serviceActive['internet_service'],
                'voz_service' => $serviceActive['voz_service'],
                'custom_service' => $serviceActive['custom_service']
            ];

            $stringService = '';
            $array = [];
            foreach ($services as $service) {
                if ($service->count()) {
                    $array[] = $service[0];
                }
            }
            foreach ($array as $value) {
                $stringService .= $value->service_name . ' ,';
            }

            if ($payment->isModelClient()) {
                $client = Client::where('id', $payment->paymentable_id)->first();
                $data = [
                    'payment_id' => $payment_id,
                    'ticket_number' => $payment->receipt,
                    'full_name' => $client->client_main_information()->first()->getClientNameWithFathersNamesAttribute(),
                    'amount' => $payment->amount,
                    'services' => $stringService,
                    'payment_period' => $payment->payment_period,
                    'pay_up' => $this->getActiveServiceExpiration($payment->paymentable_id),
                    'billing_expiration' => $this->getActiveServiceExpiration($payment->paymentable_id),
                    'date' => $payment->date,
                    'client_id' => $payment->paymentable_id
                ];
            }

            if (isset($data)) {
                $pdf = FacadePdf::loadView('meganet.module.client.billing.payment.pdf', compact('data'));
                return $pdf->stream();
            } else {
                return redirect()->back();
            }
        }
    }
}
