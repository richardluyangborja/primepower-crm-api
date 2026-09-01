<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'status' => $this->status,

            'client_since' => $this->client_since,

            'notes' => $this->notes,

            'created_at' => $this->created_at,

            'recent_activity' => $this->recent_activity,

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

            'opportunities' => OpportunitySummaryResource::collection(
                $this->company->opportunities
            ),

            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id,
                'status' => $this->lead->status->value,
            ] : null),

            'status_histories' => $this->whenLoaded('statusHistories', fn () => StatusHistoryResource::collection($this->statusHistories)),

            'communications' => $this->whenLoaded('communications', fn () => CommunicationResource::collection($this->communications)),

            'reminders' => $this->whenLoaded('reminders', fn () => ReminderResource::collection($this->reminders)),

            'latest_survey' => $this->whenLoaded('surveys', fn () => $this->surveys
                ->where('status', 'completed')
                ->sortByDesc('completed_at')
                ->first()
                ? new ClientSurveySummaryResource($this->surveys
                    ->where('status', 'completed')
                    ->sortByDesc('completed_at')
                    ->first())
                : null),

            'average_score' => $this->whenLoaded('surveys', fn () => $this->surveys
                ->where('status', 'completed')
                ->avg('average_score')),

            'trend' => $this->whenLoaded('surveys', function () {
                $completed = $this->surveys
                    ->where('status', 'completed')
                    ->sortByDesc('completed_at')
                    ->values();

                if ($completed->isEmpty()) {
                    return null;
                }

                if ($completed->count() === 1) {
                    return 'stable';
                }

                $last = (float) $completed[0]->average_score;
                $previous = (float) $completed[1]->average_score;

                if ($last > $previous) {
                    return 'up';
                }

                if ($last < $previous) {
                    return 'down';
                }

                return 'stable';
            }),

            'last_survey_date' => $this->whenLoaded('surveys', fn () => $this->surveys
                ->sortByDesc('created_at')
                ->first()
                ?->created_at),

            'sales_representative' => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ],
        ];
    }
}
