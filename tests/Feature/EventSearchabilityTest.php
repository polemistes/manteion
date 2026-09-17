<?php

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Tag;
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

test('a tag filter finds matching events however many untagged events precede them', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $music = Tag::factory()->create(['name' => 'music']);

    foreach (range(1, 60) as $day) {
        Event::factory()->searchable()->create(['user_id' => $owner->id, 'name' => 'Plain', 'starts_at' => now()->addDays($day)]);
    }

    $concert = Event::factory()->searchable()->create(['user_id' => $owner->id, 'name' => 'Concert', 'starts_at' => now()->addDays(90)]);
    $concert->tags()->attach($music);

    $this->actingAs($viewer)->get('/events/search?tags[]=music')
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Concert'));
});

test('search results are paginated', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    foreach (range(1, 31) as $day) {
        Event::factory()->searchable()->create(['user_id' => $owner->id, 'name' => 'Picnic', 'starts_at' => now()->addDays($day)]);
    }

    $this->actingAs($viewer)->get('/events/search?q=Picnic')
        ->assertInertia(fn ($page) => $page
            ->has('events', 30)
            ->where('pagination.last_page', 2)
            ->where('pagination.next_url', fn ($url) => str_contains($url, 'q=Picnic') && str_contains($url, 'page=2')));
});
