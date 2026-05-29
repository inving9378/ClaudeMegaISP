<?php

namespace App\Http\Resources\Embajadores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'phone'            => $this->phone,
            'email'            => $this->email,
            'address'          => $this->address,
            'source'           => $this->source,
            'source_label'     => $this->sourceLabel(),
            'status'           => $this->status,
            'status_label'     => $this->statusLabel(),
            'notes'            => $this->notes,
            'converted_at'     => optional($this->converted_at)->toDateString(),
            'last_contact_at'  => optional($this->last_contact_at)->toISOString(),
            'created_at'       => optional($this->created_at)->toISOString(),
            'followups_count'  => $this->whenCounted('followups'),
            'followups'        => ProspectFollowupResource::collection($this->whenLoaded('followups')),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'new'        => 'Nuevo',
            'contacted'  => 'Contactado',
            'interested' => 'Interesado',
            'quoted'     => 'Cotizado',
            'converted'  => 'Convertido',
            'lost'       => 'Perdido',
            default      => $this->status,
        };
    }

    private function sourceLabel(): string
    {
        return match ($this->source) {
            'shared_link'    => 'Enlace compartido',
            'manual'         => 'Manual',
            'contact_import' => 'Importado de agenda',
            'bulk_send'      => 'Envío masivo',
            default          => $this->source,
        };
    }
}
