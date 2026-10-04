<?php

use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function befriend(User $a, User $b): Friendship
{
    return Friendship::factory()->accepted()->create(['requester_id' => $a->id, 'addressee_id' => $b->id]);
}

test('friends come with their friendship, since when, and the friends in common', function () {
    $viewer = User::factory()->create();
    $friend = User::factory()->create(['name' => 'A Bjørn']);
    $shared = User::factory()->create(['name' => 'B shared']);
    $friendship = befriend($viewer, $friend);
    befriend($viewer, $shared);
    befriend($friend, $shared);

    $this->actingAs($viewer)->get('/contacts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('friends.0.id', $friend->id)
            ->where('friends.0.friendship_id', $friendship->id)
            ->where('friends.0.friends_since', $friendship->fresh()->updated_at->toIso8601String())
            ->where('friends.0.mutual_count', 1));
});

test('friends in common respect who shows their friends and who appears in lists', function () {
    $viewer = User::factory()->create();
    $private = User::factory()->create(['name' => 'A private list', 'show_friends_list' => false]);
    $open = User::factory()->create(['name' => 'B open list']);
    $hidden = User::factory()->create(['name' => 'C hidden', 'appear_in_friends_lists' => false]);
    $visible = User::factory()->create(['name' => 'D visible']);

    foreach ([$private, $open, $hidden, $visible] as $person) {
        befriend($viewer, $person);
    }

    befriend($private, $visible);
    befriend($open, $visible);
    befriend($open, $hidden);

    $this->actingAs($viewer)->get('/contacts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('friends.0.id', $private->id)
            ->where('friends.0.mutual_count', null)
            ->where('friends.1.id', $open->id)
            ->where('friends.1.mutual_count', 1));
});

test('a friend request says when it came and how many friends you share', function () {
    $viewer = User::factory()->create();
    $requester = User::factory()->create();
    $shared = User::factory()->create();
    befriend($viewer, $shared);
    befriend($requester, $shared);
    $request = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $viewer->id]);

    $this->actingAs($viewer)->get('/contacts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingFriendRequests.0.friendship_id', $request->id)
            ->where('pendingFriendRequests.0.created_at', $request->created_at->toIso8601String())
            ->where('pendingFriendRequests.0.mutual_count', 1));
});

test('followers say whether the user follows them back', function () {
    $viewer = User::factory()->create();
    $followedBack = User::factory()->create(['name' => 'A followed back']);
    $requested = User::factory()->create(['name' => 'B requested']);
    $notFollowed = User::factory()->create(['name' => 'C not followed']);

    foreach ([$followedBack, $requested, $notFollowed] as $follower) {
        Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $viewer->id]);
    }

    Follow::factory()->create(['follower_id' => $viewer->id, 'followed_id' => $followedBack->id]);
    Follow::factory()->pending()->create(['follower_id' => $viewer->id, 'followed_id' => $requested->id]);

    $this->actingAs($viewer)->get('/contacts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('followers.0.follows_back', 'following')
            ->where('followers.1.follows_back', 'pending')
            ->where('followers.2.follows_back', null)
            ->has('following', 1)
            ->has('pendingFollowRequestsSent', 1));
});
