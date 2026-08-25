<?php

use App\Enums\AttendeeSource;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\InvitedToEvent;
use Illuminate\Support\Facades\Notification;

test('the owner can invite a friend, who is notified', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $owner->id, 'addressee_id' => $friend->id]);
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post("/events/{$event->id}/invitations", ['user_ids' => [$friend->id]])->assertRedirect();

    $this->assertDatabaseHas('event_attendees', [
        'event_id' => $event->id,
        'user_id' => $friend->id,
        'invited_by_user_id' => $owner->id,
        'source' => AttendeeSource::Invited->value,
    ]);
    Notification::assertSentTo($friend, InvitedToEvent::class);
});

test('the owner cannot invite someone who is not their friend', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post("/events/{$event->id}/invitations", ['user_ids' => [$stranger->id]])
        ->assertInvalid(['user_ids']);
});

test('an invited attendee can invite their own friends only when the owner allows it', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $inviteesFriend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $invitee->id, 'addressee_id' => $inviteesFriend->id]);

    $event = Event::factory()->create(['user_id' => $owner->id, 'allow_invitee_invites' => false]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $invitee->id, 'source' => AttendeeSource::Invited]);

    $this->actingAs($invitee)
        ->post("/events/{$event->id}/invitations", ['user_ids' => [$inviteesFriend->id]])
        ->assertForbidden();

    $event->update(['allow_invitee_invites' => true]);

    $this->actingAs($invitee)
        ->post("/events/{$event->id}/invitations", ['user_ids' => [$inviteesFriend->id]])
        ->assertRedirect();

    $this->assertDatabaseHas('event_attendees', [
        'event_id' => $event->id,
        'user_id' => $inviteesFriend->id,
        'invited_by_user_id' => $invitee->id,
    ]);
});

test('inviting an already-attending user does not duplicate the attendee row or re-notify', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $owner->id, 'addressee_id' => $friend->id]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $friend->id]);

    $this->actingAs($owner)->post("/events/{$event->id}/invitations", ['user_ids' => [$friend->id]])->assertRedirect();

    expect(EventAttendee::query()->where('event_id', $event->id)->where('user_id', $friend->id)->count())->toBe(1);
    Notification::assertNothingSent();
});
