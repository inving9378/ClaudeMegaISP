<?php

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'company_id'       => $this->company_id,
            'full_name'        => $this->full_name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'whatsapp'         => $this->whatsapp,
            'address'          => $this->address,
            'neighborhood'     => $this->neighborhood,
            'city'             => $this->city,
            'state'            => $this->state,
            'postal_code'      => $this->postal_code,
            'score'            => $this->score,
            'score_reason'     => $this->score_reason,
            'tags'             => $this->tags ?? [],
            'status'           => $this->status,
            'notes'            => $this->notes,
            'plan_interested_id' => $this->plan_interested_id,
            'captured_at'      => $this->captured_at?->toISOString(),
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'converted_at'     => $this->converted_at?->toISOString(),
            'lost_reason'      => $this->lost_reason,
            'created_at'       => $this->created_at?->toISOString(),
            'source' => $this->whenLoaded('source', fn () => [
                'id'    => $this->source->id,
                'code'  => $this->source->code,
                'name'  => $this->source->name,
                'color' => $this->source->color,
                'icon'  => $this->source->icon,
            ]),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'id'         => $this->assignedUser->id,
                'name'       => $this->assignedUser->name,
                'login_user' => $this->assignedUser->login_user,
            ] : null),
            'lead_form' => $this->whenLoaded('leadForm', fn () => $this->leadForm ? [
                'id'   => $this->leadForm->id,
                'name' => $this->leadForm->name,
                'slug' => $this->leadForm->slug,
            ] : null),
            'activities_count' => $this->whenCounted('activities'),
            'pipeline_stage'   => $this->when(
                $this->relationLoaded('pipelines') && $this->pipelines->isNotEmpty(),
                fn () => $this->pipelines->first()?->pivot?->stage_id
            ),
        ];
    }
}
