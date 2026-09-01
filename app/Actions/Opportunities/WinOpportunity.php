<?php

namespace App\Actions\Opportunities;

use App\Enums\ClientStatus;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\StageHistory;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WinOpportunity
{
    public function handle(Opportunity $opportunity, ?string $reason = null): array
    {
        if ($opportunity->stage === OpportunityStage::WON) {
            abort(409, 'Opportunity is already marked as won.');
        }

        if ($opportunity->stage === OpportunityStage::LOST) {
            abort(409, 'Cannot mark a lost opportunity as won.');
        }

        if ($opportunity->stage !== OpportunityStage::CONTRACT_PROCESSING) {
            abort(409, 'Only opportunities in Contract Processing can be marked as won.');
        }

        $opportunity->load('company.client');

        $result = DB::transaction(function () use ($opportunity, $reason) {
            $fromStage = $opportunity->stage->value;

            $opportunity->update([
                'stage' => OpportunityStage::WON,
            ]);

            StageHistory::create([
                'opportunity_id' => $opportunity->id,
                'user_id' => Auth::id(),
                'from_stage' => $fromStage,
                'to_stage' => OpportunityStage::WON->value,
                'reason' => $reason,
            ]);

            $client = null;

            if (! $opportunity->company->client) {
                $assignedToId = $opportunity->lead_id
                    ? Lead::findOrFail($opportunity->lead_id)->assigned_to_id
                    : $opportunity->assigned_to_id;

                $client = Client::create([
                    'company_id' => $opportunity->company_id,
                    'assigned_to_id' => $assignedToId,
                    'status' => ClientStatus::ACTIVE,
                    'client_since' => now()->toDateString(),
                ]);
            }

            if ($opportunity->lead_id) {
                $lead = Lead::findOrFail($opportunity->lead_id);
                $fromStatus = $lead->status->value;

                $lead->update([
                    'status' => LeadStatus::CONVERTED,
                ]);

                StatusHistory::create([
                    'lead_id' => $lead->lead_id,
                    'user_id' => Auth::id(),
                    'from_status' => $fromStatus,
                    'to_status' => LeadStatus::CONVERTED->value,
                    'reason' => $reason,
                ]);
            }

            return [
                'opportunity' => $opportunity->fresh(['company', 'lead', 'assignedTo']),
                'client' => $client,
                'lead' => $opportunity->lead_id ? Lead::with('statusHistories.user')->find($opportunity->lead_id) : null,
            ];
        });

        return $result;
    }
}
