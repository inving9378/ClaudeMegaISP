<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\MarketingNiche;
use App\Models\Marketing\MultivariantCampaign;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Services\Personalization\CreativeDirectorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMultivariantCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 900;

    public function __construct(
        public readonly int   $campaignId,
        public readonly array $inputData,
        public readonly array $niche_slugs  = [],
        public readonly array $aspectRatios = ['9:16'],
    ) {}

    public function handle(): void
    {
        $campaign = MultivariantCampaign::find($this->campaignId);
        if (!$campaign) {
            return;
        }

        try {
            $this->generateAndRender($campaign);
        } catch (\Throwable $e) {
            Log::channel('marketing')->error('GenerateMultivariantCampaignJob failed', [
                'campaign_id' => $this->campaignId,
                'error'       => $e->getMessage(),
            ]);
            $campaign->update(['status' => 'failed']);
        }
    }

    protected function generateAndRender(MultivariantCampaign $campaign): void
    {
        // Step 1: Generate creative briefs
        $briefs    = [];
        $totalCost = 0.0;

        try {
            $director = new CreativeDirectorService($campaign->company_id);
            $briefs   = $director->generateMultivariantBriefs($this->inputData, $campaign->company_id);
        } catch (\Throwable $e) {
            Log::channel('marketing')->warning('CreativeDirector failed, using fallback briefs', ['error' => $e->getMessage()]);
            $briefs = $this->buildFallbackBriefs($campaign->company_id);
        }

        $campaign->update(['creative_briefs' => $briefs]);

        // Step 2: Filter niches
        $nicheFilter = !empty($this->niche_slugs) ? $this->niche_slugs : null;
        $niches      = MarketingNiche::where('company_id', $campaign->company_id)
            ->where('active', true)
            ->when($nicheFilter, fn($q) => $q->whereIn('slug', $nicheFilter))
            ->orderBy('display_order')
            ->get();

        // Step 3: Dispatch render jobs
        $variantIds    = [];
        $variantCount  = 0;

        foreach ($niches as $niche) {
            foreach ($this->aspectRatios as $aspectRatio) {
                $template = VideoTemplate::where('slug', "multivariant-plan-promo-{$niche->slug}")->first()
                         ?? VideoTemplate::where('slug', 'multivariant-plan-promo-gamer')->first();

                if (!$template) {
                    continue;
                }

                $brief = collect($briefs)->firstWhere('niche_slug', $niche->slug);

                $content = GeneratedContent::create([
                    'company_id'        => $campaign->company_id,
                    'type'              => 'video',
                    'template_id'       => $template->id,
                    'template_type'     => VideoTemplate::class,
                    'input_variables'   => array_merge($this->inputData, [
                        '_aspect_ratio'    => $aspectRatio,
                        '_creative_brief'  => $brief ?? [],
                        '_niche_slug'      => $niche->slug,
                        'phone_cta'        => $this->inputData['phone_cta'] ?? '',
                        'plan_speed'       => $this->inputData['plan_speed'] ?? '',
                        'plan_price'       => $this->inputData['plan_price'] ?? '',
                    ]),
                    'status'            => 'pending',
                    'generation_engine' => 'mvm_ffmpeg',
                ]);

                RenderVideoJob::dispatch(
                    $content->id,
                    $template->id,
                    $content->input_variables,
                    [],
                    $campaign->company_id
                );

                $variantIds[] = $content->id;
                $variantCount++;
            }
        }

        $campaign->update([
            'variant_content_ids' => $variantIds,
        ]);

        // Step 4: Monitor completion (via a follow-up check job)
        MonitorCampaignJob::dispatch($campaign->id)->delay(now()->addMinutes(5));
    }

    protected function buildFallbackBriefs(int $companyId): array
    {
        $niches = MarketingNiche::where('company_id', $companyId)->where('active', true)->get();
        return $niches->map(function ($niche) {
            return [
                'niche_slug'           => $niche->slug,
                'niche_id'             => $niche->id,
                'hook'                 => '¿Tu internet te falla siempre?',
                'main_benefits'        => ['Sin contratos', 'Alta velocidad', 'Soporte real'],
                'voiceover_script'     => "Internet confiable para {$niche->name}. Sin contratos. Llama ahora.",
                'kinetic_text_segments'=> [
                    ['text' => '¿INTERNET LENTO?', 'duration_sec' => 2.0, 'emphasis' => 'high'],
                    ['text' => 'CAMBIA HOY', 'duration_sec' => 1.5, 'emphasis' => 'extreme'],
                ],
                'broll_keywords'       => $niche->broll_tags ?? [],
                'music_mood'           => $niche->music_mood,
                'cta'                  => '¡Llama ahora!',
                'scene_sequence'       => ['hook', 'solution', 'cta'],
            ];
        })->values()->toArray();
    }
}
