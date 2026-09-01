<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\AuditLog;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $reminders = Reminder::query()
            ->with([
                'company',
                'relatedTo',
            ])
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
            ])
            ->latest()
            ->paginate(15);

        return ReminderResource::collection($reminders);
    }

    public function store(StoreReminderRequest $request)
    {
        $reminder = Reminder::create(array_merge(
            $request->validated(),
            ['status' => 'pending', 'user_id' => Auth::id()]
        ));

        $reminder->load([
            'company',
            'relatedTo',
        ]);

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
            ],
        ]);

        return new ReminderResource($reminder);
    }

    public function show(Reminder $reminder)
    {
        $reminder->load([
            'company',
            'relatedTo',
        ]);

        return new ReminderResource($reminder);
    }

    public function update(Reminder $reminder)
    {
        $reminder->update([
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $reminder->load([
            'company',
            'relatedTo',
        ]);

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

        return new ReminderResource($reminder);
    }

    public function markIncomplete(Reminder $reminder)
    {
        $reminder->update([
            'status' => 'incomplete',
            'is_completed' => false,
            'completed_at' => null,
        ]);

        $reminder->load([
            'company',
            'relatedTo',
        ]);

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
}
