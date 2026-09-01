<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunitySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'title' => $this->title,

            'stage' => $this->stage->value,

            'estimated_contract_value' => $this->estimated_contract_value,

            'expected_close_date' => $this->expected_close_date,

            'assigned_to' => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ],
        ];
    }
}
