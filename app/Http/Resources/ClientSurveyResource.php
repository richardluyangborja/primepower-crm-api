<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientSurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'client_id' => $this->client_id,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'average_score' => $this->average_score,
            'responses' => $this->responses,
            'respondent_name' => $this->respondent_name,
            'respondent_position' => $this->respondent_position,
            'feedback' => $this->feedback,
        ];
    }
}
