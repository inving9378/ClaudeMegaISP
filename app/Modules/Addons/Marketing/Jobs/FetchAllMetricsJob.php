<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Publication;
use App\Modules\Addons\Marketing\Services\Publishing\PostPublisherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchAllMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PostPublisherService $publisher): void
    {
        $publications = Publication::where('status', 'published')
            ->whereNotNull('pub_channel_id')
            ->whereNotNull('external_post_id')
            ->where(function ($q) {
                $q->whereNull('metrics_updated_at')
                  ->orWhere('metrics_updated_at', '<', now()->subHours(4));
            })
            ->limit(50)
            ->get();

        $count = 0;
        foreach ($publications as $pub) {
            $metrics = $publisher->fetchMetricsForPublication($pub);
            if ($metrics) $count++;
        }

        Log::info('[FetchAllMetricsJob] Actualizadas métricas', ['count' => $count]);
    }
}
