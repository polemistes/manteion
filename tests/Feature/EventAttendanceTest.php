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
        ->assertInertia(fn ($page) => $page->has('attendees', 0));
});

test('an attendee with visibility set to none is listed only for the owner and themselves', function () {
    $owner = User::factory()->create();
    $attendee = User::factory()->create();
    $viewer = User::factory()->create();

    $event = Event::factory()->searchable()->create(['user_id' => $owner->id]);
    EventAttendee::factory()->attending()->visibility(AttendanceVisibility::None)->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
    ]);

    $this->actingAs($viewer)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page->has('attendees', 0));

    $this->actingAs($attendee)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page->where('attendees.0.status', AttendanceStatus::Attending->value));

    $this->actingAs($owner)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page->where('attendees.0.status', AttendanceStatus::Attending->value));
});

test('the host is not going until they say so, and then answers like anyone else', function () {
    $host = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $host->id]);
    $event = Event::factory()->public()->create(['user_id' => $host->id, 'starts_at' => now()->addWeek()]);

    // Nobody is listed, and the host has no answer of their own.
    $this->actingAs($host)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page->where('attendees', [])->where('myAttendance', null));
    $this->actingAs($host)->get('/events')
        ->assertInertia(fn ($page) => $page
            ->where('events.0.attending_count', 0)
            ->where('events.0.my_status', null)
            ->where('events.0.is_host', true));

    $this->actingAs($host)->put("/events/{$event->id}/attendance", [
        'status' => 'attending',
        'attendance_visibility' => 'everyone',
    ])->assertRedirect();

    // Now the host is one of those going, on the page and in the count.
    $this->actingAs($follower)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page
            ->where('attendees.0.user.id', $host->id)
            ->where('attendees.0.status', AttendanceStatus::Attending->value));
    $this->actingAs($host)->get('/events')
        ->assertInertia(fn ($page) => $page
            ->where('events.0.attending_count', 1)
            ->where('events.0.my_status', AttendanceStatus::Attending->value));

    // And can change their mind, or keep it to themself.
    $this->actingAs($host)->put("/events/{$event->id}/attendance", [
        'status' => 'interested',
        'attendance_visibility' => 'none',
    ])->assertRedirect();

    $this->actingAs($follower)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page->where('attendees', []));
    $this->actingAs($host)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page->where('myAttendance.status', AttendanceStatus::Interested->value));
});
