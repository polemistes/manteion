<?php

use App\Enums\NotificationCategory;
use App\Enums\ThemeMode;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('a user can update their name, username and email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'New Name',
            'username' => 'new_username',
            'email' => 'new@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->username)->toBe('new_username');
    expect($user->email)->toBe('new@example.com');
});

test('the profile endpoint leaves privacy and appearance to their own settings', function () {
    $user = User::factory()->create([
        'allow_public_follow' => true,
        'show_friends_list' => true,
        'appear_in_friends_lists' => true,
        'searchable' => true,
        'theme_mode' => ThemeMode::Light,
    ]);

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'New Name',
            'username' => $user->username,
            'email' => $user->email,
            'allow_public_follow' => '0',
            'show_friends_list' => '0',
            'appear_in_friends_lists' => '0',
            'searchable' => '0',
            'theme_mode' => 'dark',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->allow_public_follow)->toBeTrue();
    expect($user->show_friends_list)->toBeTrue();
    expect($user->appear_in_friends_lists)->toBeTrue();
    expect($user->searchable)->toBeTrue();
    expect($user->theme_mode)->toBe(ThemeMode::Light);
});

test('updating the profile leaves the notification settings untouched', function () {
    $user = User::factory()
        ->pushDisabledFor(NotificationCategory::DirectMessages)
        ->pushEnabledFor(NotificationCategory::EventInvites)
        ->create();

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => 'New Name',
        'username' => $user->username,
        'email' => $user->email,
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->wantsPush(NotificationCategory::DirectMessages))->toBeFalse();
    expect($user->wantsPush(NotificationCategory::EventInvites))->toBeTrue();
});

test('the name, username and email are required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', ['name' => '', 'username' => '', 'email' => ''])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['name', 'username', 'email']);
});

test('a user cannot take an existing username', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => 'taken',
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrorsIn('updateProfileInformation', ['username']);
});

test('a username may only hold letters, numbers, dashes and underscores', function (string $username) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => $user->name,
            'username' => $username,
            'email' => $user->email,
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['username']);

    expect($user->fresh()->username)->not->toBe($username);
})->with(['first.last', 'two words', 'semi;colon']);

test('a user can upload an avatar and set a bio', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'bio' => 'Hello, I like board games.',
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->bio)->toBe('Hello, I like board games.');
    expect($user->avatar_path)->not->toBeNull();
    expect($user->avatar_url)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('an avatar must be an image', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['avatar']);

    expect($user->fresh()->avatar_path)->toBeNull();
});

test('uploading a new avatar replaces and deletes the old one', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'fake-content');

    $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('new.jpg'),
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
