<?php

use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;

test('a user can view another users profile page with their bio and avatar', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['bio' => 'I build board games.']);

    $response = $this->actingAs($viewer)->get("/users/{$target->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('users/Show')
        ->where('profile.bio', 'I build board games.')
        ->where('profile.username', $target->username)
        ->where('friend_status', 'none')
        ->where('follow_status', 'none'));
});

test('the profile page reflects an existing friendship and follow', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => $target->id]);
    Follow::factory()->create(['follower_id' => $viewer->id, 'followed_id' => $target->id]);

    $response = $this->actingAs($viewer)->get("/users/{$target->id}");

    $response->assertInertia(fn ($page) => $page
        ->where('friend_status', 'friends')
        ->where('follow_status', 'following'));
});
