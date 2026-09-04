<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommunicationRequest;
use App\Http\Requests\UpdateCommunicationRequest;
use App\Http\Resources\CommunicationResource;
use App\Models\AuditLog;
use App\Models\Communication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunicationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Communication::class);

        $user = $request->user();
        $communications = $this->scopeVisibleTo($user, Communication::query())
            ->with(['company', 'contact', 'user'])
            ->latest();

        $this->applyFilters($communications, $request);

        return CommunicationResource::collection($communications->paginate(15));
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        $communications = Communication::query()
            ->where('user_id', $userId)
            ->with(['company', 'contact', 'user'])
            ->latest();

        $this->applyFilters($communications, $request);

        return CommunicationResource::collection($communications->paginate(15));
    }

    private function applyFilters($query, Request $request): void
    {
        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($direction = $request->string('direction')->toString()) {
            $query->where('direction', $direction);
        }

        if ($outcome = $request->string('outcome')->toString()) {
            $query->where('outcome', $outcome);
        }

        if ($from = $request->date('from')) {
            $query->where('scheduled_at', '>=', $from);
        }

        if ($to = $request->date('to')) {
            $query->where('scheduled_at', '<=', $to);
        }

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($query) use ($q) {
                $query->where('subject', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }
    }

    public function store(StoreCommunicationRequest $request)
    {
        $this->authorize('create', Communication::class);

        $communication = Communication::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        $communication->load([
            'company',
            'lead',
            'client',
            'contact',
            'user',
        ]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Communication',
            'action' => 'Created',
            'subject_type' => 'Communication',
            'subject_id' => (string) $communication->id,
            'subject_name' => $communication->subject ?? 'Untitled',
            'description' => "Communication '{$communication->subject}' was logged"
                .($communication->company?->name ? " for company '{$communication->company->name}'" : '')
                .'.',
            'metadata' => [
                'type' => $communication->type?->value ?? (string) $communication->type,
                'direction' => $communication->direction?->value ?? (string) $communication->direction,
                'outcome' => $communication->outcome?->value,
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
        $this->authorize('view', $communication);

        $communication->load([
            'company',
            'lead',
            'client',
            'contact',
            'user',
        ]);

        return new CommunicationResource($communication);
    }

    public function update(UpdateCommunicationRequest $request, Communication $communication)
    {
        $this->authorize('update', $communication);

        $user = $request->user();
        $isPrivileged = $user->isAdmin() || $user->isManager();

        if (! $isPrivileged) {
            $graceMinutes = (int) config('crm.communication_edit_grace_minutes', 30);
            $cutoff = $communication->created_at?->copy()->addMinutes($graceMinutes);

            if ($cutoff && now()->greaterThan($cutoff)) {
                return response()->json([
                    'message' => "This communication can no longer be edited. The {$graceMinutes}-minute grace period has elapsed. Contact a manager to make changes.",
                ], 403);
            }
        }

        $original = $communication->only(array_keys($request->validated()));
        $communication->update($request->validated());

        $changes = [];
        foreach ($original as $key => $oldValue) {
            $changes[$key] = [
                'from' => $oldValue,
                'to' => $communication->{$key},
            ];
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Communication',
            'action' => 'Updated',
            'subject_type' => 'Communication',
            'subject_id' => (string) $communication->id,
            'subject_name' => $communication->subject ?? 'Untitled',
            'description' => "Communication '{$communication->subject}' was updated.",
            'metadata' => ['changes' => $changes],
        ]);

        $communication->load(['company', 'lead', 'client', 'contact', 'user']);

        return new CommunicationResource($communication);
    }

    public function destroy(Request $request, Communication $communication): JsonResponse
    {
        $this->authorize('delete', $communication);

        $user = $request->user();
        $isPrivileged = $user->isAdmin() || $user->isManager();

        if (! $isPrivileged) {
            $graceMinutes = (int) config('crm.communication_edit_grace_minutes', 30);
            $cutoff = $communication->created_at?->copy()->addMinutes($graceMinutes);

            if ($cutoff && now()->greaterThan($cutoff)) {
                return response()->json([
                    'message' => "This communication can no longer be deleted. The {$graceMinutes}-minute grace period has elapsed. Contact a manager to make changes.",
                ], 403);
            }
        }

        $subjectName = $communication->subject ?? 'Untitled';
        $communication->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Communication',
            'action' => 'Deleted',
            'subject_type' => 'Communication',
            'subject_id' => (string) $communication->id,
            'subject_name' => $subjectName,
            'description' => "Communication '{$subjectName}' was deleted.",
            'metadata' => [
                'type' => $communication->type?->value,
                'company_name' => $communication->company?->name,
            ],
        ]);

        return response()->json(null, 204);
    }

    private function scopeVisibleTo(User $user, $query)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $ids = $user->visibleUserIds();

        return $query->where(function ($q) use ($ids) {
            $q->whereIn('user_id', $ids)
                ->orWhereNull('user_id');
        });
    }
}
