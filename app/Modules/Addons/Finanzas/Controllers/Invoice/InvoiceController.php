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

            // Item #505: Enviar manda EXACTAMENTE el documento del ícono (Invoice::documentoVigente()).
            $documento = $model->documentoVigente();
            if ($documento === null) {
                return response()->json(['success' => false, 'message' => 'La factura está cancelada: no hay documento que enviar.'], 422);
            }
            if ($documento === Invoice::DOC_CFDI) {
                return response()->json(['success' => false, 'message' => 'Esta factura tiene CFDI timbrado; su envío («Tu CFDI {periodo} — Meganet») llega con la Fase 4.'], 409);
            }
            $pagada = $documento === Invoice::DOC_FACTURA;

            // Item #480: se manda la Prefactura (plantilla + provider) en PDF adjunto. Pendiente:
            // si la plantilla no se puede llenar, se cae al Blade FacturaProforma de antes (log).
            // Items #498/#505 (decisión de Irving): PAGADA → FACTURA; si la plantilla falla NO hay
            // fallback (el Blade viejo diría «Monto a pagar: 0.00»): se aborta sin enviar.
            $prefactura = $this->prefacturaParaCorreo($model);
            $adjuntos = [];
            if ($prefactura['status'] === 'ok') {
                $html = $prefactura['body'];
                $adjuntos[] = $prefactura['adjunto'];
                $asunto = $pagada
                    ? $this->asuntoCorreoFactura('Tu factura', $model, ' (pagada)')
                    : $this->asuntoCorreoFactura('Tu prefactura', $model);
            } elseif ($pagada) {
                Log::warning("[Prefactura] Enviar factura PAGADA #{$model->id}: no se pudo generar el PDF; NO se envía.", [
                    'faltan' => $prefactura['keys'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No se envió: la factura está pagada y su PDF no se pudo generar'
                        . ($prefactura['keys'] ? ' (faltan: ' . implode(', ', $prefactura['keys']) . ')' : '')
                        . '. No se usa el formato anterior porque mostraría «Monto a pagar: 0.00».',
                    'keys'    => $prefactura['keys'],
                ], 422);
            } else {
                Log::warning("[Prefactura] Enviar factura #{$model->id}: la Prefactura no se pudo generar, se usa FacturaProforma.", [
                    'faltan' => $prefactura['keys'],
                ]);
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
                // FAC-05: evidencia de los bytes exactos que van en el correo (va por cola → «encolado»;
                // el listener de MessageSent la marca «aceptado» cuando el worker lo manda).
                $emailProps['evidencias'] = $this->registrarEvidencias($model, $adjuntos, $pagada, $emailProps['to'] ?? null, $asunto);
            }
            Notification::route('mail', $emailProps['to'])
                ->notify(new StandardNotification($model, ['mail'], $emailProps));
            //marcar como enviada la factura proforma
            $model->markAsSent();
            $model->save();
            $logService = new LogService();
            $documento = $prefactura['status'] !== 'ok' ? '' : ($pagada ? ' (con la factura pagada en PDF)' : ' (con la Prefactura en PDF)');
            $logService->log($model->client, 'Cliente #' . $model->client_id . ' Se ha enviado la factura proforma ' . $model->id . $documento . ' por el usuario ' . auth()->user()->id . ' el dia ' . now()->format('Y-m-d'));

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


    /**
     * Ícono único de documento (item #505). /print/{id} se conserva por compatibilidad (Finanzas y
     * enlaces existentes) y devuelve el MISMO documento que /prefactura/{id}: el vigente según
     * Invoice::documentoVigente() — PREFACTURA (pendiente) o FACTURA sin valor fiscal (pagada).
     * Con ?validar=1 responde JSON sin generar el PDF (la pantalla valida antes de abrir la pestaña).
     */
    public function printInvoice(Request $request, $id)
    {
        return $this->servirDocumento($request, $id, false);
    }

    /**
     * Mismo documento que el ícono, por la ruta de «Prefactura actualizada» (#480, permiso
     * finanzas_prefactura_generar); además deja rastro en activity_log (log_name=prefactura).
     */
    public function prefactura(Request $request, $id)
    {
        return $this->servirDocumento($request, $id, true);
    }

    /**
     * Si la plantilla no se puede llenar: prefactura → Blade FacturaProformaPdf de antes (log);
     * factura pagada → error con las variables faltantes (el Blade viejo diría «Monto a pagar: 0.00»).
     * Cancelada → sin documento. CFDI → Fase 4 (#483; hoy hasCfdi() siempre es false).
     */
    private function servirDocumento(Request $request, $id, bool $registrar)
    {
        $validar = $request->boolean('validar');
        $model = Invoice::find($id);
        if (!$model) {
            return $this->fallaDocumento($validar, 'No se encontró la factura.', [], 404);
        }
        $model->load('client');

        $documento = $model->documentoVigente();
        if ($documento === null) {
            return $this->fallaDocumento($validar, 'La factura está cancelada: no tiene documento.', [], 422);
        }
        if ($documento === Invoice::DOC_CFDI) {
            return $this->fallaDocumento($validar, 'Esta factura tiene CFDI timbrado; su descarga (PDF y XML) llega con la Fase 4.', [], 409);
        }

        try {
            $servicio = app(PrefacturaPdfService::class);
            $r = $validar ? $servicio->html($model) : $servicio->generar($model);
        } catch (\Throwable $e) {
            Log::error("[Prefactura] Documento de la factura #{$model->id}: " . $e->getMessage());
            $r = ['status' => 'fail', 'keys' => []];
        }

        if ($r['status'] !== 'ok') {
            if ($documento === Invoice::DOC_FACTURA) {
                Log::warning("[Prefactura] Factura PAGADA #{$model->id}: no se pudo generar.", ['faltan' => $r['keys']]);

                return $this->fallaDocumento($validar, 'No se pudo generar la factura pagada: la plantilla «Prefactura.» pide variables que no se pudieron llenar.', $r['keys'], 422);
            }
            Log::warning("[Prefactura] Factura #{$model->id}: la plantilla no se pudo llenar, se usa FacturaProformaPdf.", ['faltan' => $r['keys']]);

            return $validar ? response()->json(['status' => 'ok', 'respaldo' => true]) : $this->imprimirBladeAnterior($model);
        }

        if ($validar) {
            return response()->json(['status' => 'ok', 'estado' => $r['estado']]);
        }

        if ($registrar) {
            activity('prefactura')
                ->causedBy(auth()->user())
                ->performedOn($model)
                ->event('prefactura_generada')
                ->withProperties([
                    'invoice_id'     => $model->id,
                    'invoice_number' => $model->number,
                    'client_id'      => $model->client_id,
                    'estado'         => $r['estado'],
                    'kb'             => round(strlen($r['pdf']) / 1024, 1),
                ])
                ->tap(function ($activity) use ($model) {
                    $activity->client_id = $model->client_id;
                })
                ->log("Prefactura actualizada de la factura {$model->number} ({$r['estado']})");
        }

        return response($r['pdf'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $r['filename'] . '"',
        ]);
    }

    private function fallaDocumento(bool $validar, string $mensaje, array $keys, int $codigo)
    {
        return $validar
            ? response()->json(['status' => 'fail', 'message' => $mensaje, 'keys' => $keys], $codigo)
            : response($this->paginaError($mensaje, $keys), $codigo)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** El PDF de antes (FacturaProformaPdf): solo como respaldo de facturas pendientes. */
    private function imprimirBladeAnterior(Invoice $model)
    {
        try {
            $dataClient = (new ContractClientService())->getDataClient($model->client);
            $companYInformationRepository = new CompanyInformationRepository();
            $companyInformation = $companYInformationRepository->getDataCompany();
            $invoice = $model;
            $items = $model->items;

            $pdf = Pdf::loadView('meganet.module.client.template.FacturaProformaPdf', compact('invoice', 'dataClient', 'companyInformation', 'items'));
            return $pdf->stream();
        } catch (\Exception $e) {
            Log::error('Error al imprimir factura: ' . $e->getMessage());
            return redirect()->back()->with('message', 'No se encontro la factura');
        }
    }

    private function paginaError(string $mensaje, array $keys): string
    {
        $lista = $keys ? '<ul>' . implode('', array_map(fn ($k) => '<li><code>' . e($k) . '</code></li>', $keys)) . '</ul>' : '';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>No se pudo generar</title></head>'
            . '<body style="font-family:Arial,sans-serif;max-width:640px;margin:40px auto;color:#1A2430">'
            . '<h2 style="color:#B45309">No se pudo generar el documento</h2><p>' . e($mensaje) . '</p>' . $lista . '</body></html>';
    }

    /** FAC-05: guarda cada PDF adjunto de «Enviar» como evidencia. @return int[] */
    private function registrarEvidencias(Invoice $invoice, array $adjuntos, bool $pagada, $destinatario, string $asunto): array
    {
        $servicio = app(\App\Services\Finance\Billing\EvidenciaDocumentoService::class);
        $ids = [];
        foreach ($adjuntos as $adjunto) {
            $e = $servicio->registrar(['data' => base64_decode($adjunto['data']), 'name' => $adjunto['name']], [
                'origen'       => \App\Models\BillingDocumentoEvidencia::ORIGEN_MANUAL,
                'documento'    => $pagada ? 'factura' : 'prefactura',
                'client_id'    => $invoice->client_id,
                'invoice_id'   => $invoice->id,
                'payment_id'   => $invoice->payment_id,
                'destinatario' => is_array($destinatario) ? implode(',', $destinatario) : $destinatario,
                'asunto'       => $asunto,
            ], \App\Models\BillingDocumentoEvidencia::ESTADO_ENCOLADO);
            if ($e) {
                $ids[] = $e->id;
            }
        }

        return $ids;
    }

    /**
     * «Tu prefactura noviembre-diciembre 2026 — Meganet» (item #486). Con el periodo en el asunto,
     * Gmail no junta en un solo hilo los correos de meses distintos.
     */
    private function asuntoCorreoFactura(string $prefijo, Invoice $invoice, string $sufijo = ''): string
    {
        return PrefacturaPdfService::asuntoCorreo($prefijo, [$invoice], $sufijo);
    }

    /**
     * Cuerpo corto + la Prefactura en PDF para el correo de «Enviar» (item #480, 2.5). En una
     * factura pagada, el texto es de factura pagada (items #498/#505). Si la plantilla no se pudo
     * llenar o algo falló devuelve status fail con las variables faltantes: el llamador decide
     * (pendiente → Blade viejo; pagada → no se envía).
     *
     * @return array{status: 'ok', body: string, adjunto: array{data: string, name: string, mime: string}}|array{status: 'fail', keys: string[]}
     */
    private function prefacturaParaCorreo(Invoice $invoice): array
    {
        try {
            $r = app(PrefacturaPdfService::class)->generar($invoice);
        } catch (\Throwable $e) {
            Log::warning("[Prefactura] Enviar factura #{$invoice->id}: error al generar el PDF: " . $e->getMessage());

            return ['status' => 'fail', 'keys' => []];
        }
        if ($r['status'] !== 'ok') {
            return ['status' => 'fail', 'keys' => $r['keys']];
        }

        $cmi = $invoice->client?->client_main_information;
        $nombre = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));
        $periodo = PrefacturaDataProvider::periodoLegible($invoice->period);
        $total = e(PrefacturaDataProvider::dinero((float) $invoice->total));
        $saludo = '<p>Hola' . ($nombre !== '' ? ' ' . e($nombre) : '') . ',</p>';
        $delPeriodo = $periodo !== '' ? ' del periodo ' . e($periodo) : '';

        if ($r['estado'] === PrefacturaDataProvider::ESTADO_PAGADO) {
            $pagadoEl = PrefacturaDataProvider::fecha($invoice->payment_date);
            $body = $saludo
                . '<p>Te compartimos tu factura <b>' . e($invoice->number) . '</b>' . $delPeriodo
                . ' por <b>' . $total . ' MXN</b>' . ($pagadoEl ? ', pagada el <b>' . e($pagadoEl->format('d/m/Y')) . '</b>' : '') . '.</p>'
                . '<p>La encontrarás adjunta en PDF. ¡Gracias por tu pago!</p>';
        } else {
            $vence = PrefacturaDataProvider::fecha($invoice->due_date);
            $body = $saludo
                . '<p>Te compartimos la prefactura <b>' . e($invoice->number) . '</b>' . $delPeriodo
                . ' por <b>' . $total . ' MXN</b>'
                . ($vence ? ', con fecha límite de pago el <b>' . e($vence->format('d/m/Y')) . '</b>.' : '.')
                . '</p><p>La encontrarás adjunta en PDF, con los datos para pagar y tu referencia.</p>';
        }

        return [
            'status'  => 'ok',
            'body'    => $body . '<p style="color:#6E7C8C;font-size:12px">Documento informativo sin valor fiscal.</p>',
            'adjunto' => ['data' => base64_encode($r['pdf']), 'name' => $r['filename'], 'mime' => 'application/pdf'],
        ];
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
