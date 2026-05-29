<?php

namespace App\Console\Commands\Active;

use App\Models\Marketing\Publication;
use App\Modules\Addons\Marketing\Jobs\PublishPostJob;
use Illuminate\Console\Command;

class MarketingPublishDueCommand extends Command
{
    protected $signature   = 'marketing:publish-due';
    protected $description = 'Despacha jobs para publicaciones programadas cuyo scheduled_for <= now';

    public function handle(): int
    {
        $due = Publication::where('status', 'queued')
            ->where(function ($q) {
                $q->whereNull('scheduled_for')
                  ->orWhere('scheduled_for', '<=', now());
            })
            ->get();

        $dispatched = 0;
        foreach ($due as $pub) {
            PublishPostJob::dispatch($pub);
            $dispatched++;
        }

        if ($dispatched > 0) {
            $this->info("Despachadas {$dispatched} publicaciones.");
        }

        return 0;
    }
}
