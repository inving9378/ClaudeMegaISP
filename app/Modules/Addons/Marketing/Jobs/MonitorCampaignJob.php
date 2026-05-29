<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\MultivariantCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 60;

    public function __construct(public readonly int $campaignId)
    {}

    public function handle(): void
    {
        $campaign = MultivariantCampaign::find($this->campaignId);
        if (!$campaign || in_array($campaign->status, ['ready', 'failed'])) {
            return;
        }

        $ids      = $campaign->variant_content_ids ?? [];
        $variants = GeneratedContent::whereIn('id', $ids)->get();

        $done    = $variants->whereIn('status', ['ready', 'failed'])->count();
        $total   = count($ids);
        $success = $variants->where('status', 'ready')->count();
        $failed  = $variants->where('status', 'failed')->count();

        if ($done < $total) {
            // Still running — requeue check in 3 min
            self::dispatch($this->campaignId)->delay(now()->addMinutes(3));
            return;
        }

        $status = match (true) {
            $success === $total           => 'ready',
            $success > 0 && $failed > 0  => 'partially_failed',
            default                       => 'failed',
        };

        $campaign->update([
            'status'             => $status,
            'variants_succeeded' => $success,
            'variants_failed'    => $failed,
            'completed_at'       => now(),
        ]);
    }
}
