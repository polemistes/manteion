<?php

namespace App\Http\Controllers;

use App\Enums\FollowStatus;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();

        $friendship = Friendship::query()
            ->where(fn ($query) => $query->where('requester_id', $viewer->id)->where('addressee_id', $user->id))
            ->orWhere(fn ($query) => $query->where('requester_id', $user->id)->where('addressee_id', $viewer->id))
            ->first();

        $friendStatus = $friendship?->statusFor($viewer) ?? 'none';

        $follow = Follow::query()
            ->where('follower_id', $viewer->id)
            ->where('followed_id', $user->id)
            ->first();

        $followStatus = match (true) {
            $follow === null => 'none',
            $follow->status === FollowStatus::Accepted => 'following',
            default => 'pending',
        };

        $showFriends = $viewer->is($user) || $user->show_friends_list;
        $friends = $showFriends
            ? $user->friends()->filter(fn (User $friend) => $friend->appear_in_friends_lists)->values()
            : collect();

        return Inertia::render('users/Show', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'bio' => $user->bio,
                'avatar_url' => $user->avatar_url,
            ],
            'is_self' => $viewer->is($user),
            'friend_status' => $friendStatus,
            'friendship_id' => $friendStatus === 'none' ? null : $friendship?->id,
            'follow_status' => $followStatus,
            'follow_id' => $follow?->id,
            'friends' => $friends->map(fn (User $friend) => [
                'id' => $friend->id,
                'name' => $friend->name,
                'username' => $friend->username,
                'avatar_url' => $friend->avatar_url,
            ])->all(),
        ]);
    }
}
