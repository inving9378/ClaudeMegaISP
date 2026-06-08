<?php

namespace App\Modules\Addons\WarRoom\Jobs;

use App\Modules\Addons\WarRoom\Services\InsightsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 30;

    public function __construct(
        public readonly string $viewKey,
        public readonly string $period,
    ) {}

    public function handle(InsightsService $service): void
    {
        $service->generate($this->viewKey, $this->period);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('RefreshInsightsJob failed', [
            'view'   => $this->viewKey,
            'period' => $this->period,
            'err'    => $e->getMessage(),
        ]);
    }
}
