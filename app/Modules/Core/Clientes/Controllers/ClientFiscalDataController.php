<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Models\ClientFiscalData;
use App\Models\ClientFiscalDataAudit;
use App\Services\Finance\Timbrado\TimbradoServiceInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClientFiscalDataController extends Controller
{
    // Sin este trait $this->authorize() no existe en Illuminate\Routing\Controller y cada
    // llamada respondía 500 (la API no tenía pantalla hasta el item #480).
    use AuthorizesRequests;

    private const CAMPOS_EMPRESARIALES = [
        'num_exterior', 'num_interior', 'colonia', 'municipio', 'estado', 'pais',
        'forma_pago_cfdi', 'metodo_pago_cfdi', 'requiere_orden_compra', 'dia_recepcion_facturas',
        'dias_credito', 'condiciones_credito', 'contacto_facturacion_nombre', 'contacto_facturacion_cargo',
        'contacto_facturacion_telefono', 'contacto_facturacion_extension', 'contacto_facturacion_celular',
    ];

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
        $this->authorize('facturacion.datos.ver');

        $data = ClientFiscalData::where('client_id', $clientId)->first();

        return response()->json([
            'fiscal_data'       => $data,
            'catalogos'         => [
                'regimenes_fiscales' => ClientFiscalData::catalogoRegimenFiscal(),
                'usos_cfdi'          => ClientFiscalData::catalogoUsoCFDI(),
                'formas_pago'        => ClientFiscalData::catalogoFormaPago(),
                'metodos_pago'       => ClientFiscalData::catalogoMetodoPago(),
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
        $this->authorize('facturacion.datos.editar');

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
            'calle'                 => 'nullable|string|max:255',
            'ciudad'                => 'nullable|string|max:150',
            'constancia'            => 'nullable|file|mimes:pdf|max:5120',
            // Campos empresariales opcionales (CRM-E 02): solo se escriben si el cliente los manda.
            'num_exterior'          => 'nullable|string|max:30',
            'num_interior'          => 'nullable|string|max:30',
            'colonia'               => 'nullable|string|max:150',
            'municipio'             => 'nullable|string|max:150',
            'estado'                => 'nullable|string|max:100',
            'pais'                  => 'nullable|string|max:100',
            'forma_pago_cfdi'       => ['nullable', 'in:' . implode(',', array_keys(ClientFiscalData::catalogoFormaPago()))],
            'metodo_pago_cfdi'      => ['nullable', 'in:' . implode(',', array_keys(ClientFiscalData::catalogoMetodoPago()))],
            'requiere_orden_compra' => 'nullable|boolean',
            'dia_recepcion_facturas' => 'nullable|string|max:100',
            'dias_credito'          => 'nullable|integer|min:0|max:365',
            'condiciones_credito'   => 'nullable|string|max:500',
            'contacto_facturacion_nombre'    => 'nullable|string|max:200',
            'contacto_facturacion_cargo'     => 'nullable|string|max:150',
            'contacto_facturacion_telefono'  => 'nullable|string|max:40',
            'contacto_facturacion_extension' => 'nullable|string|max:20',
            'contacto_facturacion_celular'   => 'nullable|string|max:40',
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
            'calle'                 => $this->opcional($request->input('calle')),
            'ciudad'                => $this->opcional($request->input('ciudad')),
            'updated_by'            => $userId,
        ]);

        // Opcionales empresariales: solo los que llegan en la petición (no pisa lo ya guardado).
        foreach (self::CAMPOS_EMPRESARIALES as $campo) {
            if ($request->has($campo)) {
                $fiscal->{$campo} = $campo === 'requiere_orden_compra'
                    ? $request->boolean($campo)
                    : $this->opcional($request->input($campo));
            }
        }

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

        // #1152: diff anterior/nuevo (se calcula antes de guardar; la bitácora nunca rompe el guardado).
        $cambios = $fiscal->exists ? $fiscal->getDirty() : $fiscal->getAttributes();
        $anteriores = $fiscal->exists ? $fiscal->getOriginal() : [];

        $fiscal->save();

        $this->registrarAuditoria($request, $clientId, $userId, $cambios, $anteriores);

        return response()->json(['data' => $fiscal, 'message' => 'Datos fiscales guardados.']);
    }

    private function registrarAuditoria(Request $request, int $clientId, int $userId, array $cambios, array $anteriores): void
    {
        try {
            $omitir = ['id', 'client_id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'constancia_path'];
            foreach ($cambios as $campo => $nuevo) {
                if (in_array($campo, $omitir, true)) {
                    continue;
                }
                $antes = $anteriores[$campo] ?? null;
                if ((string) $antes === (string) $nuevo) {
                    continue;
                }
                ClientFiscalDataAudit::create([
                    'client_id'      => $clientId,
                    'user_id'        => $userId ?: null,
                    'campo'          => $campo,
                    'valor_anterior' => $antes === null ? null : (string) $antes,
                    'valor_nuevo'    => $nuevo === null ? null : (string) $nuevo,
                    'ip'             => $request->ip(),
                    'created_at'     => now(),
                ]);
            }
            if ($request->hasFile('constancia')) {
                ClientFiscalDataAudit::create([
                    'client_id' => $clientId, 'user_id' => $userId ?: null, 'campo' => 'constancia',
                    'valor_anterior' => null, 'valor_nuevo' => '(archivo actualizado)',
                    'ip' => $request->ip(), 'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Auditoría datos de facturación no registrada: ' . $e->getMessage(), ['client_id' => $clientId]);
        }
    }

    /**
     * GET /cliente/billing/datos-fiscales/{clientId}/historial — solo lectura (#1152).
     */
    public function historial(int $clientId): JsonResponse
    {
        $this->authorize('facturacion.auditoria.ver');

        $filas = ClientFiscalDataAudit::with('user:id,name')
            ->where('client_id', $clientId)->orderByDesc('id')->limit(200)->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'campo' => $a->campo, 'usuario' => $a->user->name ?? 'Sistema',
                'valor_anterior' => $a->valor_anterior, 'valor_nuevo' => $a->valor_nuevo,
                'fecha' => optional($a->created_at)->format('Y-m-d H:i'),
            ]);

        return response()->json(['data' => $filas]);
    }

    private function opcional($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    /**
     * GET /api/clientes/{clientId}/datos-fiscales/constancia
     * Descarga la constancia de situación fiscal.
     */
    public function downloadConstancia(int $clientId): mixed
    {
        $this->authorize('facturacion.datos.editar');

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

        // FR-10 (decisión de Irving 2026-10-17): nunca se timbra un CFDI de un periodo no pagado.
        // La factura debe ser de este cliente y estar pagada por completo.
        $invoice = \App\Models\Invoice::where('client_id', $clientId)->find($request->input('invoice_id'));
        if (! \App\Services\Finance\Billing\ReglasFacturacion::puedeTimbrar($invoice)) {
            return response()->json([
                'error' => 'No se timbra un periodo no pagado.',
                'message' => 'Solo se puede timbrar una factura pagada de este cliente. La prefactura y el estado de cuenta no tienen valor fiscal.',
            ], 422);
        }

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
