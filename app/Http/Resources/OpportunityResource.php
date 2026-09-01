<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'title' => $this->title,

            'stage' => $this->stage->value,

            'description' => $this->description,

            'manpower_requirement' => $this->manpower_requirement,

            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'industry' => $this->company->industry,
            ],

            'assigned_to' => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ],

            'estimated_contract_value' => $this->estimated_contract_value,

            'expected_close_date' => $this->expected_close_date,

            'created_at' => $this->created_at,
        ];
    }
}
