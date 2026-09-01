<?php

namespace App\Enums;

enum ClientSurveyStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';
}
