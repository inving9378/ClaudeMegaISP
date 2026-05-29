<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingNiche;
use App\Modules\Addons\Marketing\Services\Tts\TtsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceComparatorController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:test-voices');
    }

    public function listVoices(): JsonResponse
    {
        return response()->json([
            'voices' => [
                ['id' => 'alloy',   'gender' => 'neutra',    'description' => 'Equilibrada, profesional'],
                ['id' => 'echo',    'gender' => 'masculina', 'description' => 'Joven, enérgica'],
                ['id' => 'fable',   'gender' => 'masculina', 'description' => 'Sofisticada, cuenta historias'],
                ['id' => 'onyx',    'gender' => 'masculina', 'description' => 'Grave, autoritaria'],
                ['id' => 'nova',    'gender' => 'femenina',  'description' => 'Cálida, conversacional'],
                ['id' => 'shimmer', 'gender' => 'femenina',  'description' => 'Suave, expresiva'],
            ],
            'models' => [
                ['id' => 'tts-1',    'name' => 'Standard',         'cost_per_1m_chars' => 15],
                ['id' => 'tts-1-hd', 'name' => 'HD (más natural)', 'cost_per_1m_chars' => 30],
            ],
            'niches' => MarketingNiche::where('active', 1)
                ->orderBy('display_order')
                ->get(['id', 'slug', 'name', 'preferred_voice_id', 'preferred_voice_model']),
        ]);
    }

    public function generateSamples(Request $request): JsonResponse
    {
        $request->validate([
            'text'   => 'required|string|max:500',
            'voices' => 'required|array|min:1',
            'models' => 'required|array|min:1',
        ]);

        $tts       = app(TtsService::class);
        $samples   = [];
        $totalCost = 0.0;
        $tempDir   = storage_path('app/marketing/temp');
        $pubDir    = public_path('voice_samples');

        if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);
        if (!is_dir($pubDir))  mkdir($pubDir,  0775, true);

        foreach ($request->models as $model) {
            foreach ($request->voices as $voice) {
                $uid        = uniqid();
                $filename   = "vs_{$model}_{$voice}_{$uid}.mp3";
                $tmpPath    = "{$tempDir}/{$filename}";
                $pubPath    = "{$pubDir}/{$filename}";

                $result = $tts->synthesize($request->text, $tmpPath, [
                    'driver' => 'openai',
                    'voice'  => $voice,
                    'model'  => $model,
                    'skip_preprocessing' => true,
                ]);

                if ($result['success'] && file_exists($tmpPath)) {
                    copy($tmpPath, $pubPath);
                    @unlink($tmpPath);

                    $samples[] = [
                        'voice'       => $voice,
                        'model'       => $model,
                        'label'       => "{$voice} / {$model}",
                        'url'         => url("voice_samples/{$filename}"),
                        'duration_sec'=> $result['duration_sec'] ?? null,
                        'cost_usd'    => $result['cost_usd'] ?? 0,
                    ];
                    $totalCost += $result['cost_usd'] ?? 0;
                } else {
                    Log::channel('marketing')->warning('VoiceComparator sample failed', [
                        'voice' => $voice, 'model' => $model, 'error' => $result['error'] ?? '',
                    ]);
                }
            }
        }

        return response()->json([
            'samples'        => $samples,
            'total_cost_usd' => round($totalCost, 4),
            'total_cost_mxn' => round($totalCost * 20, 2),
        ]);
    }

    public function assignToNiche(Request $request): JsonResponse
    {
        $request->validate([
            'niche_id'    => 'required|exists:marketing_niches,id',
            'voice_id'    => 'required|string',
            'voice_model' => 'required|string',
        ]);

        $niche = MarketingNiche::findOrFail($request->niche_id);
        $niche->preferred_voice_id    = $request->voice_id;
        $niche->preferred_voice_model = $request->voice_model;
        $niche->save();

        return response()->json([
            'success' => true,
            'niche'   => $niche->fresh(['id', 'slug', 'name', 'preferred_voice_id', 'preferred_voice_model']),
        ]);
    }
}
