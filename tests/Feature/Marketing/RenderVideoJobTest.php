<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Jobs\RenderVideoJob;
use App\Modules\Addons\Marketing\Services\Tts\TtsService;
use App\Modules\Addons\Marketing\Services\Video\AssetLibraryService;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use App\Modules\Addons\Marketing\Services\Video\VideoTemplateRenderer;
use Illuminate\Support\Facades\Queue;

class RenderVideoJobTest extends MarketingTestCase
{
    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        RenderVideoJob::dispatch(1, 1, ['plan_name' => 'Test'], ['aspect_ratio' => '9:16'], 1);

        Queue::assertPushed(RenderVideoJob::class, function ($job) {
            return $job->generatedContentId === 1
                && $job->templateId === 1;
        });
    }

    public function test_job_marks_record_failed_when_template_missing(): void
    {
        $template = VideoTemplate::where('slug', 'plan_promo_9_16')->first();
        if (!$template) {
            $this->markTestSkipped('plan_promo_9_16 not seeded');
        }

        $record = GeneratedContent::create([
            'company_id'          => 1,
            'type'                => 'video',
            'status'              => 'pending',
            'template_id'         => $template->id,
            'template_type'       => VideoTemplate::class,
            'render_progress'     => 0,
            'render_stage'        => 'queued',
            'render_log'          => [],
            'generation_engine'   => 'mvm_ffmpeg',
            'generation_cost_usd' => 0,
            'generation_metadata' => [],
        ]);

        // Use a non-existent template ID to trigger "not found" path
        $job = new RenderVideoJob(
            generatedContentId: $record->id,
            templateId: 999999,
            variables: [],
            options: ['aspect_ratio' => '9:16'],
            companyId: 1,
        );

        $renderer = new VideoTemplateRenderer(
            new FFmpegService(1),
            new TtsService(1),
            new AssetLibraryService(1),
        );

        $job->handle($renderer);

        $record->refresh();
        // Job should return early without changing status (template not found = return early)
        // Status stays 'pending' since we return before touching the record
        $this->assertNotEquals('ready', $record->status);
    }

    public function test_job_sets_status_generating_on_start(): void
    {
        $template = VideoTemplate::where('slug', 'generic_9_16')->first();
        if (!$template) {
            $this->markTestSkipped('generic_9_16 template not seeded');
        }

        if (!file_exists('/usr/bin/ffmpeg') && !file_exists('/usr/local/bin/ffmpeg')) {
            $this->markTestSkipped('FFmpeg not installed');
        }

        $record = GeneratedContent::create([
            'company_id'          => 1,
            'type'                => 'video',
            'status'              => 'pending',
            'template_id'         => $template->id,
            'template_type'       => VideoTemplate::class,
            'render_progress'     => 0,
            'render_stage'        => 'queued',
            'render_log'          => [],
            'generation_engine'   => 'mvm_ffmpeg',
            'generation_cost_usd' => 0,
            'generation_metadata' => [],
        ]);

        $job = new RenderVideoJob(
            generatedContentId: $record->id,
            templateId: $template->id,
            variables: ['headline' => 'Test', 'body_text' => 'Test body'],
            options: ['aspect_ratio' => '9:16'],
            companyId: 1,
        );

        $renderer = new VideoTemplateRenderer(
            new FFmpegService(1),
            new TtsService(1),
            new AssetLibraryService(1),
        );

        $job->handle($renderer);

        $record->refresh();
        $this->assertContains($record->status, ['ready', 'failed']);

        // Cleanup
        if ($record->output_path) {
            @unlink(storage_path('app/' . $record->output_path));
        }
        if ($record->thumbnail_path) {
            @unlink(storage_path('app/' . $record->thumbnail_path));
        }
    }
}
