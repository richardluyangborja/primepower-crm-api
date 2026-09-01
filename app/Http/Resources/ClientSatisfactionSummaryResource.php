<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientSatisfactionSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $surveys = $this->surveys;
        $completedSurveys = $surveys->where('status', 'completed');
        $pendingSurveys = $surveys->where('status', 'pending');

        $averageScore = $completedSurveys->isEmpty()
            ? null
            : round($completedSurveys->avg('average_score'), 1);

        $trend = $this->calculateTrend($completedSurveys);

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
            ] : null,
            'total_surveys' => $surveys->count(),
            'completed_surveys' => $completedSurveys->count(),
            'pending_surveys' => $pendingSurveys->count(),
            'last_survey_date' => $completedSurveys->max('completed_at'),
            'average_score' => $averageScore,
            'trend' => $trend,
        ];
    }

    private function calculateTrend($completedSurveys): ?string
    {
        if ($completedSurveys->count() < 2) {
            return null;
        }

        $sorted = $completedSurveys->sortBy('completed_at')->values();
        $latest = $sorted->last()->average_score;
        $previous = $sorted->slice(-2, 1)->first()->average_score;

        if ($latest > $previous) {
            return 'up';
        } elseif ($latest < $previous) {
            return 'down';
        }
        return 'stable';
    }
}
