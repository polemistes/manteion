<?php

use App\Enums\NotificationCategory;
use App\Events\BadgesUpdated;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\User;
use App\Notifications\FollowedUserPublishedEvent;
use App\Notifications\FollowRequestApproved;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Notifications\InvitedToEvent;
use App\Notifications\NewEventComment;
use App\Notifications\NewFollower;
use App\Notifications\NewLeafletResponse;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Every kind of activity notification, with what it should store and push.
 * Each is built inside its test, so the factories can use the database.
 *
 * @return array<string, array{0: Closure(): array<string, mixed>}>
 */
function activityNotificationKinds(): array
{
    return [
        'an invitation' => [function () {
            $inviter = User::factory()->create(['name' => 'Ada']);
            $event = Event::factory()->create(['name' => 'Board Game Night']);

            return [
                'notification' => new InvitedToEvent($event, $inviter),
                'category' => NotificationCategory::EventInvites,
                'actor' => $inviter,
                'data' => ['actor_id' => $inviter->id, 'subject_type' => 'event', 'subject_id' => $event->id, 'subject_title' => 'Board Game Night', 'url' => "/events/{$event->id}", 'excerpt' => null, 'pending' => null],
                'push' => ['title' => 'Event invitation', 'body' => 'Ada invited you to “Board Game Night”'],
            ];
        }],
        'an event from someone followed' => [function () {
            $owner = User::factory()->create(['name' => 'Ada']);
            $event = Event::factory()->public()->create(['user_id' => $owner->id, 'name' => 'Open House']);

            return [
                'notification' => new FollowedUserPublishedEvent($event),
                'category' => NotificationCategory::FollowedEvents,
                'actor' => $owner,
                'data' => ['actor_id' => $owner->id, 'subject_type' => 'event', 'subject_id' => $event->id, 'subject_title' => 'Open House', 'url' => "/events/{$event->id}", 'excerpt' => null, 'pending' => null],
                'push' => ['title' => 'New event', 'body' => 'Ada published “Open House”'],
            ];
        }],
        'a comment on a hosted event' => [function () {
            $commenter = User::factory()->create(['name' => 'Ada']);
            $event = Event::factory()->create(['name' => 'Board Game Night']);
            $comment = EventComment::factory()->create(['event_id' => $event->id, 'user_id' => $commenter->id, 'body' => "I'll bring   snacks\nand drinks"]);

            return [
                'notification' => new NewEventComment($comment),
                'category' => NotificationCategory::EventComments,
                'actor' => $commenter,
                'data' => ['actor_id' => $commenter->id, 'subject_type' => 'event', 'subject_id' => $event->id, 'subject_title' => 'Board Game Night', 'url' => "/events/{$event->id}", 'excerpt' => "I'll bring snacks and drinks", 'pending' => null, 'comment_id' => $comment->id],
                'push' => ['title' => 'New comment on “Board Game Night”', 'body' => "Ada: I'll bring snacks and drinks"],
            ];
        }],
        'a friend request' => [function () {
            $requester = User::factory()->create(['name' => 'Ada']);
            $friendship = Friendship::factory()->create(['requester_id' => $requester->id]);

            return [
                'notification' => new FriendRequestReceived($friendship),
                'category' => NotificationCategory::FriendRequests,
                'actor' => $requester,
                'data' => ['actor_id' => $requester->id, 'subject_type' => 'user', 'subject_id' => $requester->id, 'subject_title' => 'Ada', 'url' => "/users/{$requester->id}", 'excerpt' => null, 'pending' => null, 'friendship_id' => $friendship->id],
                'push' => ['title' => 'Friend request', 'body' => 'Ada sent you a friend request'],
            ];
        }],
        'an accepted friend request' => [function () {
            $addressee = User::factory()->create(['name' => 'Ada']);
            $friendship = Friendship::factory()->accepted()->create(['addressee_id' => $addressee->id]);

            return [
                'notification' => new FriendRequestAccepted($friendship),
                'category' => NotificationCategory::FriendRequests,
                'actor' => $addressee,
                'data' => ['actor_id' => $addressee->id, 'subject_type' => 'user', 'subject_id' => $addressee->id, 'subject_title' => 'Ada', 'url' => "/users/{$addressee->id}", 'excerpt' => null, 'pending' => null],
                'push' => ['title' => 'Friend request accepted', 'body' => 'Ada accepted your friend request'],
            ];
        }],
        'a new follower' => [function () {
            $follower = User::factory()->create(['name' => 'Ada']);
            $follow = Follow::factory()->create(['follower_id' => $follower->id]);

            return [
                'notification' => new NewFollower($follow),
                'category' => NotificationCategory::Followers,
                'actor' => $follower,
                'data' => ['actor_id' => $follower->id, 'subject_type' => 'user', 'subject_id' => $follower->id, 'subject_title' => 'Ada', 'url' => "/users/{$follower->id}", 'excerpt' => null, 'pending' => false, 'follow_id' => $follow->id],
                'push' => ['title' => 'New follower', 'body' => 'Ada started following you'],
            ];
        }],
        'a follow request' => [function () {
            $follower = User::factory()->create(['name' => 'Ada']);
            $follow = Follow::factory()->pending()->create(['follower_id' => $follower->id]);

            return [
                'notification' => new NewFollower($follow),
                'category' => NotificationCategory::Followers,
                'actor' => $follower,
                'data' => ['actor_id' => $follower->id, 'subject_type' => 'user', 'subject_id' => $follower->id, 'subject_title' => 'Ada', 'url' => "/users/{$follower->id}", 'excerpt' => null, 'pending' => true, 'follow_id' => $follow->id],
                'push' => ['title' => 'Follow request', 'body' => 'Ada wants to follow you'],
            ];
        }],
        'an approved follow request' => [function () {
            $followed = User::factory()->create(['name' => 'Ada']);
            $follow = Follow::factory()->create(['followed_id' => $followed->id]);

            return [
                'notification' => new FollowRequestApproved($follow),
                'category' => NotificationCategory::Followers,
                'actor' => $followed,
                'data' => ['actor_id' => $followed->id, 'subject_type' => 'user', 'subject_id' => $followed->id, 'subject_title' => 'Ada', 'url' => "/users/{$followed->id}", 'excerpt' => null, 'pending' => null],
                'push' => ['title' => 'Follow request approved', 'body' => 'Ada approved your follow request'],
            ];
        }],
        'a reply to a leaflet' => [function () {
            $responder = User::factory()->create(['name' => 'Ada']);
            $leaflet = Leaflet::factory()->create(['body' => 'Anyone up for a trip to the coast on Saturday? We have room for three more in the car, and lunch is on us.']);
            $response = LeafletResponse::factory()->create(['leaflet_id' => $leaflet->id, 'user_id' => $responder->id, 'body' => 'Count me in!']);

            return [
                'notification' => new NewLeafletResponse($response),
                'category' => NotificationCategory::LeafletResponses,
                'actor' => $responder,
                'data' => ['actor_id' => $responder->id, 'subject_type' => 'leaflet', 'subject_id' => $leaflet->id, 'subject_title' => 'Anyone up for a trip to the coast on Saturday? We have room for three more in…', 'url' => "/leaflets/{$leaflet->id}", 'excerpt' => 'Count me in!', 'pending' => null, 'response_id' => $response->id],
                'push' => ['title' => 'New reply to your leaflet', 'body' => 'Ada: Count me in!'],
            ];
        }],
    ];
}

/**
 * Records every broadcast instead of sending it, in place of the test
 * environment's "null" driver.
 */
function recordActivityBroadcasts(): object
{
    $broadcaster = new class implements Broadcaster
    {
        /** @var list<array{channels: list<string>, event: string, payload: array<string, mixed>}> */
        public array $sent = [];

        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = [])
        {
            $this->sent[] = ['channels' => array_map('strval', $channels), 'event' => $event, 'payload' => $payload];
        }
    };

    Broadcast::extend('activity-test', fn () => $broadcaster);
    config([
        'broadcasting.default' => 'activity-test',
        'broadcasting.connections.activity-test' => ['driver' => 'activity-test'],
    ]);
    Broadcast::forgetDrivers();

    return $broadcaster;
}

/**
 * Does what the queue worker does with every job waiting in the database
 * queue, and says how many of them failed.
 */
function workActivityQueue(): int
{
    $failed = 0;

    while (($job = Queue::connection('database')->pop()) !== null) {
        $job->fire();

        if ($job->hasFailed()) {
            $failed++;
        }
    }

    return $failed;
}

test('each kind is queued, stored and broadcast, and pushed only once its own category is turned on', function (array $kind) {
    $notification = $kind['notification'];
    $otherCategories = array_values(array_filter(
        NotificationCategory::cases(),
        fn (NotificationCategory $category) => $category !== $kind['category'],
    ));

    expect($notification)->toBeInstanceOf(ShouldQueue::class);
    expect($notification::category())->toBe($kind['category']);

    expect($notification->via(User::factory()->create()))->toBe(['database', 'broadcast']);
    expect($notification->via(User::factory()->pushEnabledFor(...$otherCategories)->create()))->toBe(['database', 'broadcast']);
    expect($notification->via(User::factory()->pushEnabledFor($kind['category'])->create()))->toBe(['database', 'broadcast', WebPushChannel::class]);
})->with(activityNotificationKinds());

test('each kind stores the same data about its subject', function (array $kind) {
    $recipient = User::factory()->create();

    $recipient->notify($kind['notification']);

    $stored = $recipient->notifications()->sole();
    expect($stored->type)->toBe($kind['notification']::class);
    expect($stored->data)->toBe($kind['data']);
    expect($stored->read_at)->toBeNull();
})->with(activityNotificationKinds());

test('each kind broadcasts the item as the activity page lists it, right away', function (array $kind) {
    $this->freezeTime();
    $recipient = User::factory()->create();
    $notification = $kind['notification'];
    $notification->id = (string) Str::uuid();

    $message = $notification->toBroadcast($recipient);

    expect($message->connection)->toBe('sync');
    expect($notification->broadcastType())->toBe(class_basename($notification));
    expect($message->data)->toBe([
        'id' => $notification->id,
        'type' => class_basename($notification),
        'category' => $kind['category']->value,
        'actor' => [
            'id' => $kind['actor']->id,
            'name' => 'Ada',
            'username' => $kind['actor']->username,
            'avatar_url' => null,
        ],
        'subject_type' => $kind['data']['subject_type'],
        'subject_id' => $kind['data']['subject_id'],
        'subject_title' => $kind['data']['subject_title'],
        'excerpt' => $kind['data']['excerpt'],
        'pending' => $kind['data']['pending'],
        'url' => $kind['data']['url'],
        'unread' => true,
        'created_at' => now()->toIso8601String(),
    ]);
})->with(activityNotificationKinds());

test('each kind pushes a message about its subject, with the recipients badge count', function (array $kind) {
    $recipient = User::factory()->create();
    Friendship::factory()->create(['addressee_id' => $recipient->id]);
    $notification = $kind['notification'];
    $type = class_basename($notification);

    $message = $notification->toWebPush($recipient, $notification);

    expect($message->toArray())->toEqual([
        'title' => $kind['push']['title'],
        'body' => $kind['push']['body'],
        'icon' => '/icons/icon-192.png',
        'badge' => '/icons/badge-96.png',
        'tag' => "{$type}-{$kind['data']['subject_id']}",
        'renotify' => true,
        'lang' => 'en',
        'data' => [
            'url' => $kind['data']['url'],
            'type' => $type,
            'badge_count' => 1,
        ],
    ]);
    expect($message->getOptions())->toBe(['TTL' => 86400, 'urgency' => 'normal']);
})->with(activityNotificationKinds());

test('each kind pushes in Norwegian to someone who reads Norwegian', function (array $kind) {
    $recipient = User::factory()->create();
    $notification = $kind['notification'];

    app()->setLocale('nb');
    $message = $notification->toWebPush($recipient, $notification)->toArray();

    expect($message['lang'])->toBe('nb');
    expect($message['title'])->not->toBe($kind['push']['title'])->not->toStartWith('notifications.');
    expect($message['body'])->not->toStartWith('notifications.');
})->with(activityNotificationKinds());

test('the push wording is translated into Bokmål', function () {
    $inviter = User::factory()->create(['name' => 'Ada']);
    $notification = new InvitedToEvent(Event::factory()->create(['name' => 'Spillkveld']), $inviter);

    app()->setLocale('nb');
    $message = $notification->toWebPush(User::factory()->create(), $notification)->toArray();

    expect($message['title'])->toBe('Arrangementsinvitasjon');
    expect($message['body'])->toBe('Ada inviterte deg til «Spillkveld»');
});

test('every push text exists in both English and Norwegian', function () {
    $keys = fn (string $locale) => array_keys(Arr::dot(require lang_path("{$locale}/notifications.php")));

    expect($keys('nb'))->toBe($keys('en'));
});

test('the push shows the actors avatar when they have one', function () {
    $inviter = User::factory()->create(['avatar_path' => 'avatars/ada.jpg']);
    $notification = new InvitedToEvent(Event::factory()->create(), $inviter);

    $icon = $notification->toWebPush(User::factory()->create(), $notification)->toArray()['icon'];

    expect($icon)->toBe($inviter->avatar_url)->toStartWith('http');
});

test('a long comment is quoted shortened, on whole words', function () {
    $comment = EventComment::factory()->create(['body' => str_repeat('lorem ipsum ', 20)]);

    $excerpt = (new NewEventComment($comment))->excerpt();

    expect($excerpt)->toEndWith('…');
    $kept = Str::beforeLast($excerpt, '…');
    expect(mb_strlen($kept))->toBeLessThanOrEqual(140);
    expect(Str::squish($comment->body))->toStartWith("{$kept} ");
});

test('a quoted comment keeps text that merely looks like markup', function () {
    $comment = EventComment::factory()->create(['body' => 'I <3 board games. '.str_repeat('Really, ', 30)]);

    expect((new NewEventComment($comment))->excerpt())->toStartWith('I <3 board games.');
});

test('storing a notification updates the recipients badges', function () {
    $recipient = User::factory()->create();
    $notification = new InvitedToEvent(Event::factory()->create(), User::factory()->create());
    Events::fake([BadgesUpdated::class]);

    $recipient->notify($notification);

    Events::assertDispatchedTimes(BadgesUpdated::class, 1);
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $recipient->id);
});

test('only storing a notification updates the badges, not broadcasting or pushing it', function () {
    $recipient = User::factory()->create();
    $notification = new InvitedToEvent(Event::factory()->create(), User::factory()->create());
    Events::fake([BadgesUpdated::class]);

    event(new NotificationSent($recipient, $notification, 'broadcast'));
    event(new NotificationSent($recipient, $notification, WebPushChannel::class));
    event(new NotificationSent(new AnonymousNotifiable, $notification, 'database'));
    Events::assertNotDispatched(BadgesUpdated::class);

    event(new NotificationSent($recipient, $notification, 'database'));
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $recipient->id);
});

test('a queued notification is stored, broadcast and counted on the badge when the worker sends it', function () {
    $broadcaster = recordActivityBroadcasts();
    config(['queue.default' => 'database']);
    $recipient = User::factory()->create();
    $event = Event::factory()->create();

    $recipient->notify((new InvitedToEvent($event, User::factory()->create()))->locale('en'));

    expect($recipient->notifications()->count())->toBe(0);
    expect($broadcaster->sent)->toBe([]);

    expect(workActivityQueue())->toBe(0);

    $stored = $recipient->notifications()->sole();
    $sent = collect($broadcaster->sent);
    expect($sent->pluck('channels')->unique()->all())->toBe([["private-App.Models.User.{$recipient->id}"]]);

    $badges = $sent->firstWhere('event', 'badges.updated');
    expect($badges['payload'])->toMatchArray(['messages' => 0, 'activity' => 1, 'requests' => 0]);

    $item = $sent->firstWhere('event', BroadcastNotificationCreated::class);
    expect($item['payload'])->toMatchArray([
        'id' => $stored->id,
        'type' => 'InvitedToEvent',
        'category' => 'event_invites',
        'subject_type' => 'event',
        'subject_id' => $event->id,
        'unread' => true,
    ]);
});

test('a queued notification about something deleted in the meantime is dropped', function () {
    config(['queue.default' => 'database']);
    $host = User::factory()->create();
    $comment = EventComment::factory()->create(['event_id' => Event::factory()->create(['user_id' => $host->id])->id]);

    $host->notify(new NewEventComment($comment));
    $comment->delete();

    expect(workActivityQueue())->toBe(0);

    expect($host->notifications()->count())->toBe(0);
    expect(DB::table('jobs')->count())->toBe(0);
});

test('a queued notification carries its models without the relations the request had loaded', function () {
    $event = Event::factory()->create();
    EventAttendee::factory()->count(3)->create(['event_id' => $event->id]);
    $event->load('attendees');

    $queued = unserialize(serialize(new InvitedToEvent($event, User::factory()->create())));

    expect($queued->event->is($event))->toBeTrue();
    expect($queued->event->relationLoaded('attendees'))->toBeFalse();
});
