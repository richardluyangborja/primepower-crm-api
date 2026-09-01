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
}
