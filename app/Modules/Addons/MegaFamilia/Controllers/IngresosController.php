<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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

    public function export(): Response
    {
        $payload = $this->buildData();
        $pdf = Pdf::loadView('addon-megafamilia::pdf.ingresos', [
            'byPlan' => $payload['by_plan'],
            'byMonth' => $payload['by_month'],
            'mrr' => $payload['mrr'],
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('letter');
        $filename = 'megafamilia-ingresos-' . now()->format('Ymd-His') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Misma consulta que data() pero accesible desde export() sin reinvocarse
     * a sí mismo a través del router.
     */
    private function buildData(): array
    {
        $byPlan = ParentalAccount::query()
            ->join('parental_plans', 'parental_plans.id', '=', 'parental_accounts.plan_id')
            ->where('parental_accounts.status', 'active')
            ->select(
                'parental_plans.name',
                DB::raw('COUNT(*) as accounts'),
                DB::raw('SUM(parental_plans.price_monthly) as monthly_revenue')
            )
            ->groupBy('parental_plans.id', 'parental_plans.name')
            ->get();

        $byMonth = ParentalAccount::query()
            ->join('parental_plans', 'parental_plans.id', '=', 'parental_accounts.plan_id')
            ->where('parental_accounts.status', 'active')
            ->whereNotNull('parental_accounts.licensed_at')
            ->where('parental_accounts.licensed_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(parental_accounts.licensed_at,'%Y-%m') as month"),
                DB::raw('SUM(parental_plans.price_monthly) as revenue')
            )
            ->groupBy('month')->orderBy('month')->get();

        return [
            'by_plan' => $byPlan,
            'by_month' => $byMonth,
            'mrr' => (float) $byPlan->sum('monthly_revenue'),
        ];
    }
}
