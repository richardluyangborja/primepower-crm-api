<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class SatisfactionAtRiskNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public int $clientId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => 'satisfaction_at_risk',
            'title' => $this->title,
            'message' => $this->message,
            'client_id' => $this->clientId,
            'related_to_type' => 'Client',
            'related_to_id' => $this->clientId,
        ]);
    }
}
