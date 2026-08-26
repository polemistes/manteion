<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendeeSource;
use App\Models\ContactTagPreference;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventImage;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Tag;
use App\Models\User;

test('the list includes events the viewer is invited to and public events from people they follow', function () {
    $viewer = User::factory()->create();
    $inviter = User::factory()->create();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();

    $invitedEvent = Event::factory()->create(['user_id' => $inviter->id, 'name' => 'Invited Event', 'starts_at' => now()->addDay()]);
    EventAttendee::factory()->create(['event_id' => $invitedEvent->id, 'user_id' => $viewer->id, 'source' => AttendeeSource::Invited]);

    Follow::factory()->create(['follower_id' => $viewer->id, 'followed_id' => $followed->id]);
    $publicEvent = Event::factory()->public()->create(['user_id' => $followed->id, 'name' => 'Followed Public Event', 'starts_at' => now()->addDays(2)]);

    Event::factory()->create(['user_id' => $stranger->id, 'name' => 'Unrelated Private Event']);
    Event::factory()->create(['user_id' => $followed->id, 'name' => 'Followed Private Event']);

    $response = $this->actingAs($viewer)->get('/events');

    $response->assertInertia(fn ($page) => $page
        ->component('events/Index')
        ->has('events', 2)
        ->where('events.0.name', 'Invited Event')
        ->where('events.1.name', 'Followed Public Event'));
});

test('the list includes events the viewer owns even without an attendee record', function () {
    $owner = User::factory()->create();

    $event = Event::factory()->create(['user_id' => $owner->id, 'name' => 'My Own Event']);

    $response = $this->actingAs($owner)->get('/events');

    $response->assertInertia(fn ($page) => $page
        ->has('events', 1)
        ->where('events.0.name', 'My Own Event')
        ->where('events.0.my_status', null));
});

test('an explicit invitation always shows regardless of the contact tag preference', function () {
    $viewer = User::factory()->create();
    $inviter = User::factory()->create();
    $musicTag = Tag::factory()->create(['name' => 'music']);
    $sportsTag = Tag::factory()->create(['name' => 'sports']);

    ContactTagPreference::factory()->create([
        'user_id' => $viewer->id,
        'contact_user_id' => $inviter->id,
        'tag_id' => $musicTag->id,
    ]);

    $event = Event::factory()->create(['user_id' => $inviter->id]);
    $event->tags()->attach($sportsTag);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $viewer->id]);

    $response = $this->actingAs($viewer)->get('/events');

    $response->assertInertia(fn ($page) => $page->has('events', 1));
});

test('a followed users public events are filtered by the viewers tag preference for them', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    Follow::factory()->create(['follower_id' => $viewer->id, 'followed_id' => $followed->id]);

    $musicTag = Tag::factory()->create(['name' => 'music']);
    $sportsTag = Tag::factory()->create(['name' => 'sports']);
    ContactTagPreference::factory()->create([
        'user_id' => $viewer->id,
        'contact_user_id' => $followed->id,
        'tag_id' => $musicTag->id,
    ]);

    $musicEvent = Event::factory()->public()->create(['user_id' => $followed->id, 'name' => 'Music Night']);
    $musicEvent->tags()->attach($musicTag);

    $sportsEvent = Event::factory()->public()->create(['user_id' => $followed->id, 'name' => 'Sports Night']);
    $sportsEvent->tags()->attach($sportsTag);

    $response = $this->actingAs($viewer)->get('/events');

    $response->assertInertia(fn ($page) => $page
        ->has('events', 1)
        ->where('events.0.name', 'Music Night'));
});

test('the relation filter narrows the list to friends, following, or both', function () {
    $viewer = User::factory()->create();
    $friend = User::factory()->create();
    $followed = User::factory()->create();

    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => $friend->id]);
    Follow::factory()->create(['follower_id' => $viewer->id, 'followed_id' => $followed->id]);

    $friendEvent = Event::factory()->create(['user_id' => $friend->id]);
    EventAttendee::factory()->create(['event_id' => $friendEvent->id, 'user_id' => $viewer->id]);

    $followedEvent = Event::factory()->public()->create(['user_id' => $followed->id]);

    $this->actingAs($viewer)->get('/events?relation=friends')
        ->assertInertia(fn ($page) => $page->has('events', 1)->where('events.0.id', $friendEvent->id));

    $this->actingAs($viewer)->get('/events?relation=following')
        ->assertInertia(fn ($page) => $page->has('events', 1)->where('events.0.id', $followedEvent->id));

    $this->actingAs($viewer)->get('/events?relation=both')
        ->assertInertia(fn ($page) => $page->has('events', 2));
});

test('the list reports the cover image and attendance counts for each event', function () {
    $owner = User::factory()->create();
    $attending = User::factory()->create();
    $interested = User::factory()->create();

    $eventWithImage = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Has A Photo', 'starts_at' => now()->addDay()]);
    EventImage::factory()->create(['event_id' => $eventWithImage->id, 'path' => 'events/cover.jpg', 'position' => 0]);
    EventAttendee::factory()->attending()->create(['event_id' => $eventWithImage->id, 'user_id' => $attending->id]);
    EventAttendee::factory()->create(['event_id' => $eventWithImage->id, 'user_id' => $interested->id, 'status' => AttendanceStatus::Interested]);

    $eventWithoutImage = Event::factory()->create(['user_id' => $owner->id, 'name' => 'No Photo Yet', 'starts_at' => now()->addDays(2)]);

    $response = $this->actingAs($owner)->get('/events');

    $response->assertInertia(fn ($page) => $page
        ->has('events', 2)
        ->where('events.0.cover_image_url', fn ($url) => str_contains($url, 'events/cover.jpg'))
        ->where('events.0.attending_count', 1)
        ->where('events.0.interested_count', 1)
        ->where('events.1.cover_image_url', null)
        ->where('events.1.attending_count', 0)
        ->where('events.1.interested_count', 0));
});

test('near_lat and near_lng sort events nearest-first and push events without coordinates to the end', function () {
    $owner = User::factory()->create();

    // Oslo, roughly 470km from the viewer's point below.
    $near = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Near Event', 'latitude' => 59.9139, 'longitude' => 10.7522, 'starts_at' => now()->addDay()]);
    // Bergen, further away.
    $far = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Far Event', 'latitude' => 60.3913, 'longitude' => 5.3221, 'starts_at' => now()->addDays(2)]);
    $noLocation = Event::factory()->create(['user_id' => $owner->id, 'name' => 'No Location Event', 'latitude' => null, 'longitude' => null, 'starts_at' => now()->addDays(3)]);

    // Trondheim as the viewer's reference point — closer to Oslo than Bergen.
    $response = $this->actingAs($owner)->get('/events?near_lat=63.4305&near_lng=10.3951');

    $response->assertInertia(fn ($page) => $page
        ->has('events', 3)
        ->where('events.0.id', $near->id)
        ->where('events.1.id', $far->id)
        ->where('events.2.id', $noLocation->id)
        ->where('events.2.distance_km', null)
        ->where('events.0.distance_km', fn ($km) => $km > 0));
});

test('an out-of-range near_lat or near_lng is ignored rather than erroring', function () {
    $owner = User::factory()->create();
    Event::factory()->create(['user_id' => $owner->id, 'name' => 'Some Event']);

    $response = $this->actingAs($owner)->get('/events?near_lat=999&near_lng=10');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('events', 1)
        ->where('events.0.distance_km', null));
});
