<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->isVisibleTo($user) || $user->isStaff();
    }

    public function delete(User $user, Message $message): bool
    {
        return $user->is($message->sender) || $user->isStaff();
    }
}
