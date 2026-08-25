<?php

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a user can create a private event with tags and images', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/events', [
        'name' => 'Board Game Night',
        'place' => 'My apartment',
        'description' => 'Bring snacks.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'ends_at' => null,
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'tags' => ['games', 'social'],
        'images' => [UploadedFile::fake()->create('photo.jpg', 50, 'image/jpeg')],
    ]);

    $event = Event::query()->where('name', 'Board Game Night')->firstOrFail();
    $response->assertRedirect("/events/{$event->id}");

    expect($event->user_id)->toBe($user->id);
    expect($event->visibility)->toBe(EventVisibility::Private);
    expect($event->tags()->pluck('name')->sort()->values()->all())->toBe(['games', 'social']);
    expect($event->images)->toHaveCount(1);
    Storage::disk('public')->assertExists($event->images->first()->path);
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
