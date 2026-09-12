<?php

namespace App\Enums;

enum AiReportType: string
{
    case OPPORTUNITY = 'opportunity';
    case SATISFACTION = 'satisfaction';
    case REP_PERFORMANCE = 'rep_performance';

    public function label(): string
    {
        return match ($this) {
            self::OPPORTUNITY => 'Opportunity Report',
            self::SATISFACTION => 'Client Satisfaction Report',
            self::REP_PERFORMANCE => 'Sales Representative Performance Report',
        };
    }
}
