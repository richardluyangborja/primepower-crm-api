<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientSurveySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'average_score' => $this->average_score,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
