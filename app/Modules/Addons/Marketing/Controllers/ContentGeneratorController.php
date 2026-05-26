<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Models\Campaign;
use App\Modules\Addons\Marketing\Models\CampaignContent;
use App\Modules\Addons\Marketing\Models\MarketingTemplate;
use App\Modules\Addons\Marketing\Services\AIContentService;
use App\Modules\Addons\Marketing\Services\ImageGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContentGeneratorController extends Controller
{
    public function __construct(
        private readonly AIContentService      $aiService,
        private readonly ImageGeneratorService $imageService
    ) {}

    public function generateCopy(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => 'required|integer|exists:marketing_campaigns,id',
            'template_id' => 'required|integer|exists:marketing_templates,id',
        ]);

        $campaign   = Campaign::findOrFail($request->campaign_id);
        $template   = MarketingTemplate::findOrFail($request->template_id);
        $variations = $this->aiService->generateCopy($campaign, $template);

        $created = collect($variations)->map(fn ($v) => CampaignContent::create([
            'campaign_id'     => $campaign->id,
            'content_type'    => 'text',
            'copy_text'       => $v['text'],
            'ia_generated'    => true,
            'variation_index' => $v['index'],
            'status'          => 'pending',
        ]))->values();

        return response()->json([
            'message'  => count($created) . ' variaciones generadas correctamente.',
            'contents' => $created,
        ]);
    }

    public function generateImage(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id'   => 'required|integer|exists:marketing_campaigns,id',
            'custom_prompt' => 'nullable|string|max:500',
        ]);

        $campaign = Campaign::findOrFail($request->campaign_id);
        $prompt   = $request->filled('custom_prompt')
            ? $request->custom_prompt
            : $this->aiService->generateImagePrompt($campaign);

        try {
            $imageUrl = $this->imageService->generate($prompt);

            $content = CampaignContent::create([
                'campaign_id'     => $campaign->id,
                'content_type'    => 'image',
                'image_url'       => $imageUrl,
                'image_prompt'    => $prompt,
                'ia_generated'    => true,
                'variation_index' => 0,
                'status'          => 'pending',
            ]);

            return response()->json([
                'message' => 'Imagen generada correctamente.',
                'content' => $content,
            ]);
        } catch (\Throwable $e) {
            Log::error('ContentGeneratorController::generateImage', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al generar imagen: ' . $e->getMessage()], 500);
        }
    }

    public function approve(int $id): JsonResponse
    {
        $content = CampaignContent::findOrFail($id);
        $content->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
        ]);

        return response()->json($content);
    }

    public function reject(int $id): JsonResponse
    {
        $content = CampaignContent::findOrFail($id);
        $content->update(['status' => 'rejected']);

        return response()->json($content);
    }

    public function byCampaign(int $campaignId): JsonResponse
    {
        Campaign::findOrFail($campaignId);

        $contents = CampaignContent::where('campaign_id', $campaignId)
            ->orderBy('content_type')
            ->orderBy('variation_index')
            ->get();

        return response()->json($contents);
    }
}
