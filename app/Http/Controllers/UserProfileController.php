<?php

namespace App\Http\Controllers;

use App\Enums\FollowStatus;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ActivityFeed;
use App\Services\EventFeedQuery;
use App\Services\MutualFriends;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    /** How many of their upcoming events the profile lists. */
    public const UPCOMING_EVENTS = 6;

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

        // What they have coming up that the viewer may see.
        $feed = new EventFeedQuery($viewer);
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();
        $events = $feed->happeningBetween($feed->ofOwner($user), $today, null)
            ->orderBy('starts_at')
            ->limit(self::UPCOMING_EVENTS)
            ->get();

        // Opening someone's profile means their friend and follow
        // notifications have been seen.
        if (ActivityFeed::seenByUser($request)) {
            ActivityFeed::markRead($viewer, 'user', $user->id);
        }

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
            'mutual_count' => $viewer->is($user) ? null : (MutualFriends::counts($viewer, collect([$user->id]))[$user->id] ?? null),
            // The organizers they speak for and chose to be listed on.
            'organizers' => $user->organizers()->wherePivot('listed', true)->orderBy('name')->get()
                ->map(fn (Organizer $organizer) => $organizer->summary())->values()->all(),
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'place' => $event->place,
                'city' => $event->city,
                'cover' => $event->images->first()?->presentation(),
                'my_status' => $event->attendees->first()?->status->value,
            ])->all(),
        ]);
    }
}
