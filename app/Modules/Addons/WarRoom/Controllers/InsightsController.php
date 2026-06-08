<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WarRoom\Models\InsightsCache;
use App\Modules\Addons\WarRoom\Services\InsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    public function __construct(private InsightsService $insightsService) {}

    public function show(string $view, ?string $period = null): JsonResponse
    {
        $period = $period ?? now()->format('Y-m');

        $cached = InsightsCache::where('view_key', $view)
            ->where('period', $period)
            ->first();

        if ($cached && $cached->isFresh(60)) {
            return response()->json(['insights' => $cached->insights, 'source' => $cached->source, 'cached' => true]);
        }

        // Auto-generar si no hay cache válido
        try {
            $result = $this->insightsService->generate($view, $period);
            return response()->json(['insights' => $result->insights, 'source' => $result->source, 'cached' => false]);
        } catch (\Throwable $e) {
            return response()->json(['insights' => [], 'source' => null, 'cached' => false, 'error' => 'No se pudieron generar insights'], 500);
        }
    }

    public function regenerate(Request $request, string $view, ?string $period = null): JsonResponse
    {
        $this->authorize('warroom.insights.regenerate');

        $period = $period ?? now()->format('Y-m');

        InsightsCache::where('view_key', $view)->where('period', $period)->delete();

        try {
            $result = $this->insightsService->generate($view, $period);
            return response()->json(['insights' => $result->insights, 'source' => $result->source, 'regenerated' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al regenerar insights: ' . $e->getMessage()], 500);
        }
    }
}
