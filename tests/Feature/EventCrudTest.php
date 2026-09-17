<?php

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a user can create a private event with categories and images', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $games = Tag::query()->where('slug', 'games')->firstOrFail();

    $response = $this->actingAs($user)->post('/events', [
        'name' => 'Board Game Night',
        'place' => 'My apartment',
        'description' => 'Bring snacks.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'ends_at' => null,
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'tag_ids' => [$games->id],
        'image' => UploadedFile::fake()->image('photo.jpg'),
    ]);

    $event = Event::query()->where('name', 'Board Game Night')->firstOrFail();
    $response->assertRedirect("/events/{$event->id}");

    expect($event->user_id)->toBe($user->id);
    expect($event->visibility)->toBe(EventVisibility::Private);
    // Selecting the "Games" subcategory should also attach its "Social" parent category.
    expect($event->tags()->pluck('slug')->sort()->values()->all())->toBe(['games', 'social']);
    expect($event->images)->toHaveCount(1);
    Storage::disk('public')->assertExists($event->images->first()->path);
});

test('uploading a new event image replaces and deletes the old one', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $event->images()->create(['path' => 'events/1/old.jpg', 'position' => 0]);
    Storage::disk('public')->put('events/1/old.jpg', 'fake-content');

    $this->actingAs($user)->put("/events/{$event->id}", [
        'name' => $event->name,
        'place' => $event->place,
        'description' => $event->description,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'image' => UploadedFile::fake()->image('new.jpg'),
    ])->assertRedirect();

    $event->refresh();
    expect($event->images)->toHaveCount(1);
    expect($event->images->first()->path)->not->toBe('events/1/old.jpg');
    Storage::disk('public')->assertMissing('events/1/old.jpg');
    Storage::disk('public')->assertExists($event->images->first()->path);
});

test('a user can create an event with a map location', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/events', [
        'name' => 'Beach Cleanup',
        'place' => 'Bygdøy beach',
        'latitude' => 59.9022,
        'longitude' => 10.6802,
        'city' => 'Oslo',
        'country' => 'Norway',
        'description' => 'Bring gloves.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'ends_at' => null,
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ]);

    $event = Event::query()->where('name', 'Beach Cleanup')->firstOrFail();
    $response->assertRedirect("/events/{$event->id}");

    expect($event->latitude)->toBe(59.9022);
    expect($event->longitude)->toBe(10.6802);
    expect($event->city)->toBe('Oslo');
    expect($event->country)->toBe('Norway');

    $this->actingAs($user)->get("/events/{$event->id}")
        ->assertInertia(fn ($page) => $page
            ->where('event.latitude', 59.9022)
            ->where('event.longitude', 10.6802)
            ->where('event.city', 'Oslo')
            ->where('event.country', 'Norway'));
});

test('a user can create an event without a place or map location', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/events', [
        'name' => 'Online Meetup',
        'description' => 'Link shared beforehand.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'ends_at' => null,
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ]);

    $event = Event::query()->where('name', 'Online Meetup')->firstOrFail();
    $response->assertRedirect("/events/{$event->id}");

    expect($event->place)->toBeNull();
    expect($event->latitude)->toBeNull();
});

test('a private event is only visible to its owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get("/events/{$event->id}")->assertOk();
    $this->actingAs($stranger)->get("/events/{$event->id}")->assertForbidden();
});

test('a public event is visible to a follower but not to a stranger', function () {
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    $stranger = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);
    $event = Event::factory()->public()->create(['user_id' => $owner->id]);

    $this->actingAs($follower)->get("/events/{$event->id}")->assertOk();
    $this->actingAs($stranger)->get("/events/{$event->id}")->assertForbidden();
});

test('a searchable event is visible to anyone', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->searchable()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)->get("/events/{$event->id}")->assertOk();
});

test('only the owner can update or delete an event', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)->put("/events/{$event->id}", [
        'name' => 'Hijacked',
        'place' => $event->place,
        'description' => $event->description,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ])->assertForbidden();

    $this->actingAs($stranger)->delete("/events/{$event->id}")->assertForbidden();

    $this->actingAs($owner)->delete("/events/{$event->id}")->assertRedirect('/events');
    $this->assertModelMissing($event);
});

test('an oversized event image is scaled down to fit before it is stored', function () {
    Storage::fake('public');
    $owner = User::factory()->create();

    $this->actingAs($owner)->post('/events', [
        'name' => 'Big Picture',
        'description' => 'A wide banner.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'image' => UploadedFile::fake()->image('banner.png', 3200, 1600),
    ])->assertRedirect();

    $path = Event::query()->where('name', 'Big Picture')->firstOrFail()->images->first()->path;
    [$width, $height] = getimagesize(Storage::disk('public')->path($path));

    expect($width)->toBe(1600)->and($height)->toBe(800);
});

test('an image beyond the decoding cap is rejected by validation', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post('/events', [
        'name' => 'Huge Picture',
        'description' => 'Far too large.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'image' => UploadedFile::fake()->image('huge.jpg', 5001, 100),
    ])->assertInvalid(['image']);
});
