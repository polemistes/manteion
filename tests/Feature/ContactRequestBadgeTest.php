<?php

use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;

test('the pending contact requests count includes incoming friend and follow requests', function () {
    $user = User::factory()->create();
    $friendRequester = User::factory()->create();
    $follower = User::factory()->create();

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn ($page) => $page->where('pendingContactRequestsCount', 0));

    Friendship::factory()->create([
        'requester_id' => $friendRequester->id,
        'addressee_id' => $user->id,
    ]);
    Follow::factory()->pending()->create([
        'follower_id' => $follower->id,
        'followed_id' => $user->id,
    ]);

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn ($page) => $page->where('pendingContactRequestsCount', 2));
});

test('outgoing requests do not count toward the pending contact requests badge', function () {
    $user = User::factory()->create();
    $friendTarget = User::factory()->create();
    $followTarget = User::factory()->create();

    Friendship::factory()->create([
        'requester_id' => $user->id,
        'addressee_id' => $friendTarget->id,
    ]);
    Follow::factory()->pending()->create([
        'follower_id' => $user->id,
        'followed_id' => $followTarget->id,
    ]);

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn ($page) => $page->where('pendingContactRequestsCount', 0));
});
