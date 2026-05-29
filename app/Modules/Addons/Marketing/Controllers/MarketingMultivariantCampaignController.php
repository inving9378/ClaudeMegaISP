<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\MarketingNiche;
use App\Models\Marketing\MultivariantCampaign;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Jobs\RenderVideoJob;
use App\Modules\Addons\Marketing\Services\Personalization\CreativeDirectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MarketingMultivariantCampaignController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view-marketing-campaigns')->only(['index', 'show', 'progress']);
        $this->middleware('permission:create-marketing-campaigns')->only('store');
        $this->middleware('permission:regenerate-marketing-variants')->only('regenerateVariant');
        $this->middleware('permission:delete-marketing-campaigns')->only('destroy');
        $this->middleware('permission:manage-marketing-niches')->only(['niches', 'updateNiche']);
    }

    public function index(Request $request): JsonResponse
    {
        $items = MultivariantCampaign::forCompany(1)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function show(int $id): JsonResponse
    {
        $campaign = MultivariantCampaign::forCompany(1)->findOrFail($id);

        $variants = GeneratedContent::whereIn('id', $campaign->variant_content_ids ?? [])
            ->get(['id', 'status', 'output_path', 'thumbnail_path', 'failure_reason', 'generation_metadata', 'created_at']);

        return response()->json([
            'campaign' => $campaign,
            'variants' => $variants,
        ]);
    }

    public function progress(int $id): JsonResponse
    {
        $campaign = MultivariantCampaign::forCompany(1)->findOrFail($id);

        $variantIds = $campaign->variant_content_ids ?? [];
        $variants   = GeneratedContent::whereIn('id', $variantIds)
            ->get(['id', 'status', 'output_path', 'thumbnail_path', 'failure_reason', 'generation_metadata']);

        $done    = $variants->whereIn('status', ['ready', 'failed'])->count();
        $total   = count($variantIds);
        $percent = $total > 0 ? round(($done / $total) * 100) : 0;

        return response()->json([
            'campaign_status' => $campaign->status,
            'progress_percent'=> $percent,
            'done'            => $done,
            'total'           => $total,
            'variants'        => $variants,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'              => 'required|string|max:150',
            'campaign_type'     => 'required|string|in:multivariant_plan_promo,multivariant_coverage',
            'plan_name'         => 'required|string|max:100',
            'plan_speed'        => 'required|string|max:50',
            'plan_price'        => 'required|string|max:50',
            'phone_cta'         => 'required|string|max:30',
            'highlight_feature' => 'sometimes|string|max:150',
            'niche_slugs'       => 'sometimes|array',
            'aspect_ratios'     => 'sometimes|array',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        $inputData = [
            'campaign_type'     => $request->campaign_type,
            'plan_name'         => $request->plan_name,
            'plan_speed'        => $request->plan_speed,
            'plan_price'        => $request->plan_price,
            'phone_cta'         => $request->phone_cta,
            'highlight_feature' => $request->input('highlight_feature', 'Sin contratos forzados'),
        ];

        $campaign = MultivariantCampaign::create([
            'company_id'          => 1,
            'name'                => $request->name,
            'campaign_type'       => $request->campaign_type,
            'status'              => 'generating',
            'input_data'          => $inputData,
            'started_at'          => now(),
            'created_by_user_id'  => auth()->id(),
        ]);

        // Dispatch async: generate briefs + render variants
        \App\Modules\Addons\Marketing\Jobs\GenerateMultivariantCampaignJob::dispatch(
            $campaign->id,
            $inputData,
            $request->input('niche_slugs', []),
            $request->input('aspect_ratios', ['9:16'])
        );

        return response()->json(['data' => $campaign], 201);
    }

    public function regenerateVariant(Request $request, int $id, string $nicheSlug): JsonResponse
    {
        $campaign = MultivariantCampaign::forCompany(1)->findOrFail($id);

        $template = VideoTemplate::where('slug', "multivariant-plan-promo-{$nicheSlug}")->first();
        if (!$template) {
            return response()->json(['error' => "Plantilla no encontrada para nicho: {$nicheSlug}"], 404);
        }

        $brief = collect($campaign->creative_briefs ?? [])
            ->firstWhere('niche_slug', $nicheSlug);

        $content = GeneratedContent::create([
            'company_id'        => 1,
            'type'              => 'video',
            'template_id'       => $template->id,
            'template_type'     => VideoTemplate::class,
            'input_variables'   => array_merge(
                $campaign->input_data ?? [],
                ['_aspect_ratio' => '9:16', '_creative_brief' => $brief, '_niche_slug' => $nicheSlug]
            ),
            'status'            => 'pending',
            'generation_engine' => 'mvm_ffmpeg',
        ]);

        RenderVideoJob::dispatch($content->id, $template->id, $content->input_variables, [], 1);

        // Add to campaign's variant list
        $variantIds   = $campaign->variant_content_ids ?? [];
        $variantIds[] = $content->id;
        $campaign->update(['variant_content_ids' => array_unique($variantIds)]);

        return response()->json(['data' => $content->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $campaign = MultivariantCampaign::forCompany(1)->findOrFail($id);

        // Delete associated video files
        foreach ($campaign->variant_content_ids ?? [] as $contentId) {
            $content = GeneratedContent::find($contentId);
            if ($content && $content->output_path) {
                @unlink(storage_path('app/' . $content->output_path));
            }
            $content?->delete();
        }

        $campaign->delete();

        return response()->json(['message' => 'Campaña eliminada']);
    }

    public function niches(): JsonResponse
    {
        $niches = MarketingNiche::where('company_id', 1)
            ->orderBy('display_order')
            ->get();

        return response()->json(['data' => $niches]);
    }

    public function updateNiche(Request $request, int $id): JsonResponse
    {
        $niche = MarketingNiche::where('company_id', 1)->findOrFail($id);

        $niche->fill($request->only([
            'name', 'description', 'motivators', 'pain_points', 'objections',
            'vocabulary', 'emotional_tone', 'music_mood', 'broll_tags',
            'voice_style', 'preferred_voice_id', 'active', 'display_order',
        ]));
        $niche->save();

        return response()->json(['data' => $niche]);
    }
}
