<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Services\CompositeScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TalentoEscalafonController extends Controller
{
    public function __construct(private CompositeScoreService $service) {}

    public function index()
    {
        $this->authorize('talento.escalafon.view');
        return view('addon-talento::talento.escalafon');
    }

    public function ranking(Request $request)
    {
        $this->authorize('talento.escalafon.view');

        $weeksBack = (int)($request->weeks_back ?? 4);
        $weights   = $this->loadWeights();

        // Cache for 15 minutes to avoid recomputing on every page reload
        $cacheKey = 'talento.escalafon.' . md5(json_encode($weights) . $weeksBack);
        $data = Cache::remember($cacheKey, 900, fn() => $this->service->scoreAll($weights, $weeksBack));

        return response()->json($data);
    }

    public function getConfig()
    {
        $this->authorize('talento.escalafon.view');
        return response()->json(['weights' => $this->loadWeights()]);
    }

    public function saveConfig(Request $request)
    {
        $this->authorize('talento.escalafon.manage');

        $request->validate([
            'weights'              => 'required|array',
            'weights.quota'        => 'required|integer|min:0|max:100',
            'weights.quality'      => 'required|integer|min:0|max:100',
            'weights.health_bonus' => 'required|integer|min:0|max:100',
            'weights.normas'       => 'required|integer|min:0|max:100',
            'weights.attendance'   => 'required|integer|min:0|max:100',
        ]);

        \DB::table('talento_escalafon_config')
            ->updateOrInsert(['id' => 1], [
                'weights'    => json_encode($request->weights),
                'updated_at' => now(),
            ]);

        Cache::forget('talento.escalafon.*');

        return response()->json(['ok' => true]);
    }

    private function loadWeights(): array
    {
        $row = \DB::table('talento_escalafon_config')->where('id', 1)->first();
        if (!$row) return CompositeScoreService::DEFAULT_WEIGHTS;

        $decoded = json_decode($row->weights, true);
        return is_array($decoded) ? $decoded : CompositeScoreService::DEFAULT_WEIGHTS;
    }
}
