<?php

namespace App\Policies;

use App\Models\EventImage;
use App\Models\User;

class EventImagePolicy
{
    public function delete(User $user, EventImage $image): bool
    {
        return $image->event->isHostedBy($user) || $user->isStaff();
    }
}
