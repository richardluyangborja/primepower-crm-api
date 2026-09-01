<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommunicationRequest;
use App\Http\Resources\CommunicationResource;
use App\Models\AuditLog;
use App\Models\Communication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunicationController extends Controller
{
    public function index(Request $request)
    {
        $communications = Communication::query()
            ->with([
                'company',
                'contact',
                'user',
            ])
            ->latest()
            ->paginate(15);

        return CommunicationResource::collection($communications);
    }

    public function mine(Request $request)
    {
        $communications = Communication::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'company',
                'contact',
                'user',
            ])
            ->latest()
            ->paginate(15);

        return CommunicationResource::collection($communications);
    }

    public function store(StoreCommunicationRequest $request)
    {
        $communication = Communication::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        $communication->load([
            'company',
            'contact',
            'lead',
            'client',
            'user',
        ]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Communication',
            'action' => 'Created',
            'subject_type' => 'Communication',
            'subject_id' => (string) $communication->id,
            'subject_name' => $communication->subject,
            'description' => "Communication '{$communication->subject}' was logged"
                .($communication->company?->name ? " for company '{$communication->company->name}'" : '')
                .'.',
            'metadata' => [
                'type' => $communication->type?->value ?? (string) $communication->type,
                'direction' => $communication->direction?->value ?? (string) $communication->direction,
                'company_name' => $communication->company?->name,
                'contact_name' => $communication->contact
                    ? "{$communication->contact->first_name} {$communication->contact->last_name}"
                    : null,
                'lead_id' => $communication->lead_id,
                'client_id' => $communication->client_id,
                'scheduled_at' => $communication->scheduled_at?->toDateTimeString(),
                'duration_minutes' => $communication->duration_minutes,
            ],
        ]);

        return new CommunicationResource($communication);
    }

    public function show(Communication $communication)
    {
        $communication->load([
            'company',
            'lead',
            'client',
            'contact',
            'user',
        ]);

        return new CommunicationResource($communication);
    }
}
