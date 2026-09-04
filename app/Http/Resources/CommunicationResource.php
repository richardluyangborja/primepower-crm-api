<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'industry' => $this->company->industry,
            ],

            'contact' => $this->whenLoaded('contact', fn () => $this->contact
                ? [
                    'id' => $this->contact->id,
                    'name' => "{$this->contact->first_name} {$this->contact->last_name}",
                    'title' => $this->contact->title,
                ]
                : null),

            'type' => $this->type,

            'direction' => $this->direction,

            'outcome' => $this->outcome?->value,

            'outcome_label' => $this->outcome?->label(),

            'subject' => $this->subject,

            'notes' => $this->notes,

            'duration_minutes' => $this->duration_minutes,

            'scheduled_at' => $this->scheduled_at,

            'user' => $this->whenLoaded('user', fn () => $this->user
                ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ]
                : null),

            'created_at' => $this->created_at,
        ];
    }
}
