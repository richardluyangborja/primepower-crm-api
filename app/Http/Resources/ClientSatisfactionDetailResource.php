<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientSatisfactionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $surveys = $this->surveys;
        $completedSurveys = $surveys->where('status', 'completed');

        $averageScore = $completedSurveys->isEmpty()
            ? null
            : round($completedSurveys->avg('average_score'), 1);

        return [
            'id' => $this->id,
            'company' => [
                'name' => $this->company->name,
                'industry' => $this->company->industry,
                'address' => $this->company->address,
                'phone' => $this->company->phone,
                'website' => $this->company->website,
            ],
            'primary_contact' => $this->company->primaryContact ? [
                'name' => $this->company->primaryContact->full_name,
                'title' => $this->company->primaryContact->title,
                'email' => $this->company->primaryContact->email,
                'phone' => $this->company->primaryContact->phone,
            ] : null,
            'total_surveys' => $surveys->count(),
            'completed_surveys' => $completedSurveys->count(),
            'pending_surveys' => $surveys->where('status', 'pending')->count(),
            'average_score' => $averageScore,
            'at_risk' => (bool) $this->at_risk,
            'at_risk_reason' => $this->at_risk_reason,
            'surveys' => ClientSurveyResource::collection($surveys),
        ];
    }
}
