<?php

namespace App\Http\Controllers;

use App\Enums\FriendshipStatus;
use App\Http\Requests\StoreFriendshipRequest;
use App\Http\Requests\UpdateFriendshipRequest;
use App\Models\Friendship;
use App\Notifications\FriendRequestReceived;
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

        return back();
    }

    public function update(UpdateFriendshipRequest $request, Friendship $friendship): RedirectResponse
    {
        $friendship->update([
            'status' => $request->input('action') === 'accept'
                ? FriendshipStatus::Accepted
                : FriendshipStatus::Declined,
        ]);

        return back();
    }

    public function destroy(Friendship $friendship): RedirectResponse
    {
        Gate::authorize('delete', $friendship);

        $friendship->delete();

        return back();
    }
}
