<?php

namespace App\Http\Controllers;

use App\Http\Requests\SnoozeReminderRequest;
use App\Http\Requests\StoreReminderRequest;
use App\Http\Requests\UpdateReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\AuditLog;
use App\Models\Reminder;
use App\Models\User;
use App\Support\ReminderRecurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Reminder::class);

        $user = $request->user();
        $reminders = $this->scopeVisibleTo($user, Reminder::query())
            ->with(['company', 'relatedTo', 'user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('due_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('due_date', '<=', $request->date('to')))
            ->when($request->filled('overdue') && $request->boolean('overdue'), function ($q) {
                $q->where('is_completed', false)->whereDate('due_date', '<', now()->toDateString());
            })
            ->latest()
            ->paginate(15);

        return ReminderResource::collection($reminders);
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;
        $userName = $request->user()->name;

        $reminders = Reminder::query()
            ->where(function ($q) use ($userId, $userName) {
                $q->where('user_id', $userId)
                    ->orWhere('assigned_to_name', $userName);
            })
            ->with([
                'company',
                'relatedTo',
                'user',
            ])
            ->latest()
            ->paginate(15);

        return ReminderResource::collection($reminders);
    }

    public function team(Request $request)
    {
        $this->authorize('viewAny', Reminder::class);
        $user = $request->user();

        if (! $user->isManager() && ! $user->isAdmin()) {
            abort(403, 'Only managers and admins may view the team reminders feed.');
        }

        $teamUserIds = $user->visibleUserIds();
        $reminders = Reminder::query()
            ->whereIn('user_id', $teamUserIds)
            ->with(['company', 'relatedTo', 'user'])
            ->where('is_completed', false)
            ->orderBy('due_date')
            ->paginate(15);

        return ReminderResource::collection($reminders);
    }

    public function store(StoreReminderRequest $request)
    {
        $this->authorize('create', Reminder::class);

        $data = array_merge(
            $request->validated(),
            ['status' => 'pending', 'user_id' => Auth::id()]
        );

        $reminder = Reminder::create($data);

        $reminder->load(['company', 'relatedTo', 'user']);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Reminder',
            'action' => 'Created',
            'subject_type' => 'Reminder',
            'subject_id' => (string) $reminder->id,
            'subject_name' => $reminder->title,
            'description' => "Reminder '{$reminder->title}' was created"
                .($reminder->company?->name ? " for company '{$reminder->company->name}'" : '')
                .'.',
            'metadata' => [
                'company_name' => $reminder->company?->name,
                'related_to_type' => $reminder->related_to_type,
                'related_to_id' => $reminder->related_to_id,
                'due_date' => $reminder->due_date?->toDateString(),
                'priority' => $reminder->priority?->value ?? (string) $reminder->priority,
                'assigned_to_name' => $reminder->assigned_to_name,
                'recurrence_rule' => $reminder->recurrence_rule,
            ],
        ]);

        return new ReminderResource($reminder);
    }

    public function show(Reminder $reminder)
    {
        $this->authorize('view', $reminder);

        $reminder->load(['company', 'relatedTo', 'user']);

        return new ReminderResource($reminder);
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder)
    {
        $this->authorize('update', $reminder);

        $reminder->update($request->validated());
        $reminder->load(['company', 'relatedTo', 'user']);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Reminder',
            'action' => 'Updated',
            'subject_type' => 'Reminder',
            'subject_id' => (string) $reminder->id,
            'subject_name' => $reminder->title,
            'description' => "Reminder '{$reminder->title}' was updated.",
            'metadata' => [
                'due_date' => $reminder->due_date?->toDateString(),
                'priority' => $reminder->priority?->value ?? (string) $reminder->priority,
                'recurrence_rule' => $reminder->recurrence_rule,
            ],
        ]);

        return new ReminderResource($reminder);
    }

    public function complete(Reminder $reminder)
    {
        $this->authorize('update', $reminder);

        $reminder->update([
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $reminder->load(['company', 'relatedTo', 'user']);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Reminder',
            'action' => 'Marked Complete',
            'subject_type' => 'Reminder',
            'subject_id' => (string) $reminder->id,
            'subject_name' => $reminder->title,
            'description' => "Reminder '{$reminder->title}' was marked as complete.",
            'metadata' => [
                'company_name' => $reminder->company?->name,
                'completed_at' => $reminder->completed_at?->toDateTimeString(),
            ],
        ]);

        if ($reminder->recurrence_rule) {
            ReminderRecurrence::spawnNext($reminder);
        }

        return new ReminderResource($reminder);
    }

    public function markIncomplete(Reminder $reminder)
    {
        $this->authorize('update', $reminder);

        $reminder->update([
            'status' => 'incomplete',
            'is_completed' => false,
            'completed_at' => null,
        ]);

        $reminder->load(['company', 'relatedTo', 'user']);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Reminder',
            'action' => 'Marked Incomplete',
            'subject_type' => 'Reminder',
            'subject_id' => (string) $reminder->id,
            'subject_name' => $reminder->title,
            'description' => "Reminder '{$reminder->title}' was marked as incomplete.",
            'metadata' => [
                'company_name' => $reminder->company?->name,
            ],
        ]);

        return new ReminderResource($reminder);
    }

    public function snooze(SnoozeReminderRequest $request, Reminder $reminder)
    {
        $this->authorize('update', $reminder);

        $reminder->update([
            'due_date' => $request->date('due_date'),
            'status' => 'snoozed',
        ]);

        $reminder->load(['company', 'relatedTo', 'user']);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Reminder',
            'action' => 'Snoozed',
            'subject_type' => 'Reminder',
            'subject_id' => (string) $reminder->id,
            'subject_name' => $reminder->title,
            'description' => "Reminder '{$reminder->title}' was snoozed until {$reminder->due_date->toDateString()}.",
            'metadata' => [
                'new_due_date' => $reminder->due_date->toDateString(),
            ],
        ]);

        return new ReminderResource($reminder);
    }

    public function destroy(Reminder $reminder)
    {
        $this->authorize('delete', $reminder);

        $title = $reminder->title;
        $reminder->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Reminder',
            'action' => 'Deleted',
            'subject_type' => 'Reminder',
            'subject_id' => (string) $reminder->id,
            'subject_name' => $title,
            'description' => "Reminder '{$title}' was deleted.",
        ]);

        return response()->noContent();
    }

    private function scopeVisibleTo(User $user, $query)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $ids = $user->visibleUserIds();

        return $query->whereIn('user_id', $ids);
    }
}
