<?php

namespace App\Http\Controllers;

use App\Actions\Leads\CreateLead;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Requests\UpdateLeadStatusRequest;
use App\Http\Resources\LeadDetailsResource;
use App\Http\Resources\LeadResource;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $leads = Lead::query()
            ->with([
                'company.primaryContact',
                'assignedTo',
            ])
            ->latest()
            ->paginate(15);

        return LeadResource::collection($leads);
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        $leads = Lead::query()
            ->where('assigned_to_id', $userId)
            ->with([
                'company.primaryContact',
                'assignedTo',
            ])
            ->latest()
            ->paginate(15);

        return LeadResource::collection($leads);
    }

    public function store(
        StoreLeadRequest $request,
        CreateLead $createLead
    ) {
        $lead = $createLead->handle(
            $request->validated()
        );

        $lead->load([
            'company.contacts',
            'opportunities.assignedTo',
            'assignedTo',
        ]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Lead',
            'action' => 'Created',
            'subject_type' => 'Lead',
            'subject_id' => (string) $lead->id,
            'subject_name' => $lead->company?->name ?? "Lead #{$lead->id}",
            'description' => "Lead for company '{$lead->company?->name}' was created.",
            'metadata' => [
                'company_id' => $lead->company?->id,
                'company_name' => $lead->company?->name,
                'assigned_to' => $lead->assignedTo?->name,
                'source' => $lead->source,
                'notes' => $lead->notes,
            ],
        ]);

        return new LeadDetailsResource($lead);
    }

    public function show(Lead $lead)
    {
        $lead->load([
            'company.contacts',
            'opportunities.assignedTo',
            'assignedTo',
            'statusHistories.user',
            'communications.company',
            'communications.contact',
            'communications.user',
            'reminders.company',
            'reminders.relatedTo',
        ]);

        return new LeadDetailsResource($lead);
    }

    public function update(
        UpdateLeadRequest $request,
        Lead $lead
    ) {
        $original = $lead->only(array_keys($request->validated()));
        $lead->update($request->validated());

        $changes = [];
        foreach ($original as $key => $oldValue) {
            $changes[$key] = [
                'from' => $oldValue,
                'to' => $lead->{$key},
            ];
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Lead',
            'action' => 'Updated',
            'subject_type' => 'Lead',
            'subject_id' => (string) $lead->id,
            'subject_name' => $lead->company?->name ?? "Lead #{$lead->id}",
            'description' => "Lead for company '{$lead->company?->name}' was updated.",
            'metadata' => ['changes' => $changes],
        ]);

        $lead->load([
            'company.contacts',
            'assignedTo',
            'statusHistories.user',
        ]);

        return new LeadResource($lead);
    }

    public function updateStatus(
        UpdateLeadStatusRequest $request,
        Lead $lead
    ) {
        $validated = $request->validated();

        $fromStatus = $lead->status->value;

        $lead->update([
            'status' => $validated['to_status'],
        ]);

        StatusHistory::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'from_status' => $fromStatus,
            'to_status' => $validated['to_status'],
            'reason' => $validated['reason'] ?? null,
        ]);

        $affectedReminders = collect();

        if ($validated['to_status'] === 'converted') {
            $affectedReminders = Reminder::where('related_to_type', 'lead')
                ->where('related_to_id', $lead->id)
                ->where('status', 'pending')
                ->get();

            Reminder::where('related_to_type', 'lead')
                ->where('related_to_id', $lead->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'incomplete',
                    'is_completed' => false,
                    'completed_at' => null,
                ]);
        }

        $companyName = $lead->company?->name ?? "Lead #{$lead->id}";

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Lead',
            'action' => 'Status Changed',
            'subject_type' => 'Lead',
            'subject_id' => (string) $lead->id,
            'subject_name' => $companyName,
            'description' => "Lead status changed from '{$fromStatus}' to '{$validated['to_status']}'."
                .($validated['reason'] ?? null ? " Reason: {$validated['reason']}." : ''),
            'metadata' => [
                'from_status' => $fromStatus,
                'to_status' => $validated['to_status'],
                'reason' => $validated['reason'] ?? null,
            ],
        ]);

        if ($validated['to_status'] === 'converted' && $affectedReminders->isNotEmpty()) {
            AuditLog::log([
                ...AuditLog::actor(),
                'module' => 'Reminder',
                'action' => 'Marked Incomplete',
                'subject_type' => 'Lead',
                'subject_id' => (string) $lead->id,
                'subject_name' => $companyName,
                'description' => "Lead converted to client; {$affectedReminders->count()} pending reminder(s) marked incomplete.",
                'metadata' => [
                    'reason' => 'Lead conversion',
                    'affected_reminders' => $affectedReminders
                        ->map(fn ($reminder) => [
                            'id' => $reminder->id,
                            'title' => $reminder->title,
                        ])
                        ->toArray(),
                ],
            ]);
        }

        $lead->load([
            'company.contacts',
            'opportunities.assignedTo',
            'assignedTo',
            'statusHistories.user',
            'communications.company',
            'communications.contact',
            'communications.user',
            'reminders.company',
            'reminders.relatedTo',
        ]);

        return new LeadDetailsResource($lead);
    }
}
