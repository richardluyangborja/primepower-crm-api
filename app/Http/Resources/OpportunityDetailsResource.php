<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'title' => $this->title,

            'stage' => $this->stage->value,

            'description' => $this->description,

            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'industry' => $this->company->industry,
                'address' => $this->company->address,
                'phone' => $this->company->phone,
                'email' => $this->company->email,
                'website' => $this->company->website,
            ],

            'contacts' => $this->company->contacts->map(
                fn ($contact) => [
                    'id' => $contact->id,
                    'name' => "{$contact->first_name} {$contact->last_name}",
                    'title' => $contact->title,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'is_primary' => $contact->is_primary,
                ]
            ),

            'lead' => $this->lead ? [
                'id' => $this->lead->id,
                'status' => $this->lead->status->value,
                'company' => [
                    'id' => $this->lead->company->id,
                    'name' => $this->lead->company->name,
                ],
            ] : null,

            'assigned_to' => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ],

            'estimated_contract_value' => $this->estimated_contract_value,

            'expected_close_date' => $this->expected_close_date,

            'lost_reason' => $this->lost_reason,

            'manpower_requirement' => $this->manpower_requirement,

            'created_at' => $this->created_at,

            'stage_histories' => $this->whenLoaded('stageHistories', fn () => StageHistoryResource::collection($this->stageHistories)),

            'reminders' => $this->whenLoaded('reminders', fn () => ReminderResource::collection($this->reminders)),
        ];
    }
}
