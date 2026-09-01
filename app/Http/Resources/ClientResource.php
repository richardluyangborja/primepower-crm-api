<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryContact = $this->company->primaryContact;

        return [
            'id' => $this->id,

            'status' => $this->status,

            'client_since' => $this->client_since,

            'notes' => $this->notes,

            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'industry' => $this->company->industry,
                'address' => $this->company->address,
                'phone' => $this->company->phone,
                'email' => $this->company->email,
                'website' => $this->company->website,
            ],

            'primary_contact' => $primaryContact
                ? [
                    'id' => $primaryContact->id,
                    'name' => "{$primaryContact->first_name} {$primaryContact->last_name}",
                    'title' => $primaryContact->title,
                ]
                : null,

            'sales_representative' => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ],

            'created_at' => $this->created_at,

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
        ];
    }
}
