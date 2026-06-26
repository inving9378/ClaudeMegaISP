<?php

namespace App\Modules\Addons\PortalPago\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentLink;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentReport;
use Illuminate\Support\Carbon;

/**
 * Dashboard / KPI cards del Portal de Pago.
 *
 * Fechas en portal_pago_payment_links son created_at (timestamp real, NO el
 * VARCHAR legacy) → se filtran con whereBetween, sin STR_TO_DATE.
 * Se excluyen las ligas expiradas de todos los KPIs.
 */
class DashboardController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->can('pagos.view'), 403);

        return view('addon-portal-pago::admin.dashboard');
    }

    public function kpis()
    {
        abort_unless(auth()->user()?->can('pagos.view'), 403);

        $inicioMes = Carbon::now()->startOfMonth();
        $finMes    = Carbon::now()->endOfMonth();

        // Base del mes, excluyendo ligas expiradas.
        $base = PortalPagoPaymentLink::query()
            ->whereBetween('created_at', [$inicioMes, $finMes])
            ->where('estado', '!=', PortalPagoPaymentLink::ESTADO_EXPIRADO);

        $totalLigasMes = (clone $base)->count();

        // Auto-conciliadas: conciliadas SIN revisión manual (ningún reporte con revisado_por).
        $autoConciliadas = (clone $base)
            ->where('estado', PortalPagoPaymentLink::ESTADO_CONCILIADO)
            ->whereDoesntHave('reports', fn ($q) => $q->whereNotNull('revisado_por'))
            ->count();

        $pctAuto = $totalLigasMes > 0
            ? round($autoConciliadas / $totalLigasMes * 100, 1)
            : 0;

        // Bandeja: reportes pendientes de validación o en discrepancia (backlog actual).
        $pendientesBandeja = PortalPagoPaymentReport::query()
            ->whereIn('estado', [
                PortalPagoPaymentReport::ESTADO_PENDIENTE,
                PortalPagoPaymentReport::ESTADO_DISCREPANCIA,
            ])
            ->count();

        // Monto conciliado este mes (suma de monto_esperado de ligas conciliadas).
        $montoConciliadoMes = (clone $base)
            ->where('estado', PortalPagoPaymentLink::ESTADO_CONCILIADO)
            ->sum('monto_esperado');

        return response()->json([
            'pct_auto_conciliado'   => $pctAuto,
            'auto_conciliadas'      => $autoConciliadas,
            'total_ligas_mes'       => $totalLigasMes,
            'pendientes_bandeja'    => $pendientesBandeja,
            'monto_conciliado_mes'  => (float) $montoConciliadoMes,
            'mes'                   => ucfirst($inicioMes->locale('es')->isoFormat('MMMM YYYY')),
        ]);
    }
}
