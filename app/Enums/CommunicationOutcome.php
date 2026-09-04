<?php

namespace App\Enums;

enum CommunicationOutcome: string
{
    case INTERESTED = 'interested';
    case NOT_NOW = 'not_now';
    case NO_RESPONSE = 'no_response';
    case VOICEMAIL = 'voicemail';
    case MEETING_BOOKED = 'meeting_booked';
    case UNSUBSCRIBE = 'unsubscribe';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::INTERESTED => 'Interested',
            self::NOT_NOW => 'Not right now',
            self::NO_RESPONSE => 'No response',
            self::VOICEMAIL => 'Left voicemail',
            self::MEETING_BOOKED => 'Meeting booked',
            self::UNSUBSCRIBE => 'Unsubscribed',
            self::OTHER => 'Other',
        };
    }
}
