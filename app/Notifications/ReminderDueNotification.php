<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class ReminderDueNotification extends Notification
{
    use Queueable;

    public function __construct(public Reminder $reminder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => 'reminder_due',
            'reminder_id' => $this->reminder->id,
            'title' => $this->reminder->title,
            'due_date' => $this->reminder->due_date?->toDateString(),
            'priority' => $this->reminder->priority?->value,
            'related_to_type' => $this->reminder->related_to_type,
            'related_to_id' => $this->reminder->related_to_id,
            'message' => "Reminder '{$this->reminder->title}' is due on {$this->reminder->due_date?->toDateString()}.",
        ]);
    }
}
