<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class EscalationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?int $reminderId = null,
        public ?string $relatedToType = null,
        public ?int $relatedToId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $data = [
            'type' => 'escalation',
            'title' => $this->title,
            'message' => $this->message,
        ];

        if ($this->reminderId) {
            $data['reminder_id'] = $this->reminderId;
        }
        if ($this->relatedToType) {
            $data['related_to_type'] = $this->relatedToType;
        }
        if ($this->relatedToId) {
            $data['related_to_id'] = $this->relatedToId;
        }

        return new DatabaseMessage($data);
    }
}
