<?php

namespace App\Enums;

enum ReminderPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
