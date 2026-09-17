<?php

namespace App\Policies;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        // Staff need to be able to open a conversation from the moderation
        // queue to investigate a reported message, even though they aren't
        // a participant.
        return $conversation->isVisibleTo($user) || $user->isStaff();
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only participants may write; staff read access (see `view`) does not
     * extend to posting into someone else's conversation.
     */
    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->isVisibleTo($user);
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
