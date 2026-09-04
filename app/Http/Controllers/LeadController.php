<?php

namespace App\Http\Controllers;

use App\Actions\Leads\CreateLead;
use App\Enums\LeadStatus;
use App\Http\Requests\ReassignRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Requests\UpdateLeadStatusRequest;
use App\Http\Resources\LeadDetailsResource;
use App\Http\Resources\LeadResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        $user = $request->user();
        $leads = $this->scopeVisibleTo($user, Lead::query())
            ->with(['company.primaryContact', 'assignedTo'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('assigned_to_id'), fn ($q) => $q->where('assigned_to_id', $request->integer('assigned_to_id')))
            ->when($request->filled('industry'), function ($q) use ($request) {
                $q->whereHas('company', fn ($c) => $c->where('industry', $request->string('industry')));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($q) use ($term) {
                    $q->whereHas('company', fn ($c) => $c->where('name', 'like', $term))
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return LeadResource::collection($leads);
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        $leads = Lead::query()
            ->where('assigned_to_id', $userId)
            ->with(['company.primaryContact', 'assignedTo'])
            ->latest()
            ->paginate(15);

        return LeadResource::collection($leads);
    }

    public function store(
        StoreLeadRequest $request,
        CreateLead $createLead
    ) {
        $this->authorize('create', Lead::class);

        $lead = $createLead->handle($request->validated());
        $lead->load(['company.contacts', 'opportunities.assignedTo', 'assignedTo']);

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
        $this->authorize('view', $lead);

        $lead->load([
            'company.contacts',
            'opportunities.assignedTo',
            'assignedTo',
            'assignedTo.team',
            'statusHistories.user',
            'communications.company',
            'communications.contact',
            'communications.user',
            'reminders.company',
            'reminders.relatedTo',
        ]);

        return new LeadDetailsResource($lead);
    }

    public function update(UpdateLeadRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);

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
            'assignedTo.team',
            'statusHistories.user',
        ]);

        return new LeadResource($lead);
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead)
    {
        $this->authorize('updateStatus', $lead);

        $validated = $request->validated();
        $fromStatus = $lead->status->value;
        $targetStatus = LeadStatus::from($validated['to_status']);

        $lead->update(['status' => $validated['to_status']]);

        StatusHistory::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'from_status' => $fromStatus,
            'to_status' => $validated['to_status'],
            'reason' => $validated['reason'] ?? null,
        ]);

        $affectedReminders = collect();

        $clientCreated = null;

        if ($targetStatus === LeadStatus::CONVERTED) {
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

            $existingClient = Client::where('company_id', $lead->company_id)->first();

            if (! $existingClient) {
                $clientCreated = Client::create([
                    'company_id' => $lead->company_id,
                    'lead_id' => $lead->id,
                    'assigned_to_id' => $lead->assigned_to_id,
                    'status' => 'active',
                    'client_since' => now()->toDateString(),
                ]);
            }
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

        if ($clientCreated) {
            AuditLog::log([
                ...AuditLog::actor(),
                'module' => 'Client',
                'action' => 'Created',
                'subject_type' => 'Client',
                'subject_id' => (string) $clientCreated->id,
                'subject_name' => $companyName,
                'description' => "Client was auto-created when lead '{$companyName}' was converted.",
                'metadata' => [
                    'company_id' => $clientCreated->company_id,
                    'lead_id' => $lead->id,
                    'source' => 'lead_status_conversion',
                ],
            ]);
        }

        if ($targetStatus === LeadStatus::CONVERTED && $affectedReminders->isNotEmpty()) {
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
            'assignedTo.team',
            'statusHistories.user',
            'communications.company',
            'communications.contact',
            'communications.user',
            'reminders.company',
            'reminders.relatedTo',
        ]);

        return new LeadDetailsResource($lead);
    }

    public function reassign(ReassignRequest $request, Lead $lead)
    {
        $this->authorize('reassign', $lead);

        $validated = $request->validated();
        $previousOwnerId = $lead->assigned_to_id;
        $previousOwner = User::find($previousOwnerId);
        $newOwner = User::findOrFail($validated['assigned_to_id']);

        $lead->update(['assigned_to_id' => $newOwner->id]);

        $companyName = $lead->company?->name ?? "Lead #{$lead->id}";

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Lead',
            'action' => 'Reassigned',
            'subject_type' => 'Lead',
            'subject_id' => (string) $lead->id,
            'subject_name' => $companyName,
            'description' => "Lead '{$companyName}' reassigned from '{$previousOwner?->name}' to '{$newOwner->name}'."
                .($validated['note'] ?? null ? " Note: {$validated['note']}." : ''),
            'metadata' => [
                'previous_owner_id' => $previousOwnerId,
                'previous_owner_name' => $previousOwner?->name,
                'new_owner_id' => $newOwner->id,
                'new_owner_name' => $newOwner->name,
                'note' => $validated['note'] ?? null,
            ],
        ]);

        $lead->load(['company.contacts', 'assignedTo', 'assignedTo.team']);

        return new LeadResource($lead);
    }

    private function scopeVisibleTo(User $user, $query)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $ids = $user->visibleUserIds();

        return $query->whereIn('assigned_to_id', $ids);
    }
}
