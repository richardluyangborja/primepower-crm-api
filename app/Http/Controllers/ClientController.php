<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateClientStatusRequest;
use App\Http\Resources\ClientDetailsResource;
use App\Http\Resources\ClientResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::query()
            ->with([
                'company.primaryContact',
                'assignedTo',
                'surveys',
            ])
            ->latest()
            ->paginate(15);

        return ClientResource::collection($clients);
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        $clients = Client::query()
            ->where('assigned_to_id', $userId)
            ->with([
                'company.primaryContact',
                'assignedTo',
                'surveys',
            ])
            ->latest()
            ->paginate(15);

        return ClientResource::collection($clients);
    }

    public function show(Client $client)
    {
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

    public function updateStatus(
        UpdateClientStatusRequest $request,
        Client $client
    ) {
        $validated = $request->validated();
        $fromStatus = $client->status->value;

        $client->update([
            'status' => $validated['status'],
        ]);

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
}
