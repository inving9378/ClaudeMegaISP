<?php

namespace App\Modules\Addons\Embajadores\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function index()
    {
        return view('addon-embajadores::metrics.index');
    }

    public function data(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $days = min(max($days, 7), 365);

        $stats = DB::table('referral_stats_daily')
            ->where('stat_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('stat_date')
            ->get();

        $jobMetrics = DB::table('referral_job_metrics')
            ->where('ran_at', '>=', now()->subDays($days))
            ->select('job_class', 'status',
                DB::raw('COUNT(*) as executions'),
                DB::raw('AVG(duration_ms) as avg_ms'),
                DB::raw('MAX(duration_ms) as max_ms'),
                DB::raw('SUM(records_processed) as total_records'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failures')
            )
            ->groupBy('job_class', 'status')
            ->orderBy('job_class')
            ->get();

        $jobSummary = DB::table('referral_job_metrics')
            ->where('ran_at', '>=', now()->subDays($days))
            ->select('job_class',
                DB::raw('COUNT(*) as total_runs'),
                DB::raw('ROUND(AVG(duration_ms)) as avg_ms'),
                DB::raw('MAX(duration_ms) as max_ms'),
                DB::raw('SUM(records_processed) as total_records'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failures')
            )
            ->groupBy('job_class')
            ->orderBy('job_class')
            ->get();

        $closureCount = DB::table('referral_closures')->count();
        $statsCount   = DB::table('referral_stats_daily')->count();

        return response()->json([
            'daily_stats'   => $stats,
            'job_summary'   => $jobSummary,
            'job_details'   => $jobMetrics,
            'infra'         => [
                'closure_rows'  => $closureCount,
                'stats_rows'    => $statsCount,
                'period_days'   => $days,
            ],
        ]);
    }
}
