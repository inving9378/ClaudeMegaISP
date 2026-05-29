<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Publication;
use App\Modules\Addons\Marketing\Services\Publishing\PostPublisherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public Publication $pub) {}

    public function handle(PostPublisherService $publisher): void
    {
        if ($this->pub->status !== 'published') {
            return;
        }

        $publisher->fetchMetricsForPublication($this->pub);
    }
}
