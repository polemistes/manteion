<?php

namespace App\Policies;

use App\Models\EventComment;
use App\Models\User;

class EventCommentPolicy
{
    public function view(User $user, EventComment $comment): bool
    {
        return $comment->event->isVisibleTo($user) || $user->isStaff();
    }

    public function delete(User $user, EventComment $comment): bool
    {
        return $user->is($comment->user) || $user->is($comment->event->owner) || $user->isStaff();
    }
}
