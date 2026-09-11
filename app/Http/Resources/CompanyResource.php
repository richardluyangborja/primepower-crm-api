<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $salesRepresentative = null;

        // NOTE: do not gate this on whenLoaded('client') — when the company
        // is lead-only the client relationship loads to null and whenLoaded
        // returns early, so the rep would never resolve. Compute it directly.
        if ($this->relationLoaded('client') || $this->relationLoaded('leads')) {
            if ($this->client && $this->client->assignedTo) {
                $salesRepresentative = [
                    'id' => $this->client->assignedTo->id,
                    'name' => $this->client->assignedTo->name,
                ];
            } elseif ($this->leads->isNotEmpty() && $this->leads->first()->assignedTo) {
                $salesRepresentative = [
                    'id' => $this->leads->first()->assignedTo->id,
                    'name' => $this->leads->first()->assignedTo->name,
                ];
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'is_client' => (bool) $this->client,
            'contacts' => $this->whenLoaded('contacts', function () {
                return $this->contacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => "{$c->first_name} {$c->last_name}",
                    'title' => $c->title,
                ]);
            }),

            'leads' => $this->whenLoaded('leads', function () {
                return $this->leads->map(fn ($lead) => [
                    'id' => $lead->id,
                    'status' => $lead->status->value,
                    'company_name' => $lead->company->name,
                ]);
            }),

            'client' => $this->whenLoaded('client', function () {
                return $this->client ? [
                    'id' => $this->client->id,
                    'status' => $this->client->status,
                    'company_name' => $this->client->company->name,
                ] : null;
            }),

            'sales_representative' => $salesRepresentative,
        ];
    }
}
