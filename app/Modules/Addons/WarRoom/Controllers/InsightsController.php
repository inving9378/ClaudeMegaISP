<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
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

        if ($cached && $cached->isFresh(60)) {
            return response()->json(['insights' => $cached->insights, 'source' => $cached->source, 'cached' => true]);
        }

        // Sin cache — devolver vacío y que el frontend lo pida al endpoint de regenerar
        return response()->json(['insights' => [], 'source' => null, 'cached' => false]);
    }

    public function regenerate(Request $request, string $view, ?string $period = null): JsonResponse
    {
        $this->authorize('warroom.insights.regenerate');

        $period = $period ?? now()->format('Y-m');

        InsightsCache::where('view_key', $view)->where('period', $period)->delete();

        return response()->json(['queued' => true]);
    }
}
