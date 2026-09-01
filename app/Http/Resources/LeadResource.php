<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryContact = $this->company->primaryContact;

        return [
            'id' => $this->id,

            'status' => $this->status->value,

            'source' => $this->source,

            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'industry' => $this->company->industry,
            ],

            'primary_contact' => $primaryContact
                ? [
                    'id' => $primaryContact->id,
                    'name' => "{$primaryContact->first_name} {$primaryContact->last_name}",
                    'title' => $primaryContact->title,
                ]
                : null,

            'sales_representative' => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ],

            'created_at' => $this->created_at,

            'recent_activity' => $this->recent_activity,
        ];
    }
}
