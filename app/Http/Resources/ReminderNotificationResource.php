<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ReminderNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewedAt = $this->viewed_at ?? null;

        return [
            'id' => (string) $this->id,
            'type' => 'reminder_overdue',
            'title' => $this->title,
            'message' => "Reminder '{$this->title}' is overdue (due {$this->due_date?->toDateString()}).",
            'reminder_id' => $this->id,
            'related_to_type' => $this->related_to_type,
            'related_to_id' => $this->related_to_id,
            'due_date' => $this->due_date?->toDateString(),
            'priority' => $this->priority?->value ?? (string) $this->priority,
            'read_at' => $viewedAt ? Carbon::parse($viewedAt)->toIso8601String() : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
