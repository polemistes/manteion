<?php

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case Administrator = 'administrator';
}
