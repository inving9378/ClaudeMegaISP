<?php

namespace App\Modules\Addons\PortalPago\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentLink;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentReport;
use App\Modules\Addons\PortalPago\Services\ConciliacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Bandeja de conciliación: reportes pendiente_validacion / discrepancia.
 */
class ConciliacionController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->can('pagos.conciliar'), 403);

        return view('addon-portal-pago::admin.conciliacion');
    }

    public function list(Request $request)
    {
        abort_unless(auth()->user()?->can('pagos.conciliar'), 403);

        $reports = PortalPagoPaymentReport::query()
            ->whereIn('estado', [
                PortalPagoPaymentReport::ESTADO_PENDIENTE,
                PortalPagoPaymentReport::ESTADO_DISCREPANCIA,
            ])
            ->with(['paymentLink.client.client_main_information', 'paymentLink.account'])
            ->orderBy('created_at', 'asc') // más antiguos primero
            ->paginate((int) $request->input('per_page', 15));

        $reports->getCollection()->transform(fn ($r) => $this->presentar($r));

        return response()->json($reports);
    }

    public function aprobar(Request $request, PortalPagoPaymentReport $report)
    {
        abort_unless(auth()->user()?->can('pagos.conciliar'), 403);

        if ($report->paymentLink && $report->paymentLink->estado === PortalPagoPaymentLink::ESTADO_CONCILIADO) {
            return response()->json(['ok' => false, 'message' => 'Esta liga ya fue conciliada.'], 409);
        }

        $report->update([
            'estado'       => PortalPagoPaymentReport::ESTADO_VALIDADO,
            'cep_validado' => true,
            'revisado_por' => auth()->id(),
            'revisado_at'  => now(),
        ]);

        try {
            // Mismo efecto que la conciliación automática (Payment polimórfico a
            // Client → cadena observer de reactivación + InvoicePaid).
            app(ConciliacionService::class)->conciliar($report);
        } catch (\Throwable $e) {
            Log::error('PortalPago: aprobación manual falló al conciliar', [
                'report_id' => $report->id,
                'error'     => $e->getMessage(),
            ]);
            $report->update(['estado' => PortalPagoPaymentReport::ESTADO_PENDIENTE]);
            return response()->json(['ok' => false, 'message' => 'No se pudo conciliar: ' . $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Pago conciliado y servicio en reactivación.']);
    }

    public function rechazar(Request $request, PortalPagoPaymentReport $report)
    {
        abort_unless(auth()->user()?->can('pagos.conciliar'), 403);

        $report->update([
            'estado'       => PortalPagoPaymentReport::ESTADO_RECHAZADO,
            'revisado_por' => auth()->id(),
            'revisado_at'  => now(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Reporte rechazado.']);
    }

    /**
     * Descarga protegida del comprobante. Nunca se expone storage/app/private
     * directamente: se sirve por esta ruta autenticada.
     */
    public function comprobante(PortalPagoPaymentReport $report)
    {
        abort_unless(auth()->user()?->can('pagos.conciliar'), 403);
        abort_if(empty($report->comprobante_path), 404);
        abort_unless(Storage::disk('local')->exists($report->comprobante_path), 404);

        return Storage::disk('local')->download($report->comprobante_path);
    }

    private function presentar(PortalPagoPaymentReport $r): array
    {
        $link    = $r->paymentLink;
        $account = $link?->account;
        $cmi     = $link?->client?->client_main_information;

        $clienteNombre = $cmi
            ? trim(implode(' ', array_filter([$cmi->name ?? null, $cmi->father_last_name ?? null, $cmi->mother_last_name ?? null])))
            : null;

        return [
            'id'               => $r->id,
            'estado'           => $r->estado,
            'cliente'          => $clienteNombre ?: ('Cliente #' . ($link->client_id ?? '—')),
            'client_id'        => $link->client_id ?? null,
            'monto_reportado'  => (float) $r->monto_reportado,
            'monto_esperado'   => (float) ($link->monto_esperado ?? 0),
            'monto_cuadra'     => $link && (float) $r->monto_reportado === (float) $link->monto_esperado,
            'banco_emisor'     => $r->banco_emisor,
            'clave_rastreo'    => $r->clave_rastreo,
            'fecha_operacion'  => optional($r->fecha_operacion)->format('d/m/Y'),
            'cep_resultado'    => $r->cep_resultado,
            'cep_validado'     => (bool) $r->cep_validado,
            'tiene_comprobante' => ! empty($r->comprobante_path),
            'cuenta'           => $account ? ($account->banco . ' · ' . $account->clabe) : null,
            'referencia'       => $link->referencia_unica ?? null,
            'creado'           => optional($r->created_at)->format('d/m/Y H:i'),
        ];
    }
}
