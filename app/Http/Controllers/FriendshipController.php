<?php

namespace App\Http\Controllers;

use App\Enums\FriendshipStatus;
use App\Http\Requests\StoreFriendshipRequest;
use App\Http\Requests\UpdateFriendshipRequest;
use App\Models\Friendship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FriendshipController extends Controller
{
    public function store(StoreFriendshipRequest $request): RedirectResponse
    {
        Friendship::create([
            'requester_id' => $request->user()->getKey(),
            'addressee_id' => $request->integer('user_id'),
            'status' => FriendshipStatus::Pending,
        ]);

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
