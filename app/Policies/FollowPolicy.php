<?php

namespace App\Policies;

use App\Enums\FollowStatus;
use App\Models\Follow;
use App\Models\User;

class FollowPolicy
{
    public function view(User $user, Follow $follow): bool
    {
        return $user->is($follow->follower) || $user->is($follow->followed);
    }

    /**
     * Approve a pending follow request — only the followed user may respond.
     */
    public function update(User $user, Follow $follow): bool
    {
        return $follow->status === FollowStatus::Pending && $user->is($follow->followed);
    }

    /**
     * Unfollow (follower), or reject/remove a follower (followed user).
     */
    public function delete(User $user, Follow $follow): bool
    {
        return $user->is($follow->follower) || $user->is($follow->followed);
    }
}
