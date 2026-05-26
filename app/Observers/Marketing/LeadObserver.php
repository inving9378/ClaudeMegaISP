<?php

namespace App\Observers\Marketing;

use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadActivity;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        LeadActivity::create([
            'lead_id'     => $lead->id,
            'user_id'     => auth()->id(),
            'type'        => 'created',
            'description' => 'Lead capturado vía ' . ($lead->source?->name ?? 'desconocido'),
            'happened_at' => $lead->captured_at ?? now(),
        ]);
    }

    public function updated(Lead $lead): void
    {
        $changes = $lead->getChanges();
        unset($changes['updated_at'], $changes['last_activity_at']);

        if (empty($changes)) {
            return;
        }

        if (isset($changes['score'])) {
            LeadActivity::create([
                'lead_id'     => $lead->id,
                'user_id'     => auth()->id(),
                'type'        => 'score_updated',
                'description' => "Score actualizado: {$lead->getOriginal('score')} → {$lead->score}",
                'metadata'    => [
                    'from'   => $lead->getOriginal('score'),
                    'to'     => $lead->score,
                    'reason' => $lead->score_reason,
                ],
                'happened_at' => now(),
            ]);
        }

        if (isset($changes['assigned_user_id'])) {
            LeadActivity::create([
                'lead_id'     => $lead->id,
                'user_id'     => auth()->id(),
                'type'        => 'assigned',
                'description' => 'Lead asignado a usuario id=' . $lead->assigned_user_id,
                'metadata'    => [
                    'from_user_id' => $lead->getOriginal('assigned_user_id'),
                    'to_user_id'   => $lead->assigned_user_id,
                ],
                'happened_at' => now(),
            ]);
        }

        if (isset($changes['status'])) {
            LeadActivity::create([
                'lead_id'     => $lead->id,
                'user_id'     => auth()->id(),
                'type'        => 'stage_changed',
                'description' => "Estado: {$lead->getOriginal('status')} → {$lead->status}",
                'metadata'    => [
                    'from' => $lead->getOriginal('status'),
                    'to'   => $lead->status,
                ],
                'happened_at' => now(),
            ]);

            if ($changes['status'] === 'won') {
                $lead->updateQuietly(['converted_at' => now()]);
                LeadActivity::create([
                    'lead_id'     => $lead->id,
                    'user_id'     => auth()->id(),
                    'type'        => 'conversion',
                    'description' => 'Lead convertido en cliente',
                    'happened_at' => now(),
                ]);
            }
        }

        $lead->updateQuietly(['last_activity_at' => now()]);
    }
}
