<?php

use App\Models\ContactTagPreference;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\FollowedUserPublishedEvent;
use Illuminate\Support\Facades\Notification;

test('followers are notified when a followed user publishes a public event', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);

    $this->actingAs($owner)->post('/events', [
        'name' => 'Open House',
        'place' => 'Main St',
        'description' => 'Come by!',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'public',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ])->assertRedirect();

    Notification::assertSentTo($follower, FollowedUserPublishedEvent::class);
});

test('a follower with a tag preference is only notified when the event matches', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);
    $music = Tag::factory()->create(['name' => 'music']);
    $sports = Tag::factory()->create(['name' => 'sports']);
    ContactTagPreference::factory()->create(['user_id' => $follower->id, 'contact_user_id' => $owner->id, 'tag_id' => $music->id]);

    $this->actingAs($owner)->post('/events', [
        'name' => 'Match Day',
        'place' => 'Stadium',
        'description' => 'Go team!',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'public',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'tag_ids' => [$sports->id],
    ])->assertRedirect();

    Notification::assertNothingSentTo($follower);
});

test('a private event does not notify followers', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);

    $this->actingAs($owner)->post('/events', [
        'name' => 'Quiet Dinner',
        'place' => 'Home',
        'description' => 'Just us.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('a user can view and mark their notifications as read', function () {
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);
    $event = Event::factory()->public()->create(['user_id' => $owner->id]);
    $follower->notify(new FollowedUserPublishedEvent($event));

    $notificationId = $follower->notifications()->first()->id;

    $this->actingAs($follower)->get('/notifications')
        ->assertInertia(fn ($page) => $page->component('notifications/Index')->has('notifications', 1));

    $this->actingAs($follower)->post("/notifications/{$notificationId}/read")->assertRedirect();

    expect($follower->notifications()->first()->read_at)->not->toBeNull();
});
