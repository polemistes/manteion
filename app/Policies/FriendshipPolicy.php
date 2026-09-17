<?php

namespace App\Policies;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;

class FriendshipPolicy
{
    public function view(User $user, Friendship $friendship): bool
    {
        return $user->is($friendship->requester) || $user->is($friendship->addressee);
    }

    /**
     * Accept or decline a pending request — only the addressee may respond.
     */
    public function update(User $user, Friendship $friendship): bool
    {
        return $friendship->status === FriendshipStatus::Pending && $user->is($friendship->addressee);
    }

    /**
     * Cancel an open request (requester) or remove an accepted friendship
     * (either party). A declined request still looks open to its requester,
     * so they may cancel it too.
     */
    public function delete(User $user, Friendship $friendship): bool
    {
        if ($friendship->status !== FriendshipStatus::Accepted) {
            return $user->is($friendship->requester);
        }

        return $user->is($friendship->requester) || $user->is($friendship->addressee);
    }
}
