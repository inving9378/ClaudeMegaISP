<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WarRoom\Jobs\RefreshInsightsJob;
use App\Modules\Addons\WarRoom\Models\InsightsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    /**
     * TTL que se considera "fresco" para peticiones HTTP (2 horas).
     * El scheduler refresca cada hora, así que en la mayoría de cargas
     * el cache tendrá < 1h de antigüedad y se sirve al instante.
     */
    private const FRESH_TTL_MINUTES = 120;

    public function show(string $view, ?string $period = null): JsonResponse
    {
        $period = $period ?? now()->format('Y-m');

        $cached = InsightsCache::where('view_key', $view)
            ->where('period', $period)
            ->first();

        // Cache fresco → servir inmediatamente
        if ($cached && $cached->isFresh(self::FRESH_TTL_MINUTES)) {
            return response()->json([
                'insights' => $cached->insights,
                'source'   => $cached->source,
                'cached'   => true,
                'status'   => 'ready',
            ]);
        }

        // Cache stale o vacío → disparar job en background, devolver lo que hay
        RefreshInsightsJob::dispatch($view, $period)->onQueue('default');

        return response()->json([
            'insights' => $cached?->insights ?? [],
            'source'   => $cached?->source,
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
