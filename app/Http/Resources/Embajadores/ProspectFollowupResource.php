<?php

namespace App\Http\Resources\Embajadores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectFollowupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'action'           => $this->action,
            'notes'            => $this->notes,
            'next_action_date' => optional($this->next_action_date)->toDateString(),
            'created_at'       => optional($this->created_at)->toISOString(),
        ];
    }
}
