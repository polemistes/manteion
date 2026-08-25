<?php

use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;

test('search excludes the current user and matches by name or username', function () {
    $viewer = User::factory()->create(['name' => 'Viewer']);
    $match = User::factory()->create(['name' => 'Grace Hopper', 'username' => 'grace']);
    User::factory()->create(['name' => 'Someone Else', 'username' => 'someone']);

    $response = $this->actingAs($viewer)->get('/contacts/search?q=grace');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('contacts/Search')
        ->where('results.0.id', $match->id)
        ->has('results', 1));
});

test('search results reflect friendship and follow status', function () {
    $viewer = User::factory()->create();
    $friend = User::factory()->create(['name' => 'Friend Person', 'username' => 'friendperson']);
    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => $friend->id]);
    Follow::factory()->create(['follower_id' => $viewer->id, 'followed_id' => $friend->id]);

    $response = $this->actingAs($viewer)->get('/contacts/search?q=friendperson');

    $response->assertInertia(fn ($page) => $page
        ->component('contacts/Search')
        ->where('results.0.friend_status', 'friends')
        ->where('results.0.follow_status', 'following'));
});

test('an empty query returns no results', function () {
    $viewer = User::factory()->create();
    User::factory()->create();

    $response = $this->actingAs($viewer)->get('/contacts/search');

    $response->assertInertia(fn ($page) => $page->has('results', 0));
});
