<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class RemoteDeployJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $timeout = 900;
    public int    $tries   = 1;

    public function __construct(
        public readonly int    $logId,
        public readonly string $version,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $releaseDate,
    ) {}

    public function handle(): void
    {
        Artisan::call('remote:deploy', [
            'logId'          => $this->logId,
            '--version'      => $this->version,
            '--title'        => $this->title,
            '--summary'      => $this->summary,
            '--release-date' => $this->releaseDate,
        ]);
    }
}
