<?php

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\User;

test('the owner can make an event searchable', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->public()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post("/events/{$event->id}/searchable")->assertRedirect();

    $event->refresh();
    expect($event->visibility)->toBe(EventVisibility::Searchable);
    expect($event->searchable_paid_at)->not->toBeNull();
});

test('only the owner can make an event searchable', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->public()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)->post("/events/{$event->id}/searchable")->assertForbidden();
});

test('searchable events are findable via the search endpoint', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $event = Event::factory()->searchable()->create(['user_id' => $owner->id, 'name' => 'Citywide Picnic']);
    Event::factory()->create(['user_id' => $owner->id, 'name' => 'Private Dinner']);

    $response = $this->actingAs($viewer)->get('/events/search?q=Picnic');

    $response->assertInertia(fn ($page) => $page
        ->component('events/Search')
        ->has('events', 1)
        ->where('events.0.name', 'Citywide Picnic'));
});

test('the searchable tier survives making the event private and public again', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->searchable()->create(['user_id' => $owner->id]);

    $payload = [
        'name' => $event->name,
        'description' => $event->description,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ];

    $this->actingAs($owner)->put("/events/{$event->id}", [...$payload, 'visibility' => 'private'])->assertRedirect();
    expect($event->fresh()->visibility)->toBe(EventVisibility::Private);

    $this->actingAs($owner)->put("/events/{$event->id}", [...$payload, 'visibility' => 'public'])->assertRedirect();
    expect($event->fresh()->visibility)->toBe(EventVisibility::Searchable);
});
