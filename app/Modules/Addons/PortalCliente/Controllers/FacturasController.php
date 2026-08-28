<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientInvoiceCfdi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FacturasController extends Controller
{
    /**
     * Lista de facturas del cliente autenticado.
     * Usa STR_TO_DATE para ordenar fechas VARCHAR DD/MM/YYYY correctamente.
     * Condición de aislamiento: client_id = cliente autenticado (satisface condición de paro #9).
     */
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $facturas = DB::table('client_invoices')
            ->where('client_id', $clientId)
            ->orderByRaw("COALESCE(STR_TO_DATE(document_date, '%d/%m/%Y'), '1900-01-01') DESC")
            ->get([
                'id', 'number', 'total', 'estado',
                'payment_date', 'document_date', 'is_proforma',
            ]);

        return view('addon-portal-cliente::facturas', compact('cmi', 'facturas'));
    }

    /**
     * Detalle de una factura.
     * Verifica que client_id = cliente autenticado antes de devolver datos.
     */
    public function show(int $id)
    {
        $clientId = Auth::guard('cliente')->user()->client_id;

        $factura = DB::table('client_invoices')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->first();

        if (! $factura) {
            abort(404);
        }

        $cfdi = ClientInvoiceCfdi::where('client_invoice_id', $id)->whereNull('cancelado_at')->first();

        return view('addon-portal-cliente::factura_show', compact('factura', 'cfdi'));
    }

    /**
     * Descarga del XML del CFDI ya timbrado. Verifica que la factura
     * pertenezca al cliente autenticado antes de servir el archivo
     * (condición de aislamiento / anti-IDOR #9).
     */
    public function downloadCfdiXml(int $id)
    {
        return $this->downloadCfdi($id, 'xml');
    }

    /**
     * Descarga del PDF del CFDI ya timbrado. Misma verificación de
     * propiedad que downloadCfdiXml.
     */
    public function downloadCfdiPdf(int $id)
    {
        return $this->downloadCfdi($id, 'pdf');
    }

    private function downloadCfdi(int $id, string $format)
    {
        $clientId = Auth::guard('cliente')->user()->client_id;

        $factura = DB::table('client_invoices')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->first();

        if (! $factura) {
            abort(404);
        }

        $cfdi = ClientInvoiceCfdi::where('client_invoice_id', $id)->whereNull('cancelado_at')->first();

        if (! $cfdi) {
            abort(404);
        }

        $path = $format === 'xml' ? $cfdi->xml_path : $cfdi->pdf_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $mime     = $format === 'xml' ? 'application/xml' : 'application/pdf';
        $filename = "cfdi_factura_{$factura->number}.{$format}";

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('local')->get($path);
        }, $filename, ['Content-Type' => $mime]);
    }
}
