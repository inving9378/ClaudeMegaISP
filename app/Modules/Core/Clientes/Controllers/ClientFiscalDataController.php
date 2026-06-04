<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Models\ClientFiscalData;
use App\Services\Finance\Timbrado\TimbradoServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClientFiscalDataController extends Controller
{
    private TimbradoServiceInterface $timbradoService;

    public function __construct(TimbradoServiceInterface $timbradoService)
    {
        $this->timbradoService = $timbradoService;
    }

    /**
     * GET /api/clientes/{clientId}/datos-fiscales
     * Devuelve los datos fiscales del cliente más los catálogos SAT y el estado del PAC.
     */
    public function show(int $clientId): JsonResponse
    {
        $this->authorize('facturacion.ver');

        $data = ClientFiscalData::where('client_id', $clientId)->first();

        return response()->json([
            'fiscal_data'       => $data,
            'catalogos'         => [
                'regimenes_fiscales' => ClientFiscalData::catalogoRegimenFiscal(),
                'usos_cfdi'          => ClientFiscalData::catalogoUsoCFDI(),
            ],
            'pac_disponible'    => $this->timbradoService->isDisponible(),
            'pac_proveedor'     => $this->timbradoService->nombreProveedor(),
        ]);
    }

    /**
     * POST /api/clientes/{clientId}/datos-fiscales
     * Crea o actualiza los datos fiscales. Valida RFC y CP.
     */
    public function upsert(Request $request, int $clientId): JsonResponse
    {
        $this->authorize('facturacion.fiscal.editar');

        $v = Validator::make($request->all(), [
            'facturacion_fiscal'    => 'required|boolean',
            'razon_social'          => 'required_if:facturacion_fiscal,true|nullable|string|max:250',
            'rfc'                   => 'required_if:facturacion_fiscal,true|nullable|string|max:13',
            'codigo_postal_fiscal'  => 'required_if:facturacion_fiscal,true|nullable|digits:5',
            'regimen_fiscal'        => ['required_if:facturacion_fiscal,true', 'nullable',
                                        'in:' . implode(',', array_keys(ClientFiscalData::catalogoRegimenFiscal()))],
            'uso_cfdi'              => ['required_if:facturacion_fiscal,true', 'nullable',
                                        'in:' . implode(',', array_keys(ClientFiscalData::catalogoUsoCFDI()))],
            'correo_fiscal'         => 'nullable|email|max:200',
            'constancia'            => 'nullable|file|mimes:pdf|max:5120',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        // Validación RFC con regex SAT
        $rfc = $request->input('rfc');
        if ($rfc && ! ClientFiscalData::validarRfc($rfc)) {
            return response()->json(['errors' => ['rfc' => ['El RFC no tiene un formato válido.']]], 422);
        }

        $cp = $request->input('codigo_postal_fiscal');
        if ($cp && ! ClientFiscalData::validarCodigoPostal($cp)) {
            return response()->json(['errors' => ['codigo_postal_fiscal' => ['El código postal debe ser de 5 dígitos.']]], 422);
        }

        $userId = auth()->id() ?? 0;

        $fiscal = ClientFiscalData::firstOrNew(['client_id' => $clientId]);
        $fiscal->fill([
            'client_id'             => $clientId,
            'facturacion_fiscal'    => $request->boolean('facturacion_fiscal'),
            'razon_social'          => $request->input('razon_social'),
            'rfc'                   => $rfc ? strtoupper(trim($rfc)) : null,
            'codigo_postal_fiscal'  => $cp,
            'regimen_fiscal'        => $request->input('regimen_fiscal'),
            'uso_cfdi'              => $request->input('uso_cfdi'),
            'correo_fiscal'         => $request->input('correo_fiscal'),
            'updated_by'            => $userId,
        ]);

        if (! $fiscal->exists) {
            $fiscal->created_by = $userId;
        }

        // Constancia fiscal (PDF)
        if ($request->hasFile('constancia')) {
            $path = $request->file('constancia')->store(
                "clients/{$clientId}/fiscal",
                'local'
            );
            $fiscal->constancia_path = $path;
        }

        $fiscal->save();

        return response()->json(['data' => $fiscal, 'message' => 'Datos fiscales guardados.']);
    }

    /**
     * GET /api/clientes/{clientId}/datos-fiscales/constancia
     * Descarga la constancia de situación fiscal.
     */
    public function downloadConstancia(int $clientId): mixed
    {
        $this->authorize('facturacion.fiscal.editar');

        $fiscal = ClientFiscalData::where('client_id', $clientId)->firstOrFail();

        if (! $fiscal->constancia_path || ! Storage::disk('local')->exists($fiscal->constancia_path)) {
            return response()->json(['error' => 'Constancia no disponible.'], 404);
        }

        return response()->streamDownload(function () use ($fiscal) {
            echo Storage::disk('local')->get($fiscal->constancia_path);
        }, "constancia_cliente_{$clientId}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * POST /api/clientes/{clientId}/timbrar
     * Botón "Generar factura (timbrar)" — deshabilitado hasta que haya PAC.
     */
    public function timbrar(int $clientId, Request $request): JsonResponse
    {
        $this->authorize('facturacion.fiscal.timbrar');

        if (! $this->timbradoService->isDisponible()) {
            return response()->json([
                'error'   => 'PAC no configurado.',
                'message' => $this->timbradoService->nombreProveedor() .
                    ' — Integra un proveedor de timbrado antes de emitir facturas fiscales.',
            ], 503);
        }

        // Cuando haya PAC real: validar payment_id, cargar fiscal_data y llamar emitirFactura().
        return response()->json(['error' => 'No implementado.'], 501);
    }
}
