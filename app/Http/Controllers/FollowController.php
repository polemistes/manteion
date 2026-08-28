<?php

namespace App\Http\Controllers;

use App\Enums\FollowStatus;
use App\Http\Requests\StoreFollowRequest;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewFollower;
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

        return back();
    }

    public function update(Follow $follow): RedirectResponse
    {
        Gate::authorize('update', $follow);

        $follow->update(['status' => FollowStatus::Accepted]);

        return back();
    }

    public function destroy(Follow $follow): RedirectResponse
    {
        Gate::authorize('delete', $follow);

        $follow->delete();

        return back();
    }
}
