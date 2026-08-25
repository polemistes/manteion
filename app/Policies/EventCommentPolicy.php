<?php

namespace App\Policies;

use App\Models\EventComment;
use App\Models\User;

class EventCommentPolicy
{
    public function delete(User $user, EventComment $comment): bool
    {
        return $user->is($comment->user) || $user->is($comment->event->owner);
    }
}
