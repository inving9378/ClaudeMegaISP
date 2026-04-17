<?php

namespace App\Http\Controllers\Module\Client;

use App\Models\ClientInvoice;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Http\HelpersModule\module\client\ClientInvoiceDatatableHelper;
use App\Http\Requests\module\client\ClientInvoiseRequest;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\CompanyInformationRepository;
use App\Http\Repository\ConfigFinanceNotificationRepository;
use App\Http\Repository\DocumentTemplateRepository;
use App\Notifications\StandardNotification;
use App\Services\ClientService\ClientService;
use App\Services\ClientService\ContractClientService;
use App\Services\ConfigFinanceNotificationService;
use App\Services\DocumentTemplateService;
use App\Services\FormatDateService;
use App\Services\IvaInformationService;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ClientInvoiceController extends Controller
{

    private $helper;

    public function __construct(ClientInvoiceDatatableHelper $helper)
    {
        $model = 'ClientInvoice';
        $this->data['url'] = 'meganet.module.' . Str::lower($model);
        $this->data['module'] = $model;
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'clientInvoice';
        $this->helper = $helper;
    }

    public function store(ClientInvoiseRequest $request, $id)
    {
        if ($request->import) {
            $this->importDataToTable($request);
        }
        //        return Client::find($id)->clientCreateInvoise($request);
    }

    public function importDataToTable($request)
    {
        $input = [];

        $input = [
            'client_id' => $request->client_id,
            'number' => $request->number,
            'total' => $request->total,
            'estado' => $request->estado,
            'last_update' => $request->last_update,
            'pay_up' => $request->pay_up,
            'use_of_transactions' => $this->procesaValoresTrueOFalse($request->use_of_transactions),
            'payment' => $request->payment,
            'is_sent' => $this->procesaValoresTrueOFalse($request->is_sent),
            'delete_transactions' => $this->procesaValoresTrueOFalse($request->delete_transactions),
            'added_by' => '0',
            'type' => $request->type,
            'payment_date' => $request->payment_date,
            'document_date' => $request->document_date,
            'is_proforma' => $this->procesaValoresTrueOFalse($request->is_proforma),
            'created_at' => $request->created_at
        ];
        if ($request->id_old) {
            $input['id'] = $request->id_old;
        }
        DB::table('client_invoices')->insert($input);
    }

    public function procesaValoresTrueOFalse($value)
    {
        $valoresAdmitidosPositivos = ['si', 'yes', 'true', 1];
        $valoresAdmitidosNegativos = ['no', 'not', 'false', 0];

        if (in_array(strtolower($value), $valoresAdmitidosPositivos)) {
            return 1;
        }
        return 0;
    }

    public function destroy($id)
    {
        $this->data['model']::where('id', $id)->first()->delete();
        return redirect()->back()->with('message', 'Factura Eliminada Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }

    public function getPrintPdf($invoice_id)
    {
        $client_invoice = ClientInvoice::findOrFail($invoice_id);
        $configFinanceNotificationPayment = (new ConfigFinanceNotificationRepository())->getNotificationTypeInvoice();
        if ($configFinanceNotificationPayment->auto_send_notifications == true && app()->environment('production')) {
            try {
                $invoice_id = $client_invoice->id;
                $client = Client::whereHas('client_invoices', function ($query) use ($invoice_id) {
                    $query->where('id', $invoice_id);
                })->first();
                $documentTemplateService = new DocumentTemplateService();
                $documentTemplateRepository = new DocumentTemplateRepository();
                $dataClient = (new ContractClientService())->getDataClient($client);
                $html = $documentTemplateService->validateAndReplaceTemplate($documentTemplateRepository->getHtmlById($configFinanceNotificationPayment->email_template_id), $dataClient);
                if ($html['status'] == 'fail') {
                    Log::error($html['keys']);
                } else {
                    $html = (new ConfigFinanceNotificationService())->replaceDinamicDataInvoice($html['html'], $client_invoice, $client);
                    return $documentTemplateService->returnPath($html);
                }
            } catch (\Exception $e) {
                Log::error('error al enviar notificacion => ' . $e->getMessage());
            }
        } else {
            $client_invoice_type = $client_invoice->type;
            //TODO Revisar
            $data = $this->invoice_services($invoice_id);
            if ($client_invoice_type == ClientInvoice::TYPE_INVOICE_SERVICES) {
                $data = $this->invoice_services($invoice_id);
            }

            if ($client_invoice_type == ClientInvoice::TYPE_INVOICE_SURCHARGE_DEFAULTER) {
                $data = $this->invoice_services_defaulter($client_invoice);
            }

            if (isset($data)) {
                $pdf = FacadePdf::loadView('meganet.module.client.billing.invoice.pdf', compact('data'));
                return $pdf->stream();
            } else {
                return redirect()->back();
            }
        }
    }

    public function invoice_services($invoice_id)
    {
        $client = Client::whereHas('client_invoices', function ($query) use ($invoice_id) {
            $query->where('id', $invoice_id);
        })->first();

        if ($client) {
            $clientWithServices = (new ClientRepository())->getServicesForClient($client->id);
            $client_services = [];

            $i = 0;
            $subTotal = 0;
            $ivaSum = 0;
            $services = ComunConstantsController::ALL_CLIENT_SERVICE;

            foreach ($services as $service) {
                foreach ($clientWithServices->$service as $clientService) {
                    $informationIva = $this->getIvaInformation($clientService->getTax(), $clientService->price_service);
                    $iva = $informationIva['iva'];
                    $client_services[] = [
                        'number' => $i + 1,
                        'service_name' => $clientService->service_name,
                        'iva_porcent' => $clientService->getTax(),
                        'iva' => $clientService->serviceHasIva() ? $iva : 0,
                        'monto' => $clientService->serviceHasIva() ? $informationIva['monto'] : $clientService->price_service,
                    ];

                    //$ivaSum = $ivaSum + !$clientService->serviceHasIva() ? $iva : 0;
                    $ivaSum += $clientService->serviceHasIva() ? $iva : 0;
                    $subTotal += $informationIva['monto'];
                }
            }



            $debit = $client['balance']['amount'];


            $infoCompanyRepository = new CompanyInformationRepository();
            $dataCompany = $infoCompanyRepository->getDataCompany();
            $period = (new ClientService($client))->getPaymentPeriod(0);

            $invoice = ClientInvoice::find($invoice_id);

            $array = [
                'client_id' => $client['id'],
                'client_name_with_fathers_names' => $client['client_main_information']['client_name_with_fathers_names'] ?? '',
                'street' => $client['client_main_information']['street'] ?? '',
                'external_number' => $client['client_main_information']['external_number'] ?? '',
                'internal_number' => $client['client_main_information']['internal_number'] ?? '',
                'state' => $client['client_main_information']['state']['name'] ?? '',
                'municipality' => $client['client_main_information']['municipality']['name'] ?? '',
                'colony' => $client['client_main_information']['colony']['name'] ?? '',
                'zip' => $client['client_main_information']['zip'] ?? '',
                'number' => $client['client_invoices'][0]['number'] ?? '',
                'created_at' => (new FormatDateService($invoice->created_at))->formatDate() ?? '',
                'payment' => $client['client_invoices'][0]['payment'],
                'debit' => $debit ?? '',
                'sub_total' => $subTotal ?? '',
                'total' => $subTotal + $ivaSum ?? '',
                'total_iva' => $ivaSum ?? '',
                'pay_up' => (new FormatDateService($invoice->pay_up))->formatDateWithTime() ?? '',
                'client_services' => $client_services ?? '',
                'estado' => $client['client_invoices'][0]['estado'] ?? '',
                'note' => $client['client_invoices'][0]['note'] ?? '',
                'invoice_id' => $client['client_invoices'][0]['id'] ?? '',
                'periodo' => '-'
            ];
            return array_merge($dataCompany, $array);
        }
        return [];
    }

    public function invoice_services_defaulter($client_invoice)
    {
        $client = $client_invoice->client()->first();
        if ($client) {
            $client_services[] = [
                'number' => 1,
                'service_name' => 'Recargo',
                'iva_porcent' => '0',
                'iva' => '0',
                'monto' => '99.0',
            ];

            return  [
                'client_name_with_fathers_names' => $client->client_main_information()->first()->client_name_with_fathers_names,
                'street' => $client->client_main_information()->first()->street,
                'state' => $client->client_main_information()->first()->getStateName(),
                'municipality' => $client->client_main_information()->first()->getMunicipalityName(),
                'colony' => $client->client_main_information()->first()->getColonyName(),
                'zip' => $client->client_main_information()->first()->zip,
                'number' => $client_invoice->number,
                'created_at' => Carbon::parse($client_invoice->created_at)->toDateString(),
                'payment' => $client_invoice->payment,
                'debit' => '99.0',
                'sub_total' => '99.0',
                'total' => '99.0',
                'total_iva' => '0',
                'pay_up' => '',
                'client_services' => $client_services,
                'estado' => $client_invoice->estado,
                'note' => $client_invoice->note,
            ];
        }
    }

    public function hidden($value)
    {
        return $value ? 'visible' : 'hidden';
    }

    public function createManualClientInvoice($id)
    {
        return $id;
    }


    public function getIvaInformation($tasa, $total)
    {
        return (new IvaInformationService($tasa, $total))->getIvaInformation();
    }
}
