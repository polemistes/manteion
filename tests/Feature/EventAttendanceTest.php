<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVisibility;
use App\Enums\AttendeeSource;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;

test('a follower can RSVP to a public event, creating an attendee row lazily', function () {
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);
    $event = Event::factory()->public()->create(['user_id' => $owner->id]);

    $this->actingAs($follower)->put("/events/{$event->id}/attendance", [
        'status' => 'attending',
        'attendance_visibility' => 'everyone',
    ])->assertRedirect();

    $this->assertDatabaseHas('event_attendees', [
        'event_id' => $event->id,
        'user_id' => $follower->id,
        'status' => AttendanceStatus::Attending->value,
        'source' => AttendeeSource::Follower->value,
    ]);
});

test('a user who cannot see a private event cannot RSVP to it', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)->put("/events/{$event->id}/attendance", [
        'status' => 'attending',
        'attendance_visibility' => 'everyone',
    ])->assertForbidden();
});

test('the owner sees every attendees status regardless of their visibility choice', function () {
    $owner = User::factory()->create();
    $attendee = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);
    EventAttendee::factory()->attending()->visibility(AttendanceVisibility::None)->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
    ]);

    $response = $this->actingAs($owner)->get("/events/{$event->id}");

    $response->assertInertia(fn ($page) => $page
        ->where('attendees.0.status', AttendanceStatus::Attending->value));
});

test('an attendee with visibility set to friends is hidden from non-friends but shown to friends', function () {
    $owner = User::factory()->create();
    $attendee = User::factory()->create();
    $viewerFriend = User::factory()->create();
    $viewerStranger = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $attendee->id, 'addressee_id' => $viewerFriend->id]);

    $event = Event::factory()->public()->create(['user_id' => $owner->id]);
    Follow::factory()->create(['follower_id' => $viewerFriend->id, 'followed_id' => $owner->id]);
    Follow::factory()->create(['follower_id' => $viewerStranger->id, 'followed_id' => $owner->id]);
    EventAttendee::factory()->attending()->visibility(AttendanceVisibility::Friends)->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
    ]);

    $this->actingAs($viewerFriend)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page->where('attendees.0.status', AttendanceStatus::Attending->value));

    $this->actingAs($viewerStranger)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page->where('attendees.0.status', null));
});
