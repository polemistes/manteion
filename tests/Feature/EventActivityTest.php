<?php

use App\Enums\UserRole;
use App\Events\EventActivity;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\EventImage;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Exceptions;

/**
 * Asserts that exactly one EventActivity went out, for the given event and
 * kind.
 */
function assertEventActivity(Event $event, string $kind): void
{
    Events::assertDispatchedTimes(EventActivity::class, 1);
    Events::assertDispatched(EventActivity::class, fn (EventActivity $activity) => $activity->eventId === $event->id && $activity->kind === $kind);
}

test('it goes out right away on the events private channel as event.activity', function () {
    $activity = new EventActivity(42, 'comment');

    expect($activity)->toBeInstanceOf(ShouldBroadcastNow::class);
    expect(array_map('strval', $activity->broadcastOn()))->toBe(['private-events.42']);
    expect($activity->broadcastAs())->toBe('event.activity');
    expect($activity->broadcastWith())->toBe(['event_id' => 42, 'kind' => 'comment']);
});

test('posting a comment tells the open event pages, except the posters own', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    Events::fake([EventActivity::class]);

    $this->actingAs($host)
        ->post("/events/{$event->id}/comments", ['body' => 'Welcome!'], ['X-Socket-ID' => '1234.5678'])
        ->assertRedirect();

    assertEventActivity($event, 'comment');
    Events::assertDispatched(EventActivity::class, fn (EventActivity $activity) => $activity->socket === '1234.5678');
});

test('deleting a comment tells the open event pages', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $comment = EventComment::factory()->create(['event_id' => $event->id]);
    Events::fake([EventActivity::class]);

    $this->actingAs($host)->delete("/events/{$event->id}/comments/{$comment->id}")->assertRedirect();

    assertEventActivity($event, 'comment');
});

test('a refused comment tells no one', function () {
    $event = Event::factory()->create();
    Events::fake([EventActivity::class]);

    $this->actingAs(User::factory()->create())
        ->post("/events/{$event->id}/comments", ['body' => 'Hi'])
        ->assertForbidden();

    Events::assertNotDispatched(EventActivity::class);
});

test('answering an event tells the open event pages', function () {
    $host = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $host->id]);
    $event = Event::factory()->public()->create(['user_id' => $host->id]);
    Events::fake([EventActivity::class]);

    $this->actingAs($follower)->put("/events/{$event->id}/attendance", [
        'status' => 'attending',
        'attendance_visibility' => 'everyone',
    ])->assertRedirect();

    assertEventActivity($event, 'attendance');
});

test('inviting someone new tells the open event pages, re-inviting does not', function () {
    $host = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $host->id, 'addressee_id' => $friend->id]);
    $event = Event::factory()->create(['user_id' => $host->id]);
    Events::fake([EventActivity::class]);

    $this->actingAs($host)->post("/events/{$event->id}/invitations", ['user_ids' => [$friend->id]])->assertRedirect();

    assertEventActivity($event, 'invitation');

    $this->actingAs($host)->post("/events/{$event->id}/invitations", ['user_ids' => [$friend->id]])->assertRedirect();

    Events::assertDispatchedTimes(EventActivity::class, 1);
});

test('editing an event tells the open event pages', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    Events::fake([EventActivity::class]);

    $this->actingAs($host)->put("/events/{$event->id}", [
        'name' => 'Renamed',
        'description' => $event->description,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ])->assertRedirect();

    assertEventActivity($event, 'updated');
});

test('deleting an event tells the open event pages it is gone', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    EventAttendee::factory()->create(['event_id' => $event->id]);
    Events::fake([EventActivity::class]);

    $this->actingAs($host)->delete("/events/{$event->id}")->assertRedirect('/events');

    $this->assertModelMissing($event);
    assertEventActivity($event, 'deleted');
});

test('a moderator deleting an event behind a placeholder tells the open event pages too', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $event = Event::factory()->create();
    Events::fake([EventActivity::class]);

    $this->actingAs($moderator)->delete("/events/{$event->id}", ['leave_placeholder' => true])->assertRedirect();

    expect($event->fresh()->is_deleted)->toBeTrue();
    assertEventActivity($event, 'deleted');
});

test('an unreachable WebSocket server does not fail the change', function () {
    Exceptions::fake();
    Broadcast::extend('event-activity-test', fn () => new class implements Broadcaster
    {
        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = [])
        {
            throw new BroadcastException('Connection refused');
        }
    });
    config([
        'broadcasting.default' => 'event-activity-test',
        'broadcasting.connections.event-activity-test' => ['driver' => 'event-activity-test'],
    ]);
    Broadcast::forgetDrivers();
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);

    $this->actingAs($host)->post("/events/{$event->id}/comments", ['body' => 'Welcome!'])->assertRedirect();

    $this->assertDatabaseHas('event_comments', ['event_id' => $event->id, 'body' => 'Welcome!']);
    Exceptions::assertReported(BroadcastException::class);
});

test('removing the cover image tells the open event pages', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $image = EventImage::factory()->create(['event_id' => $event->id]);
    Events::fake([EventActivity::class]);

    $this->actingAs($host)->delete("/events/{$event->id}/images/{$image->id}")->assertRedirect();

    assertEventActivity($event, 'updated');
});

test('making an event searchable tells the open event pages', function () {
    $host = User::factory()->create();
    $event = Event::factory()->public()->create(['user_id' => $host->id]);
    Events::fake([EventActivity::class]);

    $this->actingAs($host)->post("/events/{$event->id}/searchable")->assertRedirect();

    assertEventActivity($event, 'updated');
});
