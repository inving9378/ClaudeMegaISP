<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Models\ClientInvoiceCfdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pantalla admin standalone: adjuntar el XML+PDF de un CFDI ya timbrado
 * FUERA del sistema (no hay PAC integrado — ver
 * App\Services\Finance\Timbrado\TimbradoServiceInterface) a una factura de
 * client_invoices, para que el Portal Cliente lo muestre/descargue.
 *
 * NO timbra ni genera CFDI — solo guarda los archivos que el staff ya
 * obtuvo timbrando por fuera y los liga a la factura correcta.
 * Gateada por rol (mismo patrón que finanzas/extraccion-comprobante) para
 * no acoplar al catálogo de permisos por una pantalla de alcance acotado.
 */
class ClientInvoiceCfdiController extends Controller
{
    public function index()
    {
        return view('core-clientes::cfdi.adjuntar');
    }

    /**
     * GET /facturacion/cfdi/buscar?numero=...
     * Busca facturas por número o id exacto (máx. 10) para que el staff
     * confirme visualmente cliente/monto antes de adjuntar el CFDI.
     */
    public function buscar(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('numero', ''));

        if ($termino === '') {
            return response()->json(['data' => []]);
        }

        $facturas = DB::table('client_invoices as ci')
            ->join('clients as c', 'c.id', '=', 'ci.client_id')
            ->join('client_main_information as cmi', 'cmi.client_id', '=', 'c.id')
            ->where('ci.number', 'like', "%{$termino}%")
            ->orderByDesc('ci.id')
            ->limit(10)
            ->get([
                'ci.id', 'ci.number', 'ci.total', 'ci.estado', 'ci.document_date',
                'ci.client_id', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name',
            ]);

        $cfdiExistente = ClientInvoiceCfdi::whereIn('client_invoice_id', $facturas->pluck('id'))
            ->pluck('client_invoice_id')
            ->all();

        $data = $facturas->map(function ($f) use ($cfdiExistente) {
            return [
                'id'             => $f->id,
                'number'         => $f->number,
                'total'          => $f->total,
                'estado'         => $f->estado,
                'document_date'  => $f->document_date,
                'client_name'    => trim("{$f->name} {$f->father_last_name} {$f->mother_last_name}"),
                'tiene_cfdi'     => in_array($f->id, $cfdiExistente, true),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * POST /facturacion/cfdi/{clientInvoiceId}/adjuntar
     * Sube XML+PDF, intenta extraer el UUID fiscal del XML (best-effort,
     * nunca bloquea el guardado si no se puede parsear) y crea/actualiza
     * el registro (una factura = un CFDI activo, se sobreescribe al re-subir).
     */
    public function adjuntar(Request $request, int $clientInvoiceId): JsonResponse
    {
        $factura = DB::table('client_invoices')->where('id', $clientInvoiceId)->first();

        if (! $factura) {
            return response()->json(['error' => 'La factura no existe.'], 404);
        }

        $v = validator($request->all(), [
            'xml' => 'required|file|mimes:xml|max:2048',
            'pdf' => 'required|file|mimes:pdf|max:5120',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $xmlFile = $request->file('xml');
        $pdfFile = $request->file('pdf');

        $uuid = $this->extraerUuid($xmlFile->getRealPath());

        $dir     = "facturas/cfdi/{$clientInvoiceId}";
        $xmlPath = $xmlFile->store($dir, 'local');
        $pdfPath = $pdfFile->store($dir, 'local');

        $userId = auth()->id();

        $cfdi = ClientInvoiceCfdi::updateOrCreate(
            ['client_invoice_id' => $clientInvoiceId],
            [
                'uuid_fiscal'  => $uuid,
                'xml_path'     => $xmlPath,
                'pdf_path'     => $pdfPath,
                'timbrado_at'  => now(),
                'cancelado_at' => null,
                'updated_by'   => $userId,
                'created_by'   => DB::table('client_invoice_cfdi')
                    ->where('client_invoice_id', $clientInvoiceId)
                    ->value('created_by') ?? $userId,
            ]
        );

        return response()->json(['data' => $cfdi, 'message' => 'CFDI adjuntado correctamente.']);
    }

    /**
     * Best-effort: lee el UUID del TimbreFiscalDigital del XML CFDI 4.0.
     * Si el XML no trae complemento de timbre (o no es parseable), retorna
     * null sin bloquear el guardado — el staff puede capturar el UUID a
     * mano después si lo necesita.
     */
    private function extraerUuid(string $xmlRealPath): ?string
    {
        $prevErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_file($xmlRealPath);

            if ($xml === false) {
                return null;
            }

            // XPath, no navegación por propiedad: SimpleXMLElement::children()
            // colapsa el nodo cuando hay un solo hijo en ese namespace (el caso
            // real de TimbreFiscalDigital, único hijo de Complemento), y entonces
            // ->TimbreFiscalDigital busca un nieto que no existe → falso negativo.
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $nodos = $xml->xpath('//tfd:TimbreFiscalDigital');

            if (! $nodos) {
                return null;
            }

            $uuid = (string) ($nodos[0]['UUID'] ?? '');

            return $uuid !== '' ? $uuid : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prevErrors);
        }
    }
}
