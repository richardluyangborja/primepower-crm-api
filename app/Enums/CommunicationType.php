<?php

namespace App\Enums;

enum CommunicationType: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case TEXT = 'text';
    case MEETING = 'meeting';
    case IN_PERSON = 'in_person';
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::PHONE => 'Phone',
            self::TEXT => 'Text',
            self::MEETING => 'Meeting',
            self::IN_PERSON => 'In Person',
            self::VIDEO => 'Video',
        };
    }
}
