<?php

namespace App\Enums;

enum EscalationAction: string
{
    case CREATE_REMINDER = 'create_reminder';
    case NOTIFY_MANAGER = 'notify_manager';
    case CREATE_REMINDER_AND_NOTIFY = 'create_reminder_and_notify';

    public function createsReminder(): bool
    {
        return in_array($this, [self::CREATE_REMINDER, self::CREATE_REMINDER_AND_NOTIFY], true);
    }

    public function notifiesManager(): bool
    {
        return in_array($this, [self::NOTIFY_MANAGER, self::CREATE_REMINDER_AND_NOTIFY], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::CREATE_REMINDER => 'Create follow-up reminder',
            self::NOTIFY_MANAGER => 'Notify manager',
            self::CREATE_REMINDER_AND_NOTIFY => 'Create reminder + notify manager',
        };
    }
}
