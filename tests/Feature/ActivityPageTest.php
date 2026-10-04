<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVisibility;
use App\Enums\FriendshipStatus;
use App\Enums\UserRole;
use App\Events\BadgesUpdated;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\EventImage;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\User;
use App\Notifications\FollowedUserPublishedEvent;
use App\Notifications\FriendRequestReceived;
use App\Notifications\InvitedToEvent;
use App\Notifications\NewEventComment;
use App\Notifications\NewFollower;
use App\Notifications\NewLeafletResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('the activity page lists each notification as an activity item, newest first', function () {
    $user = User::factory()->create();
    $inviter = User::factory()->create(['name' => 'Ada']);
    $follower = User::factory()->create(['name' => 'Grace']);
    $event = Event::factory()->create(['name' => 'Board Game Night']);

    $user->notify(new InvitedToEvent($event, $inviter));
    $user->notifications()->update(['read_at' => now()]);
    $this->travel(5)->minutes();
    $user->notify(new NewFollower(Follow::factory()->pending()->create(['follower_id' => $follower->id, 'followed_id' => $user->id])));

    $invitation = $user->notifications()->where('type', InvitedToEvent::class)->sole();
    $followRequest = $user->notifications()->where('type', NewFollower::class)->sole();

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->has('notifications', 2)
            ->has('notifications.0', fn (Assert $item) => $item
                ->where('id', $followRequest->id)
                ->where('type', 'NewFollower')
                ->where('category', 'followers')
                ->where('actor', ['id' => $follower->id, 'name' => 'Grace', 'username' => $follower->username, 'avatar_url' => null])
                ->where('subject_type', 'user')
                ->where('subject_id', $follower->id)
                ->where('subject_title', 'Grace')
                ->where('excerpt', null)
                ->where('pending', true)
                ->where('url', "/users/{$follower->id}")
                ->where('unread', true)
                ->where('created_at', $followRequest->created_at->toIso8601String())
                ->where('context', ['event' => null, 'request' => ['id' => $followRequest->data['follow_id'], 'status' => 'pending'], 'following_actor' => null]))
            ->has('notifications.1', fn (Assert $item) => $item
                ->where('id', $invitation->id)
                ->where('type', 'InvitedToEvent')
                ->where('category', 'event_invites')
                ->where('actor', ['id' => $inviter->id, 'name' => 'Ada', 'username' => $inviter->username, 'avatar_url' => null])
                ->where('subject_type', 'event')
                ->where('subject_id', $event->id)
                ->where('subject_title', 'Board Game Night')
                ->where('excerpt', null)
                ->where('pending', null)
                ->where('url', "/events/{$event->id}")
                ->where('unread', false)
                ->where('created_at', $invitation->created_at->toIso8601String())
                // Someone else's private event, without an invitation row: out of sight.
                ->where('context', ['event' => null, 'request' => null, 'following_actor' => null])));
});

test('the activity page shows the newest hundred notifications', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();

    foreach (range(1, 101) as $number) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => InvitedToEvent::class,
            'data' => ['actor_id' => $actor->id, 'subject_type' => 'event', 'subject_id' => $number, 'subject_title' => "Event {$number}", 'url' => "/events/{$number}", 'excerpt' => null, 'pending' => null],
            'created_at' => now()->subMinutes(200 - $number),
        ]);
    }

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications', 100)
            ->where('notifications.0.subject_id', 101)
            ->where('notifications.99.subject_id', 2));
});

test('the activity page loads every actor in one query', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    foreach (range(1, 3) as $ignored) {
        $user->notify(new InvitedToEvent($event, User::factory()->create()));
    }

    DB::enableQueryLog();
    $this->actingAs($user)->get('/notifications')->assertOk();

    $actorQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query) => str_contains($query['query'], 'select "id", "name", "username", "avatar_path" from "users"'));
    expect($actorQueries)->toHaveCount(1);
});

test('an invitation brings the event, its cover and the answer so far, to answer in place', function () {
    $user = User::factory()->create();
    $host = User::factory()->create(['name' => 'Ada']);
    $event = Event::factory()->create(['user_id' => $host->id, 'starts_at' => now()->addWeek()->startOfMinute(), 'ends_at' => null]);
    $image = EventImage::factory()->create(['event_id' => $event->id]);
    EventAttendee::factory()->visibility(AttendanceVisibility::Friends)->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'status' => AttendanceStatus::Interested,
    ]);
    $user->notify(new InvitedToEvent($event, $host));

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.0.context.event', [
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => null,
                'cover' => $image->fresh()->presentation(),
                'host_name' => 'Ada',
                'is_host' => false,
                'is_over' => false,
                'my_status' => 'interested',
                'my_attendance_visibility' => 'friends',
            ]));
});

test('an event that was deleted leaves its notifications without event context', function () {
    $user = User::factory()->create();
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $user->id]);
    $user->notify(new InvitedToEvent($event, $host));
    $event->update(['is_deleted' => true]);

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->where('notifications.0.context.event', null));
});

test('a friend request says whether it is still open', function () {
    $user = User::factory()->create();
    $friendship = Friendship::factory()->create(['addressee_id' => $user->id]);
    $user->notify(new FriendRequestReceived($friendship));

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.0.context.request', ['id' => $friendship->id, 'status' => 'pending']));

    $friendship->update(['status' => FriendshipStatus::Accepted]);

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.0.context.request', ['id' => $friendship->id, 'status' => 'accepted']));
});

test('a new follower says whether the user follows them back', function () {
    $user = User::factory()->create();
    $follower = User::factory()->create();
    $user->notify(new NewFollower(Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $user->id])));

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->where('notifications.0.context.following_actor', null));

    Follow::factory()->pending()->create(['follower_id' => $user->id, 'followed_id' => $follower->id]);

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->where('notifications.0.context.following_actor', 'pending'));
});

test('the activity page only shows the users own notifications', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();
    $someoneElse->notify(new InvitedToEvent(Event::factory()->create(), User::factory()->create()));

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->has('notifications', 0));

    expect($someoneElse->unreadNotifications()->count())->toBe(1);
});

test('notifications of someone who has since left show without an actor', function () {
    $user = User::factory()->create();
    $inviter = User::factory()->create();
    $user->notify(new InvitedToEvent(Event::factory()->create(), $inviter));
    $inviter->delete();

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->where('notifications.0.actor', null));
});

test('opening the activity page marks everything read, while showing what was unread', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $user->notify(new InvitedToEvent($event, User::factory()->create()));
    $user->notify(new InvitedToEvent($event, User::factory()->create()));
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.0.unread', true)
            ->where('notifications.1.unread', true)
            ->where('badges.activity', 0));

    expect($user->unreadNotifications()->count())->toBe(0);
    Events::assertDispatchedTimes(BadgesUpdated::class, 1);
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $user->id);

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->where('notifications.0.unread', false));

    // Nothing was left to mark, so no device needed telling.
    Events::assertDispatchedTimes(BadgesUpdated::class, 1);
});

test('a live update of the open activity page leaves the new item unread', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $inviter = User::factory()->create();

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(function (Assert $page) use ($user, $event, $inviter) {
            $user->notify(new InvitedToEvent($event, $inviter));

            $page->reloadOnly('notifications', fn (Assert $reload) => $reload->where('notifications.0.unread', true));
        });

    expect($user->unreadNotifications()->count())->toBe(1);
});

test('a prefetch of the activity page marks nothing read', function () {
    $user = User::factory()->create();
    $user->notify(new InvitedToEvent(Event::factory()->create(), User::factory()->create()));

    $this->actingAs($user)->get('/notifications', ['Purpose' => 'prefetch'])->assertOk();

    expect($user->unreadNotifications()->count())->toBe(1);
});

test('one notification can be marked read on its own', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $user->notify(new InvitedToEvent($event, User::factory()->create()));
    $user->notify(new InvitedToEvent($event, User::factory()->create()));
    $notification = $user->notifications()->first();
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($user)->post("/notifications/{$notification->id}/read")->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
    expect($user->unreadNotifications()->count())->toBe(1);
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $user->id);

    $this->actingAs($user)->postJson("/notifications/{$notification->id}/read")->assertNoContent();
    Events::assertDispatchedTimes(BadgesUpdated::class, 1);
});

test('nobody can mark someone elses notification read', function () {
    $owner = User::factory()->create();
    $owner->notify(new InvitedToEvent(Event::factory()->create(), User::factory()->create()));
    $notification = $owner->notifications()->sole();

    $this->actingAs(User::factory()->create())->post("/notifications/{$notification->id}/read")->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('guests have no activity page', function () {
    $this->get('/notifications')->assertRedirect('/login');
    $this->post('/notifications/'.Str::uuid().'/read')->assertRedirect('/login');
});

test('opening an event marks the viewers notifications about it read', function () {
    $user = User::factory()->create();
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $otherEvent = Event::factory()->create(['user_id' => $host->id]);
    $alsoInvited = User::factory()->create();
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $user->id]);
    $user->notify(new InvitedToEvent($event, $host));
    $user->notify(new InvitedToEvent($otherEvent, $host));
    $alsoInvited->notify(new InvitedToEvent($event, $host));
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($user)->get("/events/{$event->id}")->assertOk();

    expect($user->unreadNotifications()->where('data->subject_id', $event->id)->count())->toBe(0);
    expect($user->unreadNotifications()->where('data->subject_id', $otherEvent->id)->count())->toBe(1);
    expect($alsoInvited->unreadNotifications()->count())->toBe(1);
    Events::assertDispatchedTimes(BadgesUpdated::class, 1);
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $user->id);

    $this->actingAs($user)->get("/events/{$event->id}")->assertOk();
    Events::assertDispatchedTimes(BadgesUpdated::class, 1);
});

test('opening the event they host marks the comments on it read', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $host->notify(new NewEventComment(EventComment::factory()->create(['event_id' => $event->id])));

    $this->actingAs($host)->get("/events/{$event->id}")->assertOk();

    expect($host->unreadNotifications()->count())->toBe(0);
});

test('a live reload of an open event page marks nothing read', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $comment = EventComment::factory()->create(['event_id' => $event->id]);

    $this->actingAs($host)->get("/events/{$event->id}")
        ->assertInertia(function (Assert $page) use ($host, $comment) {
            $host->notify(new NewEventComment($comment));

            $page->reloadOnly('comments');
        });

    expect($host->unreadNotifications()->count())->toBe(1);
});

test('opening a leaflet marks the replies to it read', function () {
    $author = User::factory()->create();
    $leaflet = Leaflet::factory()->create(['user_id' => $author->id]);
    $otherLeaflet = Leaflet::factory()->create(['user_id' => $author->id]);
    $author->notify(new NewLeafletResponse(LeafletResponse::factory()->create(['leaflet_id' => $leaflet->id])));
    $author->notify(new NewLeafletResponse(LeafletResponse::factory()->create(['leaflet_id' => $otherLeaflet->id])));
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($author)->get("/leaflets/{$leaflet->id}")->assertOk();

    expect($author->unreadNotifications()->sole()->data['subject_id'])->toBe($otherLeaflet->id);
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $author->id);
});

test('opening someones profile marks their friend and follow notifications read', function () {
    $user = User::factory()->create();
    $requester = User::factory()->create();
    $stranger = User::factory()->create();
    $user->notify(new FriendRequestReceived(Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $user->id])));
    $user->notify(new NewFollower(Follow::factory()->create(['follower_id' => $requester->id, 'followed_id' => $user->id])));
    $user->notify(new NewFollower(Follow::factory()->create(['follower_id' => $stranger->id, 'followed_id' => $user->id])));
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($user)->get("/users/{$requester->id}")->assertOk();

    expect($user->unreadNotifications()->sole()->data['subject_id'])->toBe($stranger->id);
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $user->id);
});

test('opening a page with nothing unread about it tells no device', function () {
    $user = User::factory()->create();
    $user->notify(new InvitedToEvent(Event::factory()->create(), User::factory()->create()));
    $leaflet = Leaflet::factory()->official()->create();
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($user)->get("/leaflets/{$leaflet->id}")->assertOk();
    $this->actingAs($user)->get("/users/{$leaflet->user_id}")->assertOk();

    Events::assertNotDispatched(BadgesUpdated::class);
    expect($user->unreadNotifications()->count())->toBe(1);
});

test('an event the viewer may no longer see marks nothing read', function () {
    $user = User::factory()->create();
    $host = User::factory()->create();
    $event = Event::factory()->public()->create(['user_id' => $host->id]);
    $user->notify(new FollowedUserPublishedEvent($event));

    // They unfollowed since, so the public event is out of their sight.
    $this->actingAs($user)->get("/events/{$event->id}")->assertForbidden();

    expect($user->unreadNotifications()->count())->toBe(1);
});

test('an administrator browsing as a member marks none of the members activity read', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $user = User::factory()->create();
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $user->id]);
    $user->notify(new InvitedToEvent($event, $host));
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($user)->withSession(['impersonator_id' => $admin->id])
        ->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->where('notifications.0.unread', true));
    $this->actingAs($user)->withSession(['impersonator_id' => $admin->id])
        ->get("/events/{$event->id}")
        ->assertOk();
    $this->actingAs($user)->withSession(['impersonator_id' => $admin->id])
        ->get("/users/{$host->id}")
        ->assertOk();

    expect($user->unreadNotifications()->count())->toBe(1);
    Events::assertNotDispatched(BadgesUpdated::class);
});

test('the activity page says which notifications reach the phone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->where('push.categories', ['direct_messages', 'group_messages'])
            ->where('push.has_device', false));

    $user->updatePushSubscription('https://push.example.test/abc', 'key', 'token');

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->where('push.has_device', true));
});

test('deleting an event takes everyone’s notifications about it along', function () {
    $host = User::factory()->create();
    $guest = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $other = Event::factory()->create(['user_id' => $host->id]);
    $guest->notify(new InvitedToEvent($event, $host));
    $guest->notify(new InvitedToEvent($other, $host));
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($host)->delete("/events/{$event->id}")->assertRedirect();

    expect($guest->notifications()->pluck('data')->pluck('subject_id')->all())->toBe([$other->id]);
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $guest->id);
});

test('deleting a leaflet takes the notifications about its responses along', function () {
    $author = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $friend->id, 'addressee_id' => $author->id]);
    $leaflet = Leaflet::factory()->friends()->create(['user_id' => $author->id]);
    $author->notify(new NewLeafletResponse(LeafletResponse::factory()->create(['leaflet_id' => $leaflet->id, 'user_id' => $friend->id])));
    $author->notifications()->update(['read_at' => now()]);

    $this->actingAs($author)->delete("/leaflets/{$leaflet->id}")->assertRedirect();

    expect($author->notifications()->count())->toBe(0);
});
