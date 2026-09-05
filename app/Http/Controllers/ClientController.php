<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReassignRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\UpdateClientStatusRequest;
use App\Http\Resources\ClientDetailsResource;
use App\Http\Resources\ClientResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Client::class);

        $user = $request->user();
        $clients = $this->scopeVisibleTo($user, Client::query())
            ->with(['company.primaryContact', 'assignedTo', 'surveys'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('assigned_to_id'), fn ($q) => $q->where('assigned_to_id', $request->integer('assigned_to_id')))
            ->when($request->filled('industry'), function ($q) use ($request) {
                $q->whereHas('company', fn ($c) => $c->where('industry', $request->string('industry')));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('client_since', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('client_since', '<=', $request->date('to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->whereHas('company', fn ($c) => $c->where('name', 'like', $term));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        $clients = Client::query()
            ->where('assigned_to_id', $userId)
            ->with(['company.primaryContact', 'assignedTo', 'surveys'])
            ->latest()
            ->paginate(15);

        return ClientResource::collection($clients);
    }

    public function show(Client $client)
    {
        $this->authorize('view', $client);

        $client->load([
            'company.contacts',
            'company.opportunities.assignedTo',
            'assignedTo',

            'lead',
            'communications.company',
            'communications.contact',
            'communications.user',
            'reminders.company',
            'reminders.relatedTo',
            'surveys',
            'statusHistories.user',
        ]);

        return new ClientDetailsResource($client);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $this->authorize('update', $client);

        $original = $client->only(array_keys($request->validated()));
        $client->update($request->validated());

        $changes = [];
        foreach ($original as $key => $oldValue) {
            $changes[$key] = [
                'from' => $oldValue,
                'to' => $client->{$key},
            ];
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client',
            'action' => 'Updated',
            'subject_type' => 'Client',
            'subject_id' => (string) $client->id,
            'subject_name' => $client->company?->name ?? "Client #{$client->id}",
            'description' => "Client '{$client->company?->name}' was updated.",
            'metadata' => ['changes' => $changes],
        ]);

        $client->load(['company.primaryContact', 'assignedTo', 'assignedTo.team', 'surveys']);

        return new ClientResource($client);
    }

    public function updateStatus(UpdateClientStatusRequest $request, Client $client)
    {
        $this->authorize('updateStatus', $client);

        $validated = $request->validated();
        $fromStatus = $client->status->value;

        $client->update(['status' => $validated['status']]);

        ClientStatusHistory::create([
            'client_id' => $client->id,
            'user_id' => Auth::id(),
            'from_status' => $fromStatus,
            'to_status' => $validated['status'],
            'reason' => $validated['reason'] ?? null,
        ]);

        $companyName = $client->company?->name ?? "Client #{$client->id}";

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client',
            'action' => 'Status Changed',
            'subject_type' => 'Client',
            'subject_id' => (string) $client->id,
            'subject_name' => $companyName,
            'description' => "Client status changed from '{$fromStatus}' to '{$validated['status']}'."
                .($validated['reason'] ?? null ? " Reason: {$validated['reason']}." : ''),
            'metadata' => [
                'from_status' => $fromStatus,
                'to_status' => $validated['status'],
                'reason' => $validated['reason'] ?? null,
            ],
        ]);

        $client->load([
            'company.contacts',
            'company.opportunities.assignedTo',
            'assignedTo',

            'lead',
            'communications.company',
            'communications.contact',
            'communications.user',
            'reminders.company',
            'reminders.relatedTo',
            'surveys',
            'statusHistories.user',
        ]);

        return new ClientDetailsResource($client);
    }

    public function reassign(ReassignRequest $request, Client $client)
    {
        $this->authorize('reassign', $client);

        $validated = $request->validated();
        $previousOwnerId = $client->assigned_to_id;
        $previousOwner = User::find($previousOwnerId);
        $newOwner = User::findOrFail($validated['assigned_to_id']);

        $client->update(['assigned_to_id' => $newOwner->id]);

        $companyName = $client->company?->name ?? "Client #{$client->id}";

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client',
            'action' => 'Reassigned',
            'subject_type' => 'Client',
            'subject_id' => (string) $client->id,
            'subject_name' => $companyName,
            'description' => "Client '{$companyName}' reassigned from '{$previousOwner?->name}' to '{$newOwner->name}'."
                .($validated['note'] ?? null ? " Note: {$validated['note']}." : ''),
            'metadata' => [
                'previous_owner_id' => $previousOwnerId,
                'previous_owner_name' => $previousOwner?->name,
                'new_owner_id' => $newOwner->id,
                'new_owner_name' => $newOwner->name,
                'note' => $validated['note'] ?? null,
            ],
        ]);

        $client->load(['company.contacts', 'assignedTo', 'assignedTo.team', 'surveys']);

        return new ClientResource($client);
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
