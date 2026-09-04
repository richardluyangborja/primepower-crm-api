<?php

namespace App\Support;

use App\Models\Reminder;
use Carbon\CarbonImmutable;

class ReminderRecurrence
{
    public static function spawnNext(Reminder $reminder): ?Reminder
    {
        if (! $reminder->recurrence_rule || ! $reminder->due_date) {
            return null;
        }

        $next = match ($reminder->recurrence_rule) {
            'daily' => $reminder->due_date->addDay(),
            'weekly' => $reminder->due_date->addWeek(),
            'monthly' => $reminder->due_date->addMonth(),
            default => null,
        };

        if (! $next) {
            return null;
        }

        return Reminder::create([
            'company_id' => $reminder->company_id,
            'related_to_type' => $reminder->related_to_type,
            'related_to_id' => $reminder->related_to_id,
            'title' => $reminder->title,
            'description' => $reminder->description,
            'due_date' => $next->toDateString(),
            'priority' => $reminder->priority,
            'status' => 'pending',
            'is_completed' => false,
            'completed_at' => null,
            'assigned_to_name' => $reminder->assigned_to_name,
            'user_id' => $reminder->user_id,
            'recurrence_rule' => $reminder->recurrence_rule,
            'recurrence_parent_id' => $reminder->recurrence_parent_id ?? $reminder->id,
        ]);
    }

    public static function advanceFor(Reminder $reminder): CarbonImmutable
    {
        return match ($reminder->recurrence_rule) {
            'daily' => $reminder->due_date->addDay(),
            'weekly' => $reminder->due_date->addWeek(),
            'monthly' => $reminder->due_date->addMonth(),
            default => $reminder->due_date,
        };
    }
}
