<?php

namespace App\Http\Controllers;

use App\Actions\Opportunities\WinOpportunity;
use App\Http\Requests\WinOpportunityRequest;
use App\Http\Resources\WinOpportunityResource;
use App\Models\AuditLog;
use App\Models\Opportunity;

class WinOpportunityController extends Controller
{
    public function win(WinOpportunityRequest $request, Opportunity $opportunity, WinOpportunity $winOpportunity)
    {
        $this->authorize('win', $opportunity);

        $reason = $request->validated()['reason'] ?? null;

        $result = $winOpportunity->handle($opportunity, $reason);

        $wonOpportunity = $result['opportunity'];
        $companyName = $wonOpportunity->company?->name;

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Opportunity',
            'action' => 'Won',
            'subject_type' => 'Opportunity',
            'subject_id' => (string) $wonOpportunity->id,
            'subject_name' => $wonOpportunity->title,
            'description' => "Opportunity '{$wonOpportunity->title}'"
                .($companyName ? " for company '{$companyName}'" : '')
                .' was marked as won.',
            'metadata' => [
                'company_name' => $companyName,
                'reason' => $reason,
            ],
        ]);

        if ($result['client']) {
            $client = $result['client'];

            AuditLog::log([
                ...AuditLog::actor(),
                'module' => 'Client',
                'action' => 'Created',
                'subject_type' => 'Client',
                'subject_id' => (string) $client->id,
                'subject_name' => $client->company?->name ?? "Client #{$client->id}",
                'description' => "Client for company '{$client->company?->name}' was created from a won opportunity.",
                'metadata' => [
                    'company_name' => $client->company?->name,
                    'assigned_to' => $client->assignedTo?->name,
                    'status' => $client->status?->value ?? (string) $client->status,
                    'client_since' => $client->client_since?->toDateString(),
                ],
            ]);
        }

        if ($result['lead']) {
            $lead = $result['lead'];

            AuditLog::log([
                ...AuditLog::actor(),
                'module' => 'Lead',
                'action' => 'Status Changed',
                'subject_type' => 'Lead',
                'subject_id' => (string) $lead->id,
                'subject_name' => $lead->company?->name ?? "Lead #{$lead->id}",
                'description' => 'Lead converted to client after its opportunity was won.',
                'metadata' => [
                    'from_status' => $lead->status?->value ?? (string) $lead->status,
                    'to_status' => 'converted',
                    'reason' => $reason,
                ],
            ]);
        }

        return new WinOpportunityResource($result);
    }
}
