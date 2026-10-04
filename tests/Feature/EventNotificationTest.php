<?php

use App\Jobs\NotifyFollowersOfEvent;
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

test('a follower who muted a tag is not notified when the event matches it', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);
    $music = Tag::factory()->create(['name' => 'music']);
    $sports = Tag::factory()->create(['name' => 'sports']);
    ContactTagPreference::factory()->create(['user_id' => $follower->id, 'contact_user_id' => $owner->id, 'tag_id' => $music->id]);

    $this->actingAs($owner)->post('/events', [
        'name' => 'Concert Night',
        'place' => 'Arena',
        'description' => 'Live music!',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'public',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'tag_ids' => [$music->id],
    ])->assertRedirect();

    Notification::assertNothingSentTo($follower);
});

test('a follower who muted a tag is still notified about events that do not match it', function () {
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

    Notification::assertSentTo($follower, FollowedUserPublishedEvent::class);
});

test('a follower who fully muted an owner is never notified', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);
    ContactTagPreference::factory()->create(['user_id' => $follower->id, 'contact_user_id' => $owner->id, 'tag_id' => null]);

    $this->actingAs($owner)->post('/events', [
        'name' => 'Quiet Gathering',
        'place' => 'Park',
        'description' => 'No particular topic.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'public',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
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

test('followers are notified once an event created private is later made public, and only once', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $payload = [
        'name' => $event->name,
        'description' => $event->description,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ];

    $this->actingAs($owner)->put("/events/{$event->id}", [...$payload, 'visibility' => 'public'])->assertRedirect();
    Notification::assertSentToTimes($follower, FollowedUserPublishedEvent::class, 1);

    $this->actingAs($owner)->put("/events/{$event->id}", [...$payload, 'visibility' => 'private'])->assertRedirect();
    $this->actingAs($owner)->put("/events/{$event->id}", [...$payload, 'visibility' => 'public'])->assertRedirect();
    Notification::assertSentToTimes($follower, FollowedUserPublishedEvent::class, 1);
});

test('each follower hears about a new event in their own language', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $english = User::factory()->create(['locale' => 'en']);
    $norwegian = User::factory()->create(['locale' => 'nb']);
    $pendingFollower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $english->id, 'followed_id' => $owner->id]);
    Follow::factory()->create(['follower_id' => $norwegian->id, 'followed_id' => $owner->id]);
    Follow::factory()->pending()->create(['follower_id' => $pendingFollower->id, 'followed_id' => $owner->id]);
    $event = Event::factory()->public()->create(['user_id' => $owner->id]);

    (new NotifyFollowersOfEvent($event))->handle();

    Notification::assertSentTo($english, FollowedUserPublishedEvent::class, fn (FollowedUserPublishedEvent $notification) => $notification->event->is($event)
        && $notification->locale === 'en');
    Notification::assertSentTo($norwegian, FollowedUserPublishedEvent::class, fn (FollowedUserPublishedEvent $notification) => $notification->locale === 'nb');
    Notification::assertNotSentTo($pendingFollower, FollowedUserPublishedEvent::class);
    Notification::assertNotSentTo($owner, FollowedUserPublishedEvent::class);
    expect($event->fresh()->followers_notified_at)->not->toBeNull();
});
