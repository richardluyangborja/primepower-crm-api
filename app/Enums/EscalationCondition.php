<?php

namespace App\Enums;

enum EscalationCondition: string
{
    case INACTIVE_LEAD = 'inactive_lead';
    case INACTIVE_CLIENT = 'inactive_client';
    case STALE_OPPORTUNITY = 'stale_opportunity';
    case OVERDUE_REMINDER = 'overdue_reminder';

    /**
     * The entity type this condition evaluates against.
     */
    public function entityType(): string
    {
        return match ($this) {
            self::INACTIVE_LEAD => 'lead',
            self::INACTIVE_CLIENT => 'client',
            self::STALE_OPPORTUNITY => 'opportunity',
            self::OVERDUE_REMINDER => 'reminder',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::INACTIVE_LEAD => 'Inactive lead',
            self::INACTIVE_CLIENT => 'Inactive client',
            self::STALE_OPPORTUNITY => 'Stale opportunity',
            self::OVERDUE_REMINDER => 'Overdue reminder',
        };
    }
}
