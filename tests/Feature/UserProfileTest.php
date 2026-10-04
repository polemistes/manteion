<?php

use App\Models\Event;
use App\Models\EventAttendee;
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

test('a profile page shows the owners friends list to other viewers', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $target->id, 'addressee_id' => $friend->id]);

    $response = $this->actingAs($viewer)->get("/users/{$target->id}");

    $response->assertInertia(fn ($page) => $page
        ->has('friends', 1)
        ->where('friends.0.id', $friend->id));
});

test('a profile page hides the friends list from other viewers when disabled, but not from the owner', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['show_friends_list' => false]);
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $target->id, 'addressee_id' => $friend->id]);

    $this->actingAs($viewer)->get("/users/{$target->id}")
        ->assertInertia(fn ($page) => $page->has('friends', 0));

    $this->actingAs($target)->get("/users/{$target->id}")
        ->assertInertia(fn ($page) => $page->has('friends', 1));
});

test('a friend who opted out is excluded from friends list displays', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $visibleFriend = User::factory()->create();
    $hiddenFriend = User::factory()->create(['appear_in_friends_lists' => false]);
    Friendship::factory()->accepted()->create(['requester_id' => $target->id, 'addressee_id' => $visibleFriend->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $target->id, 'addressee_id' => $hiddenFriend->id]);

    $response = $this->actingAs($viewer)->get("/users/{$target->id}");

    $response->assertInertia(fn ($page) => $page
        ->has('friends', 1)
        ->where('friends.0.id', $visibleFriend->id));
});

test('a profile lists the upcoming events of theirs the viewer may see', function () {
    $viewer = User::factory()->create();
    $owner = User::factory()->create();
    Follow::factory()->create(['follower_id' => $viewer->id, 'followed_id' => $owner->id]);

    $public = Event::factory()->public()->create(['user_id' => $owner->id, 'name' => 'Public', 'starts_at' => now()->addDays(2)]);
    $searchable = Event::factory()->searchable()->create(['user_id' => $owner->id, 'name' => 'Searchable', 'starts_at' => now()->addDays(3)]);
    $invited = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Invited', 'starts_at' => now()->addDays(4)]);
    EventAttendee::factory()->create(['event_id' => $invited->id, 'user_id' => $viewer->id]);
    Event::factory()->create(['user_id' => $owner->id, 'name' => 'Private', 'starts_at' => now()->addDays(5)]);
    Event::factory()->public()->create(['user_id' => $owner->id, 'name' => 'Over', 'starts_at' => now()->subWeek()]);
    Event::factory()->public()->create(['user_id' => User::factory()->create()->id, 'name' => 'Someone else']);

    $this->actingAs($viewer)->get("/users/{$owner->id}")
        ->assertInertia(fn ($page) => $page
            ->where('events', fn ($events) => collect($events)->pluck('id')->all() === [$public->id, $searchable->id, $invited->id])
            ->where('events.2.my_status', 'no_response'));
});

test('a profile counts the friends you have in common', function () {
    $viewer = User::factory()->create();
    $owner = User::factory()->create();
    $shared = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => $shared->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $owner->id, 'addressee_id' => $shared->id]);

    $this->actingAs($viewer)->get("/users/{$owner->id}")
        ->assertInertia(fn ($page) => $page->where('mutual_count', 1));

    $this->actingAs($owner)->get("/users/{$owner->id}")
        ->assertInertia(fn ($page) => $page->where('mutual_count', null));
});
