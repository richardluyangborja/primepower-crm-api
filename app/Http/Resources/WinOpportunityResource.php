<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WinOpportunityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'message' => 'Opportunity marked as won.',
            'data' => [
                'opportunity' => new OpportunityDetailsResource($this->resource['opportunity']),
                'client' => $this->resource['client'] ? new ClientResource($this->resource['client']) : null,
                'lead' => $this->resource['lead'] ? [
                    'id' => $this->resource['lead']->id,
                    'status' => $this->resource['lead']->status->value,
                    'source' => $this->resource['lead']->source,
                    'company' => [
                        'id' => $this->resource['lead']->company->id,
                        'name' => $this->resource['lead']->company->name,
                        'industry' => $this->resource['lead']->company->industry,
                    ],
                    'sales_representative' => [
                        'id' => $this->resource['lead']->assignedTo->id,
                        'name' => $this->resource['lead']->assignedTo->name,
                    ],
                    'created_at' => $this->resource['lead']->created_at,
                    'status_histories' => StatusHistoryResource::collection($this->resource['lead']->statusHistories),
                ] : null,
            ],
        ];
    }
}
