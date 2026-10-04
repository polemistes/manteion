<?php

namespace App\Http\Controllers;

use App\Enums\FriendshipStatus;
use App\Events\BadgesUpdated;
use App\Http\Requests\StoreFriendshipRequest;
use App\Http\Requests\UpdateFriendshipRequest;
use App\Models\Friendship;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Services\ActivityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FriendshipController extends Controller
{
    public function store(StoreFriendshipRequest $request): RedirectResponse
    {
        // If the addressee once asked this user and was declined, that old
        // request is superseded by this one rather than left dangling.
        Friendship::query()
            ->where('requester_id', $request->integer('user_id'))
            ->where('addressee_id', $request->user()->getKey())
            ->where('status', FriendshipStatus::Declined)
            ->delete();

        $friendship = Friendship::create([
            'requester_id' => $request->user()->getKey(),
            'addressee_id' => $request->integer('user_id'),
            'status' => FriendshipStatus::Pending,
        ]);

        $friendship->addressee->notify(
            (new FriendRequestReceived($friendship))->locale($friendship->addressee->locale ?? 'en')
        );

        $this->updateBadges($friendship);

        return back();
    }

    public function update(UpdateFriendshipRequest $request, Friendship $friendship): RedirectResponse
    {
        $accepted = $request->input('action') === 'accept';

        $friendship->update([
            'status' => $accepted ? FriendshipStatus::Accepted : FriendshipStatus::Declined,
        ]);

        // Declining is never announced: the requester keeps seeing the
        // request as sent.
        if ($accepted) {
            $friendship->requester->notify(
                (new FriendRequestAccepted($friendship))->locale($friendship->requester->locale ?? 'en')
            );
        }

        // An answered request has been seen, wherever it was answered.
        ActivityFeed::markRead($request->user(), 'user', $friendship->requester_id);

        $this->updateBadges($friendship);

        return back();
    }

    public function destroy(Friendship $friendship): RedirectResponse
    {
        Gate::authorize('delete', $friendship);

        $friendship->delete();

        // A cancelled request takes its notification with it.
        if ($friendship->status !== FriendshipStatus::Accepted) {
            ActivityFeed::withdraw($friendship->addressee, FriendRequestReceived::class, 'friendship_id', $friendship->id);
        }

        $this->updateBadges($friendship);

        return back();
    }

    /**
     * An open request counts on the addressee's badge, so creating,
     * answering or withdrawing one updates both sides' devices.
     */
    private function updateBadges(Friendship $friendship): void
    {
        BadgesUpdated::dispatch($friendship->requester_id);
        BadgesUpdated::dispatch($friendship->addressee_id);
    }
}
