<?php

namespace App\Modules\Addons\Manual\Jobs;

use App\Modules\Addons\Manual\Services\ManualGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegenerateManualSectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    public function __construct(public string $slug) {}

    public function handle(ManualGeneratorService $service): void
    {
        try {
            $result = $service->generate($this->slug);
            Log::info('[Manual] Regeneración por activación completada (job)', [
                'slug'      => $this->slug,
                'generated' => $result['generated'] ?? 0,
                'errors'    => $result['errors'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('[Manual] Falló regeneración por activación (job): ' . $e->getMessage(), [
                'slug' => $this->slug,
            ]);
        }
    }
}
