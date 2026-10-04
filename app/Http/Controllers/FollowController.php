<?php

namespace App\Http\Controllers;

use App\Enums\FollowStatus;
use App\Events\BadgesUpdated;
use App\Http\Requests\StoreFollowRequest;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowRequestApproved;
use App\Notifications\NewFollower;
use App\Services\ActivityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FollowController extends Controller
{
    public function store(StoreFollowRequest $request): RedirectResponse
    {
        $target = User::findOrFail($request->integer('user_id'));

        $follow = Follow::create([
            'follower_id' => $request->user()->getKey(),
            'followed_id' => $target->getKey(),
            'status' => $target->allow_public_follow ? FollowStatus::Accepted : FollowStatus::Pending,
        ]);

        $target->notify((new NewFollower($follow))->locale($target->locale ?? 'en'));

        $this->updateBadges($follow);

        return back();
    }

    public function update(Follow $follow): RedirectResponse
    {
        Gate::authorize('update', $follow);

        $follow->update(['status' => FollowStatus::Accepted]);

        $follow->follower->notify(
            (new FollowRequestApproved($follow))->locale($follow->follower->locale ?? 'en')
        );

        $this->updateBadges($follow);

        return back();
    }

    public function destroy(Follow $follow): RedirectResponse
    {
        Gate::authorize('delete', $follow);

        $follow->delete();

        // A withdrawn or turned-down request takes its notification with it.
        if ($follow->status === FollowStatus::Pending) {
            ActivityFeed::withdraw($follow->followed, NewFollower::class, 'follow_id', $follow->id);
        }

        $this->updateBadges($follow);

        return back();
    }

    /**
     * A follow request counts on the followed user's badge, so creating,
     * approving or withdrawing one updates both sides' devices.
     */
    private function updateBadges(Follow $follow): void
    {
        BadgesUpdated::dispatch($follow->follower_id);
        BadgesUpdated::dispatch($follow->followed_id);
    }
}
