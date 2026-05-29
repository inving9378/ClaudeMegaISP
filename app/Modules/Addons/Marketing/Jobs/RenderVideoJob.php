<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Services\Video\VideoTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenderVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 600;

    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(
        public readonly int   $generatedContentId,
        public readonly int   $templateId,
        public readonly array $variables  = [],
        public readonly array $options    = [],
        public readonly int   $companyId  = 1,
    ) {}

    public function handle(VideoTemplateRenderer $renderer): void
    {
        $record = GeneratedContent::find($this->generatedContentId);

        if (!$record) {
            Log::channel('marketing')->error("RenderVideoJob: GeneratedContent #{$this->generatedContentId} not found");
            return;
        }

        $template = VideoTemplate::find($this->templateId);

        if (!$template) {
            $this->fail("VideoTemplate #{$this->templateId} not found");
            return;
        }

        try {
            $record->update([
                'status'         => 'generating',
                'render_stage'   => 'starting',
                'render_progress' => 0,
                'render_log'     => [],
            ]);

            $aspectRatio = $this->options['aspect_ratio'] ?? '9:16';
            $result      = $renderer->render($template, $this->variables, $aspectRatio, $this->companyId);

            if ($result['success']) {
                $record->update([
                    'status'              => 'ready',
                    'render_stage'        => 'done',
                    'render_progress'     => 100,
                    'output_path'         => $result['output_path'] ?? null,
                    'thumbnail_path'      => $result['thumbnail_path'] ?? null,
                    'generation_metadata' => array_merge(
                        $record->generation_metadata ?? [],
                        [
                            'duration_sec' => $result['duration_sec'] ?? 0,
                            'file_size'    => $result['file_size']    ?? 0,
                            'resolution'   => $result['resolution']   ?? '',
                            'tts_cost_usd' => $result['tts_cost_usd'] ?? 0,
                            'render_ms'    => $result['render_ms']    ?? 0,
                        ]
                    ),
                ]);

                Log::channel('marketing')->info("RenderVideoJob done: GC#{$this->generatedContentId} template#{$this->templateId}", [
                    'duration_sec' => $result['duration_sec'] ?? 0,
                    'render_ms'    => $result['render_ms']    ?? 0,
                ]);
            } else {
                $this->markFailed($record, $result['error'] ?? 'Unknown render error');
            }
        } catch (\Throwable $e) {
            Log::channel('marketing')->error("RenderVideoJob exception: " . $e->getMessage(), [
                'gc_id'       => $this->generatedContentId,
                'template_id' => $this->templateId,
                'trace'       => $e->getTraceAsString(),
            ]);

            $this->markFailed($record, $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $record = GeneratedContent::find($this->generatedContentId);
        if ($record) {
            $this->markFailed($record, $exception->getMessage());
        }

        Log::channel('marketing')->error("RenderVideoJob permanently failed: GC#{$this->generatedContentId}", [
            'error' => $exception->getMessage(),
        ]);
    }

    protected function markFailed(GeneratedContent $record, string $reason): void
    {
        $log = $record->render_log ?? [];
        $log[] = ['ts' => now()->toIso8601String(), 'error' => $reason];

        $record->update([
            'status'       => 'failed',
            'render_stage' => 'error',
            'render_log'   => $log,
        ]);
    }
}
