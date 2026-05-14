<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAppBlock;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::reportes.index');
    }

    public function data(Request $request): JsonResponse
    {
        // top apps (más usadas) — placeholder: contar bloqueos como proxy.
        $topApps = ParentalAppBlock::query()
            ->when($request->profile_id, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->select('app_name', DB::raw('count(*) as total'))
            ->whereNotNull('app_name')
            ->groupBy('app_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // screen time por día — proxy basado en eventos.
        $screenByDay = ParentalEvent::query()
            ->where('action', 'screen_time_log')
            ->when($request->profile_id, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->where('created_at', '>=', now()->subDays(14))
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as samples'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'top_apps' => $topApps,
            'screen_by_day' => $screenByDay,
        ]);
    }

    public function export()
    {
        return response()->json([
            'success' => false,
            'error' => 'Export PDF aún no implementado',
        ], 501);
    }
}
