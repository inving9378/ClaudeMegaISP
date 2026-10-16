<?php

namespace App\Modules\Addons\Finanzas\Controllers\Invoice;

use App\Dto\EmailDto;
use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\finance\InvoiceDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Modules\Core\Configuracion\Repositories\CompanyInformationRepository;
use App\Modules\Core\Configuracion\Repositories\ConfigFinanceNotificationRepository;
use App\Modules\Core\Security\Traits\ChecksActionPermission;
use App\Http\Requests\module\finance\invoice\InvoiceCreateRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\StandardNotification;
use App\Services\ClientService\ContractClientService;
use App\Modules\Core\Configuracion\Services\EmailConfigService;
use App\Services\Finance\Invoice\AvailablePeriodsService;
use App\Services\Finance\Invoice\InvoiceService;
use App\Services\Finance\Billing\PrefacturaDataProvider;
use App\Services\Finance\Billing\PrefacturaPdfService;
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
    use ChecksActionPermission;

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
            $this->verificarPermisoAccion('finanzas.invoice.crear', 'invoice.createForClient');

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

            // Item #480: se manda la Prefactura (plantilla + provider) en PDF adjunto. Si la
            // plantilla no se puede llenar, se cae al Blade FacturaProforma de antes (queda en log).
            $prefactura = $this->prefacturaParaCorreo($model);
            $adjuntos = [];
            if ($prefactura) {
                $html = $prefactura['body'];
                $adjuntos[] = $prefactura['adjunto'];
                $asunto = $this->asuntoCorreoFactura('Tu prefactura', $model);
            } else {
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
                $asunto = $this->asuntoCorreoFactura('Tu factura proforma', $model);
            }

            $config = (new ConfigFinanceNotificationRepository())->getNotificationType('proforma_invoice');

            $email = new EmailDto();
            $email->subject = $asunto;
            $email->html = $html;
            $email->recipient_email =  $model->client->client_main_information->email;
            $email->client_id = $model->client_id;
            if ($config) {
                $email->cc_email = $config->email_bcc;
            }

            $emailService = new EmailConfigService();
            $emailProps = $emailService->getEmailProps($email);
            if ($adjuntos) {
                $emailProps['attachments'] = $adjuntos;
            }
            Notification::route('mail', $emailProps['to'])
                ->notify(new StandardNotification($model, ['mail'], $emailProps));
            //marcar como enviada la factura proforma
            $model->markAsSent();
            $model->save();
            $logService = new LogService();
            $logService->log($model->client, 'Cliente #' . $model->client_id . ' Se ha enviado la factura proforma ' . $model->id . ($prefactura ? ' (con la Prefactura en PDF)' : '') . ' por el usuario ' . auth()->user()->id . ' el dia ' . now()->format('Y-m-d'));

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

    /**
     * «Tu prefactura noviembre-diciembre 2026 — Meganet» (item #486). Con el periodo en el asunto,
     * Gmail no junta en un solo hilo los correos de meses distintos.
     */
    private function asuntoCorreoFactura(string $prefijo, Invoice $invoice): string
    {
        $periodo = PrefacturaDataProvider::periodoLegible($invoice->period);

        return $prefijo . ($periodo !== '' ? ' ' . $periodo : '') . ' — Meganet';
    }

    /**
     * Cuerpo corto + la Prefactura en PDF para el correo de «Enviar» (item #480, 2.5). Devuelve
     * null si la plantilla no se pudo llenar o algo falló: el llamador cae al Blade viejo.
     *
     * @return array{body: string, adjunto: array{data: string, name: string, mime: string}}|null
     */
    private function prefacturaParaCorreo(Invoice $invoice): ?array
    {
        try {
            $r = app(PrefacturaPdfService::class)->generar($invoice);
        } catch (\Throwable $e) {
            Log::warning("[Prefactura] Enviar factura #{$invoice->id}: error al generar el PDF, se usa FacturaProforma: " . $e->getMessage());

            return null;
        }
        if ($r['status'] !== 'ok') {
            Log::warning("[Prefactura] Enviar factura #{$invoice->id}: la plantilla no se pudo llenar, se usa FacturaProforma.", [
                'faltan' => $r['keys'],
            ]);

            return null;
        }

        $cmi = $invoice->client?->client_main_information;
        $nombre = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));
        $periodo = PrefacturaDataProvider::periodoLegible($invoice->period);
        $vence = PrefacturaDataProvider::fecha($invoice->due_date);
        $pagada = $r['estado'] === PrefacturaDataProvider::ESTADO_PAGADO;

        $body = '<p>Hola' . ($nombre !== '' ? ' ' . e($nombre) : '') . ',</p>'
            . '<p>Te compartimos la prefactura <b>' . e($invoice->number) . '</b>'
            . ($periodo !== '' ? ' del periodo ' . e($periodo) : '')
            . ' por <b>' . e(PrefacturaDataProvider::dinero((float) $invoice->total)) . ' MXN</b>'
            . ($pagada ? ', que ya se encuentra <b>pagada</b>.' : ($vence ? ', con fecha límite de pago el <b>' . e($vence->format('d/m/Y')) . '</b>.' : '.'))
            . '</p><p>La encontrarás adjunta en PDF, con los datos para pagar y tu referencia.</p>'
            . '<p style="color:#6E7C8C;font-size:12px">Documento informativo sin valor fiscal.</p>';

        return [
            'body'    => $body,
            'adjunto' => ['data' => base64_encode($r['pdf']), 'name' => $r['filename'], 'mime' => 'application/pdf'],
        ];
    }

    /**
     * «Prefactura actualizada» (item #480): PDF de la plantilla «Prefactura.» con los datos de
     * HOY de esta factura (pendiente o pagada). Se genera en memoria: no reemplaza ni borra el
     * PDF original, no toca billing_notifications ni la pausa de envíos. Documento informativo
     * sin valor fiscal. Permiso de ruta: finanzas_prefactura_generar.
     *
     * Con ?validar=1 solo comprueba que la plantilla se pueda llenar (JSON, sin PDF ni bitácora):
     * la pantalla lo usa antes de abrir la pestaña, para mostrar las variables faltantes en un
     * aviso en vez de una pestaña con JSON.
     */
    public function prefactura(Request $request, $id)
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['status' => 'fail', 'message' => 'No se encontró la factura.', 'keys' => []], 404);
        }
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return response()->json(['status' => 'fail', 'message' => 'La factura está cancelada: no se genera prefactura.', 'keys' => []], 422);
        }

        try {
            $servicio = app(PrefacturaPdfService::class);
            $r = $request->boolean('validar') ? $servicio->html($invoice) : $servicio->generar($invoice);
        } catch (\Throwable $e) {
            Log::error("[Prefactura] Error generando la prefactura de la factura #{$invoice->id}: " . $e->getMessage());

            return response()->json(['status' => 'fail', 'message' => 'Ocurrió un error al generar la prefactura.', 'keys' => []], 500);
        }

        if ($r['status'] !== 'ok') {
            return response()->json([
                'status'  => 'fail',
                'message' => 'La plantilla «Prefactura.» pide variables que no se pudieron llenar:',
                'keys'    => $r['keys'],
            ], 422);
        }

        if ($request->boolean('validar')) {
            return response()->json(['status' => 'ok', 'estado' => $r['estado']]);
        }

        activity('prefactura')
            ->causedBy(auth()->user())
            ->performedOn($invoice)
            ->event('prefactura_generada')
            ->withProperties([
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->number,
                'client_id'      => $invoice->client_id,
                'estado'         => $r['estado'],
                'kb'             => round(strlen($r['pdf']) / 1024, 1),
            ])
            ->tap(function ($activity) use ($invoice) {
                $activity->client_id = $invoice->client_id;
            })
            ->log("Prefactura actualizada de la factura {$invoice->number} ({$r['estado']})");

        return response($r['pdf'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $r['filename'] . '"',
        ]);
    }

    public function markAsPaid(Request $request, $id)
    {
        try {
            $this->verificarPermisoAccion('finanzas.invoice.marcar_pagada', 'invoice.markAsPaid');

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
        $this->verificarPermisoAccion('finanzas.invoice.cancelar', 'invoice.destroy');

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
