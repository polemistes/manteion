<?php

namespace App\Policies;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function addParticipant(User $user, Conversation $conversation): bool
    {
        return $conversation->type === ConversationType::Group && $conversation->isVisibleTo($user);
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $conversation->type === ConversationType::Group && $conversation->isVisibleTo($user);
    }
}
