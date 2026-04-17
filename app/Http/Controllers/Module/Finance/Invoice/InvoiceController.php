<?php

namespace App\Http\Controllers\Module\Finance\Invoice;

use App\Dto\EmailDto;
use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\finance\InvoiceDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\CompanyInformationRepository;
use App\Http\Repository\ConfigFinanceNotificationRepository;
use App\Http\Requests\module\finance\invoice\InvoiceCreateRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\StandardNotification;
use App\Services\ClientService\ContractClientService;
use App\Services\EmailConfigService;
use App\Services\Finance\Invoice\AvailablePeriodsService;
use App\Services\Finance\Invoice\InvoiceService;
use App\Services\LogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class InvoiceController extends CrudModalController
{
    public function __construct(InvoiceDatatableHelper $helper)
    {
        parent::__construct($helper, new InvoiceCreateRequest());
        $this->data['model'] = 'App\Models\Invoice';
        $this->data['url'] = 'meganet.module.finance.invoice_new';
        $this->data['module'] = 'Invoice';
    }


    public function createForClient(Request $request, $clientId)
    {
        try {
            $client = Client::find($clientId);
            $fecha = $request->fecha_corte;
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontro el cliente',
                ], 500);
            }
            $period = Carbon::parse($fecha)->format('Y-m');
            $fechaCorte = Carbon::parse($fecha)->format('Y-m-d');
            $invoice = Invoice::where('client_id', $clientId)->where('period', $period)->first();
            if ($invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente ya tiene una factura para ese periodo',
                ], 500);
            }
            $invoiceService = new InvoiceService();
            $clientRepository = new ClientRepository();
            $montos = $clientRepository->calculateAmounts($client);
            $data = [
                'number' => $invoiceService->generateInvoiceNumber(),
                'client_id' => $client->id,
                'due_date' => $fechaCorte,
                'subtotal' => $montos['subtotal'],
                'tax' => $montos['tax'],
                'total' => $montos['total'],
                'pending_balance' => $montos['total'],
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_PROFORMA,
                'period' => $period,
                'notes' => "Proforma creada manualmente correspondiente al período {$period} por el usuario " . auth()->user()->id
            ];

            $model = $invoiceService->createProformaInvoice($data);

            return response()->json([
                'success' => true,
                'message' => 'Factura creada',
                'id' => $model->id,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function sendInvoice($id)
    {
        try {
            $model = $this->data['model']::find($id);
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontro la factura',
                ], 500);
            }
            $model->load('client');
            $items = $model->items;
            $dataClient = (new ContractClientService())->getDataClient($model->client);
            $companYInformationRepository = new CompanyInformationRepository();
            $companyInformation = $companYInformationRepository->getDataCompany();
            $html = view('meganet.module.client.template.FacturaProforma', [
                'invoice' => $model,
                'dataClient' => $dataClient,
                'companyInformation' => $companyInformation,
                'items' => $items
            ])->toHtml();

            $config = (new ConfigFinanceNotificationRepository())->getNotificationType('proforma_invoice');

            $email = new EmailDto();
            $email->subject = 'Factura Proforma';
            $email->html = $html;
            $email->recipient_email =  $model->client->client_main_information->email;
            $email->client_id = $model->client_id;
            if ($config) {
                $email->cc_email = $config->email_bcc;
            }

            $emailService = new EmailConfigService();
            $emailProps = $emailService->getEmailProps($email);
            Notification::route('mail', $emailProps['to'])
                ->notify(new StandardNotification($model, ['mail'], $emailProps));
            //marcar como enviada la factura proforma
            $model->markAsSent();
            $model->save();
            $logService = new LogService();
            $logService->log($model->client, 'Cliente #' . $model->client_id . ' Se ha enviado la factura proforma ' . $model->id . ' por el usuario ' . auth()->user()->id . ' el dia ' . now()->format('Y-m-d'));

            return response()->json([
                'success' => true,
                'message' => 'Los la factura se ha enviado con exito.',
                'model' => $model
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al enviar factura: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud. Se ha notificado al administrador.',
            ], 500);
        }
    }


    public function printInvoice($id)
    {
        try {
            $model = $this->data['model']::find($id);
            if (!$model) {
                return redirect()->back()->with('message', 'No se encontro la factura');
            }
            $model->load('client');
            $dataClient = (new ContractClientService())->getDataClient($model->client);
            $companYInformationRepository = new CompanyInformationRepository();
            $companyInformation = $companYInformationRepository->getDataCompany();
            $invoice = $model;
            $items = $model->items;

            $pdf = Pdf::loadView('meganet.module.client.template.FacturaProformaPdf', compact('invoice', 'dataClient', 'companyInformation', 'items'));
            return $pdf->stream();
        } catch (\Exception $e) {
            Log::error('Error al enviar factura: ' . $e->getMessage());
            return redirect()->back()->with('message', 'No se encontro la factura');
        }
    }

    public function markAsPaid(Request $request, $id)
    {
        try {
            $model = $this->data['model']::find($id);
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontro la factura',
                ], 500);
            }
            $payment = Payment::find($request->payment_id);
            if (!$payment){
                return response()->json([
                    'success' => false,
                    'message' => 'No existe un pago con este id',
                ], 500);
            }

            $model->markAsPaid();
            $model->payment_id = $payment->id;
            $model->payment_date = $payment->date;
            $logService = new LogService();
            $logService->log($model->client, 'Cliente #' . $model->client_id . ' Se ha marcado la factura ' . $model->id . ' como pagada el dia ' . now()->format('Y-m-d') . ' por el usuario ' . auth()->user()->id . ' desde la tabla');
            $model->save();
            return response()->json([
                'success' => true,
                'message' => 'La factura se ha marcado como pagada con exito.',
                'model' => $model
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al enviar factura: ' . $e->getMessage());
            return redirect()->back()->with('message', 'No se encontro la factura');
        }
    }

    public function getPendingByClient($id)
    {
        try {
            $invoices = $this->data['model']::where('client_id', $id)->notPaid()->orderBy('due_date', 'asc')->limit(1)->get();
            return response()->json([
                'success' => true,
                'message' => 'La factura se ha marcado como pagada con exito.',
                'invoices' => $invoices
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al enviar factura: ' . $e->getMessage());
            return redirect()->back()->with('message', 'No se encontro la factura');
        }
    }

    public function editPeriod(Request $request, $clientId)
    {
        try {
            $client = Client::find($clientId);
            $periodoActual = $request->periodo_actual;
            $periodoNuevo = $request->periodo_nuevo;
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontro el cliente',
                ], 500);
            }

            $invoice = Invoice::where('client_id', $clientId)->where('period', $periodoNuevo)->first();
            if ($invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente ya tiene una factura para ese periodo',
                ], 500);
            }

            $modelo = Invoice::where('client_id', $clientId)->where('period', $periodoActual)->first();
            if (!$modelo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontro la factura',
                ], 500);
            }

            DB::beginTransaction();
            $payment = $modelo->payment;
            if ($payment) {
                $payment = $payment->update([
                    'payment_period' => $periodoNuevo,
                ]);
            }

            $modelo = $modelo->update([
                'period' => $periodoNuevo,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'La factura se ha actualizado con exito.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al enviar factura: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        return  $this->data['model']::findOrFail($id)->delete();
    }


    public function getAvailablePeriodsByClient(
        int $id,
        AvailablePeriodsService $service
    ): JsonResponse {
        return response()->json(
            $service->getAvailablePeriodsByClient($id)
        );
    }
}
