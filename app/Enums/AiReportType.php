<?php

namespace App\Enums;

enum AiReportType: string
{
    case BUSINESS_HEALTH = 'business_health';
    case REP_PERFORMANCE = 'rep_performance';

    public function label(): string
    {
        return match ($this) {
            self::BUSINESS_HEALTH => 'Business Health Report',
            self::REP_PERFORMANCE => 'Sales Performance Report',
        };
    }

    /** Map legacy stored values to current cases. */
    public static function coerce(string $value): ?self
    {
        return match ($value) {
            'opportunity', 'satisfaction' => self::BUSINESS_HEALTH,
            default => self::tryFrom($value),
        };
    }
}
