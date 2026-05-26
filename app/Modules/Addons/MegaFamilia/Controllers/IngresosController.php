<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalLicense;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngresosController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::ingresos.index');
    }

    public function data(): JsonResponse
    {
        return response()->json($this->buildData());
    }

    public function export(): StreamedResponse
    {
        $p = $this->buildData();
        $filename = 'megafamilia-ingresos-' . now()->format('Ymd-His') . '.csv';

        return new StreamedResponse(function () use ($p) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Reporte de Ingresos MegaFamilia']);
            fputcsv($out, ['Generado', now()->toDateTimeString()]);
            fputcsv($out, []);
            fputcsv($out, ['Resumen']);
            fputcsv($out, ['MRR (USD)',        number_format($p['mrr'], 2)]);
            fputcsv($out, ['ARR (USD)',        number_format($p['arr'], 2)]);
            fputcsv($out, ['Licencias activas', $p['active_licenses']]);
            fputcsv($out, ['ARPU (avg/cliente)', number_format($p['avg_per_client'], 2)]);
            fputcsv($out, []);

            fputcsv($out, ['Ingresos por plan']);
            fputcsv($out, ['Plan', 'Licencias activas', 'Precio unitario', 'Total mensual', '% MRR']);
            foreach ($p['by_plan'] as $r) {
                fputcsv($out, [$r['name'], $r['licenses'], $r['unit_price'], $r['total'], $r['percent'] . '%']);
            }
            fputcsv($out, []);

            fputcsv($out, ['Evolución mensual (últimos 12 meses)']);
            fputcsv($out, ['Mes', 'Ingreso']);
            foreach ($p['monthly_evolution'] as $r) fputcsv($out, [$r['month'], $r['revenue']]);
            fputcsv($out, []);

            fputcsv($out, ['Por vencer en los próximos 30 días']);
            fputcsv($out, ['ID licencia', 'Cliente', 'Plan', 'Vencimiento', 'Días restantes']);
            foreach ($p['expiring_soon'] as $r) {
                fputcsv($out, [$r['id'], $r['client'], $r['plan'], $r['expires_at'], $r['days_left']]);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildData(): array
    {
        $now = Carbon::now();

        // Licencias activas vigentes (status=active y aún no expiran).
        $activeQuery = ParentalLicense::query()
            ->join('parental_plans', 'parental_plans.id', '=', 'parental_licenses.plan_id')
            ->where('parental_licenses.status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('parental_licenses.expires_at')
                  ->orWhere('parental_licenses.expires_at', '>=', $now);
            });

        $activeCount = (clone $activeQuery)->count();
        $mrr         = (float) (clone $activeQuery)->sum('parental_plans.price_monthly');
        $arr         = $mrr * 12;
        $avgPerClient = $activeCount > 0 ? $mrr / $activeCount : 0.0;

        // Ingresos por plan
        $byPlan = (clone $activeQuery)
            ->select(
                'parental_plans.id',
                'parental_plans.name',
                'parental_plans.price_monthly',
                DB::raw('COUNT(*) as licenses'),
                DB::raw('SUM(parental_plans.price_monthly) as total'),
            )
            ->groupBy('parental_plans.id', 'parental_plans.name', 'parental_plans.price_monthly')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'id'         => (int) $r->id,
                'name'       => $r->name,
                'licenses'   => (int) $r->licenses,
                'unit_price' => (float) $r->price_monthly,
                'total'      => (float) $r->total,
                'percent'    => $mrr > 0 ? round(($r->total / $mrr) * 100, 1) : 0,
            ])->values();

        // Evolución mensual: para cada uno de los últimos 12 meses, suma del
        // price_monthly de las licencias que estaban activas durante ese mes.
        $monthly = [];
        for ($i = 11; $i >= 0; $i--) {
            $mStart = $now->copy()->subMonths($i)->startOfMonth();
            $mEnd   = $now->copy()->subMonths($i)->endOfMonth();

            $sum = ParentalLicense::query()
                ->join('parental_plans', 'parental_plans.id', '=', 'parental_licenses.plan_id')
                ->where('parental_licenses.activated_at', '<=', $mEnd)
                ->where(function ($q) use ($mStart) {
                    $q->whereNull('parental_licenses.expires_at')
                      ->orWhere('parental_licenses.expires_at', '>=', $mStart);
                })
                ->where('parental_licenses.status', '!=', 'expired')
                ->sum('parental_plans.price_monthly');

            $monthly[] = [
                'month'   => $mStart->format('Y-m'),
                'label'   => $mStart->translatedFormat('M Y'),
                'revenue' => (float) $sum,
            ];
        }

        $recentRenewals = ParentalLicense::query()
            ->with(['account.user:id,name,email', 'plan:id,name,price_monthly'])
            ->where('status', 'active')
            ->orderByDesc('activated_at')
            ->limit(10)
            ->get()
            ->map(fn ($l) => [
                'id'           => $l->id,
                'client'       => $l->account?->user?->name ?? '—',
                'email'        => $l->account?->user?->email ?? '',
                'plan'         => $l->plan?->name ?? '—',
                'amount'       => (float) ($l->plan?->price_monthly ?? 0),
                'activated_at' => $l->activated_at,
                'expires_at'   => $l->expires_at,
            ]);

        $expiringSoon = ParentalLicense::query()
            ->with(['account.user:id,name,email', 'plan:id,name,price_monthly'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(30)])
            ->orderBy('expires_at')
            ->get()
            ->map(function ($l) use ($now) {
                $days = $now->diffInDays(Carbon::parse($l->expires_at), false);
                return [
                    'id'         => $l->id,
                    'client'     => $l->account?->user?->name ?? '—',
                    'plan'       => $l->plan?->name ?? '—',
                    'amount'     => (float) ($l->plan?->price_monthly ?? 0),
                    'expires_at' => $l->expires_at,
                    'days_left'  => (int) max(0, $days),
                ];
            });

        return [
            'mrr'              => round($mrr, 2),
            'arr'              => round($arr, 2),
            'active_licenses'  => $activeCount,
            'avg_per_client'   => round($avgPerClient, 2),
            'by_plan'          => $byPlan,
            'monthly_evolution'=> $monthly,
            'recent_renewals'  => $recentRenewals,
            'expiring_soon'    => $expiringSoon,
        ];
    }
}
