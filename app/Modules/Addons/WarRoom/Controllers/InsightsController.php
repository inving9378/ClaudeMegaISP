<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WarRoom\Jobs\RefreshInsightsJob;
use App\Modules\Addons\WarRoom\Models\InsightsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    public function show(string $view, ?string $period = null): JsonResponse
    {
        $period = $period ?? now()->format('Y-m');

        $cached = InsightsCache::where('view_key', $view)
            ->where('period', $period)
            ->first();

        // Cualquier cache existente → servir tal cual, sin regenerar
        if ($cached) {
            return response()->json([
                'insights' => $cached->insights,
                'source'   => $cached->source,
                'cached'   => true,
                'status'   => 'ready',
            ]);
        }

        // Sin cache → generar una vez en background
        RefreshInsightsJob::dispatch($view, $period)->onQueue('default');

        return response()->json([
            'insights' => [],
            'source'   => null,
            'cached'   => false,
            'status'   => 'generating',
        ]);
    }

    public function regenerate(Request $request, string $view, ?string $period = null): JsonResponse
    {
        $this->authorize('warroom.insights.regenerate');

        $period = $period ?? now()->format('Y-m');

        InsightsCache::where('view_key', $view)->where('period', $period)->delete();

        RefreshInsightsJob::dispatch($view, $period)->onQueue('default');

        return response()->json(['queued' => true, 'status' => 'generating']);
    }
}
