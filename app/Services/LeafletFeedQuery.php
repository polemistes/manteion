<?php

namespace App\Services;

use App\Enums\FollowStatus;
use App\Enums\LeafletVisibility;
use App\Models\Follow;
use App\Models\Leaflet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The leaflets a viewer may read: their own, every official one, friends'
 * leaflets marked for friends or friends-and-followers, followed users'
 * leaflets marked for friends-and-followers, everything from the
 * organizers they speak for, and followed organizers' leaflets marked for
 * followers.
 */
class LeafletFeedQuery
{
    public function __construct(private User $viewer) {}

    /**
     * @return Builder<Leaflet>
     */
    public function visible(?LeafletVisibility $scope = null): Builder
    {
        $viewerId = $this->viewer->id;
        $friendIds = $this->viewer->friendIds()->all();
        $organizerIds = $this->viewer->organizerIds()->all();
        $followedOrganizerIds = $this->viewer->followedOrganizerIds()->all();

        return Leaflet::query()
            ->with(['owner', 'organizer', 'reactions'])
            ->withCount('responses')
            ->where(function (Builder $query) use ($viewerId, $friendIds, $organizerIds, $followedOrganizerIds) {
                $query->where('user_id', $viewerId)
                    ->orWhere('visibility', LeafletVisibility::Official)
                    // A person's leaflets reach their friends and followers;
                    // an organizer's reach its followers, whoever wrote them.
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNull('organizer_id')
                        ->whereIn('visibility', [LeafletVisibility::Friends->value, LeafletVisibility::FriendsAndFollowers->value])
                        ->whereIn('user_id', $friendIds))
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNull('organizer_id')
                        ->where('visibility', LeafletVisibility::FriendsAndFollowers)
                        ->whereIn('user_id', Follow::query()
                            ->select('followed_id')
                            ->where('follower_id', $viewerId)
                            ->where('status', FollowStatus::Accepted)))
                    ->orWhereIn('organizer_id', $organizerIds)
                    ->orWhere(fn (Builder $query) => $query
                        ->where('visibility', LeafletVisibility::FriendsAndFollowers)
                        ->whereIn('organizer_id', $followedOrganizerIds));
            })
            ->when($scope !== null, fn (Builder $query) => $query->where('visibility', $scope))
            ->orderByDesc('id');
    }
}
