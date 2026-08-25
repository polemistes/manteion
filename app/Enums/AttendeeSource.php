<?php

namespace App\Enums;

enum AttendeeSource: string
{
    case Invited = 'invited';
    case Follower = 'follower';
}
