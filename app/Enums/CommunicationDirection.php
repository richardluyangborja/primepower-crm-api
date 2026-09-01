<?php

namespace App\Enums;

enum CommunicationDirection: string
{
    case INCOMING = 'incoming';
    case OUTGOING = 'outgoing';
}
