<?php

use App\Models\Event;
use App\Models\EventImage;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('a user can delete their account with their password and is logged out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete('/profile', ['password' => 'password']);

    $response->assertRedirect('/');
    $this->assertGuest();
    expect(User::find($user->id))->toBeNull();
});

test('deleting an account requires the correct password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete('/profile', ['password' => 'wrong-password']);

    $response->assertSessionHasErrors(['password']);
    $this->assertAuthenticated();
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting an account requires a password to be provided', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete('/profile');

    $response->assertSessionHasErrors(['password']);
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting an account removes the avatar and owned event images from storage, and cascades related data', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/mine.jpg']);
    Storage::disk('public')->put('avatars/mine.jpg', 'fake-content');

    $friend = User::factory()->create();
    $friendship = Friendship::factory()->accepted()->create([
        'requester_id' => $user->id,
        'addressee_id' => $friend->id,
    ]);

    $event = Event::factory()->create(['user_id' => $user->id]);
    $image = EventImage::factory()->for($event)->create(['path' => 'events/mine.jpg']);
    Storage::disk('public')->put('events/mine.jpg', 'fake-content');

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    Storage::disk('public')->assertMissing('avatars/mine.jpg');
    Storage::disk('public')->assertMissing('events/mine.jpg');
    expect(Friendship::find($friendship->id))->toBeNull();
    expect(Event::find($event->id))->toBeNull();
    expect(EventImage::find($image->id))->toBeNull();
});
