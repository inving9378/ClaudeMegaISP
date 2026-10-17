<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Models\ClientEstadoCuenta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * FR-02 (#536): estados de cuenta generados del cliente (el final al pasar a Cancelado). Solo
 * lectura: listar y descargar el PDF. Mismo permiso que los Datos de facturación (facturacion.ver).
 */
class ClientEstadoCuentaController extends Controller
{
    use AuthorizesRequests;

    public function index(int $clientId): JsonResponse
    {
        $this->authorize('facturacion.ver');

        $estados = ClientEstadoCuenta::where('client_id', $clientId)->orderByDesc('id')->limit(50)
            ->get(['id', 'tipo', 'clave', 'estado_cliente', 'saldo', 'adeudo', 'facturas_pendientes', 'pdf_path', 'origen', 'error', 'created_at'])
            ->map(fn ($e) => [
                'id' => $e->id, 'tipo' => $e->tipo, 'clave' => $e->clave, 'estado_cliente' => $e->estado_cliente,
                'saldo' => $e->saldo, 'adeudo' => $e->adeudo, 'facturas_pendientes' => $e->facturas_pendientes,
                'tiene_pdf' => (bool) $e->pdf_path, 'origen' => $e->origen, 'error' => $e->error,
                'fecha' => optional($e->created_at)->format('d/m/Y H:i'),
            ]);

        return response()->json(['estados_cuenta' => $estados]);
    }

    public function pdf(int $clientId, int $id)
    {
        $this->authorize('facturacion.ver');

        $e = ClientEstadoCuenta::where('client_id', $clientId)->findOrFail($id);
        $disco = Storage::disk(config('billing.pdf_disk', 'local'));
        if (! $e->pdf_path || ! $disco->exists($e->pdf_path)) {
            return response()->json(['error' => 'El PDF de este estado de cuenta no está disponible.'], 404);
        }

        return response($disco->get($e->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="estado_cuenta_' . $clientId . '_' . $e->tipo . '_' . $e->clave . '.pdf"',
        ]);
    }
}
