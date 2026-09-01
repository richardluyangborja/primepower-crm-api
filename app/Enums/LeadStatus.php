<?php

namespace App\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case QUALIFIED = 'qualified';
    case DISQUALIFIED = 'disqualified';
    case CONVERTED = 'converted';

    public function validTransitions(): array
    {
        return match ($this) {
            self::NEW => [
                self::QUALIFIED,
                self::DISQUALIFIED,
            ],
            self::QUALIFIED => [
                self::DISQUALIFIED,
            ],
            self::DISQUALIFIED => [
                self::QUALIFIED,
            ],
            self::CONVERTED => [],
        };
    }
}
