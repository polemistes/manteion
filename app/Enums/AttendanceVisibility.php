<?php

namespace App\Enums;

enum AttendanceVisibility: string
{
    case Everyone = 'everyone';
    case Friends = 'friends';
    case None = 'none';
}
