<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('a user can update their profile information and privacy setting', function () {
    $user = User::factory()->create(['allow_public_follow' => true]);

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => 'New Name',
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '0',
    ]);

    $response->assertRedirect();
    expect($user->fresh()->name)->toBe('New Name');
    expect($user->fresh()->allow_public_follow)->toBeFalse();
});

test('a user cannot take an existing username', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => 'taken',
        'email' => $user->email,
        'allow_public_follow' => '1',
    ]);

    $response->assertSessionHasErrorsIn('updateProfileInformation', ['username']);
});

test('a user can upload an avatar and set a bio', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '1',
        'bio' => 'Hello, I like board games.',
        'avatar' => UploadedFile::fake()->create('avatar.jpg', 50, 'image/jpeg'),
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->bio)->toBe('Hello, I like board games.');
    expect($user->avatar_path)->not->toBeNull();
    expect($user->avatar_url)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('uploading a new avatar replaces and deletes the old one', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'fake-content');

    $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '1',
        'avatar' => UploadedFile::fake()->create('new.jpg', 50, 'image/jpeg'),
    ])->assertRedirect();

    $user->refresh();
    expect($user->avatar_path)->not->toBe('avatars/old.jpg');
    Storage::disk('public')->assertMissing('avatars/old.jpg');
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('a user can remove their avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'fake-content');

    $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '1',
        'remove_avatar' => '1',
    ])->assertRedirect();

    $user->refresh();
    expect($user->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('avatars/old.jpg');
});

test('a user can update their password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect();
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
