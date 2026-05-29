<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Lead;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\LeadScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScoreLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    public function __construct(public int $leadId) {}

    public function handle(LeadScoringService $scorer): void
    {
        $lead = Lead::find($this->leadId);
        if (!$lead) {
            return;
        }

        if (Setting::get('lead_scoring_enabled', $lead->company_id) !== 'true') {
            return;
        }

        $result = $scorer->scoreLead($lead);

        $lead->update([
            'score'        => $result->score,
            'score_reason' => $result->reason,
            'tags'         => $result->tags,
        ]);
    }
}
