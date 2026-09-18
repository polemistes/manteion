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
 * leaflets marked for friends or friends-and-followers, and followed
 * users' leaflets marked for friends-and-followers.
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

        return Leaflet::query()
            ->with(['owner', 'reactions', 'responses.user', 'responses.reactions'])
            ->where(function (Builder $query) use ($viewerId, $friendIds) {
                $query->where('user_id', $viewerId)
                    ->orWhere('visibility', LeafletVisibility::Official)
                    ->orWhere(fn (Builder $query) => $query
                        ->whereIn('visibility', [LeafletVisibility::Friends->value, LeafletVisibility::FriendsAndFollowers->value])
                        ->whereIn('user_id', $friendIds))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('visibility', LeafletVisibility::FriendsAndFollowers)
                        ->whereIn('user_id', Follow::query()
                            ->select('followed_id')
                            ->where('follower_id', $viewerId)
                            ->where('status', FollowStatus::Accepted)));
            })
            ->when($scope !== null, fn (Builder $query) => $query->where('visibility', $scope))
            ->orderByDesc('id');
    }
}
