<?php

namespace App\Services;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Friends in common, counted the way a profile's friends list shows them.
 */
class MutualFriends
{
    /**
     * How many of the viewer's friends each person is friends with, as their
     * profile would show it: only for people who show their friends list,
     * and only counting friends who agree to appear in such lists. Null
     * where the list is private.
     *
     * @param  Collection<int, int>  $personIds
     * @return array<int, int|null>
     */
    public static function counts(User $viewer, Collection $personIds): array
    {
        $personIds = $personIds->map(fn (mixed $id) => (int) $id)->unique()->values();

        if ($personIds->isEmpty()) {
            return [];
        }

        $showing = User::query()->whereKey($personIds)->where('show_friends_list', true)->pluck('id')->flip();
        $myFriends = User::query()->whereKey($viewer->friendIds())->where('appear_in_friends_lists', true)->pluck('id')->flip();

        $counts = $personIds->mapWithKeys(fn (int $id) => [$id => $showing->has($id) ? 0 : null])->all();

        if ($showing->isEmpty() || $myFriends->isEmpty()) {
            return $counts;
        }

        Friendship::query()
            ->accepted()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereIn('requester_id', $showing->keys())->whereIn('addressee_id', $myFriends->keys()))
                ->orWhere(fn (Builder $query) => $query->whereIn('addressee_id', $showing->keys())->whereIn('requester_id', $myFriends->keys())))
            ->get(['requester_id', 'addressee_id'])
            ->each(function (Friendship $friendship) use (&$counts, $showing, $myFriends): void {
                if ($showing->has($friendship->requester_id) && $myFriends->has($friendship->addressee_id)) {
                    $counts[$friendship->requester_id]++;
                }

                if ($showing->has($friendship->addressee_id) && $myFriends->has($friendship->requester_id)) {
                    $counts[$friendship->addressee_id]++;
                }
            });

        return $counts;
    }
}
