<?php

namespace App\Http\Resources;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
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

            'related_to_type' => $this->related_to_type,

            'related_to_id' => $this->related_to_id,

            'related_to_name' => $this->whenLoaded('relatedTo', function () {
                $related = $this->relatedTo;
                if ($related instanceof Lead || $related instanceof Client) {
                    return $related->company?->name ?? 'Unknown';
                }
                if ($related instanceof Opportunity) {
                    return $related->title ?? 'Unknown';
                }

                return 'Unknown';
            }),

            'related_to_status' => $this->whenLoaded('relatedTo', function () {
                $related = $this->relatedTo;
                if ($related instanceof Lead) {
                    return $related->status;
                }
                if ($related instanceof Client) {
                    return $related->status;
                }

                return null;
            }),

            'title' => $this->title,

            'description' => $this->description,

            'due_date' => $this->due_date,

            'priority' => $this->priority,

            'status' => $this->status,

            'is_completed' => $this->is_completed,

            'completed_at' => $this->completed_at,

            'assigned_to' => $this->assigned_to_name
                ? [
                    'id' => 0,
                    'name' => $this->assigned_to_name,
                ]
                : null,

            'created_at' => $this->created_at,
        ];
    }
}
