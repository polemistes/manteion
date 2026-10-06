<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $event->isVisibleTo($user) || $user->isStaff();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return $event->isHostedBy($user);
    }

    public function delete(User $user, Event $event): bool
    {
        return $event->isHostedBy($user) || $user->isStaff();
    }

    public function comment(User $user, Event $event): bool
    {
        return $event->isVisibleTo($user) && ($event->isHostedBy($user) || $event->allow_invitee_comments);
    }

    public function invite(User $user, Event $event): bool
    {
        return $event->isVisibleTo($user) && ($event->isHostedBy($user) || $event->allow_invitee_invites);
    }
}
