<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case NoResponse = 'no_response';
    case Attending = 'attending';
    case Interested = 'interested';
    case NotAttending = 'not_attending';
}
