<?php

use App\Enums\ThemeMode;
use App\Enums\ThemeStyle;
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
        'show_friends_list' => '1',
        'appear_in_friends_lists' => '1',
        'searchable' => '1',
        'push_notifications_enabled' => '1',
        'notify_event_invites' => '1',
        'notify_messages' => '1',
        'notify_friend_requests' => '1',
        'notify_follow_requests' => '1',
        'theme_style' => 'classic',
        'theme_mode' => 'system',
    ]);

    $response->assertRedirect();
    expect($user->fresh()->name)->toBe('New Name');
    expect($user->fresh()->allow_public_follow)->toBeFalse();
});

test('a user can update their friends-list and search privacy settings', function () {
    $user = User::factory()->create([
        'show_friends_list' => true,
        'appear_in_friends_lists' => true,
        'searchable' => true,
    ]);

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '1',
        'show_friends_list' => '0',
        'appear_in_friends_lists' => '0',
        'searchable' => '0',
        'push_notifications_enabled' => '1',
        'notify_event_invites' => '1',
        'notify_messages' => '1',
        'notify_friend_requests' => '1',
        'notify_follow_requests' => '1',
        'theme_style' => 'classic',
        'theme_mode' => 'system',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->show_friends_list)->toBeFalse();
    expect($user->appear_in_friends_lists)->toBeFalse();
    expect($user->searchable)->toBeFalse();
});

test('a user can update their push notification preferences', function () {
    $user = User::factory()->create([
        'push_notifications_enabled' => true,
        'notify_event_invites' => true,
        'notify_messages' => true,
        'notify_friend_requests' => true,
        'notify_follow_requests' => true,
    ]);

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '1',
        'show_friends_list' => '1',
        'appear_in_friends_lists' => '1',
        'searchable' => '1',
        'push_notifications_enabled' => '0',
        'notify_event_invites' => '0',
        'notify_messages' => '0',
        'notify_friend_requests' => '0',
        'notify_follow_requests' => '0',
        'theme_style' => 'classic',
        'theme_mode' => 'system',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->push_notifications_enabled)->toBeFalse();
    expect($user->notify_event_invites)->toBeFalse();
    expect($user->notify_messages)->toBeFalse();
    expect($user->notify_friend_requests)->toBeFalse();
    expect($user->notify_follow_requests)->toBeFalse();
});

test('a user cannot take an existing username', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => 'taken',
        'email' => $user->email,
        'allow_public_follow' => '1',
        'show_friends_list' => '1',
        'appear_in_friends_lists' => '1',
        'searchable' => '1',
        'push_notifications_enabled' => '1',
        'notify_event_invites' => '1',
        'notify_messages' => '1',
        'notify_friend_requests' => '1',
        'notify_follow_requests' => '1',
        'theme_style' => 'classic',
        'theme_mode' => 'system',
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
        'show_friends_list' => '1',
        'appear_in_friends_lists' => '1',
        'searchable' => '1',
        'push_notifications_enabled' => '1',
        'notify_event_invites' => '1',
        'notify_messages' => '1',
        'notify_friend_requests' => '1',
        'notify_follow_requests' => '1',
        'theme_style' => 'classic',
        'theme_mode' => 'system',
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

test('uploading a new avatar replaces and deletes the old one', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'fake-content');

    $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '1',
        'show_friends_list' => '1',
        'appear_in_friends_lists' => '1',
        'searchable' => '1',
        'push_notifications_enabled' => '1',
        'notify_event_invites' => '1',
        'notify_messages' => '1',
        'notify_friend_requests' => '1',
        'notify_follow_requests' => '1',
        'theme_style' => 'classic',
        'theme_mode' => 'system',
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
        'allow_public_follow' => '1',
        'show_friends_list' => '1',
        'appear_in_friends_lists' => '1',
        'searchable' => '1',
        'push_notifications_enabled' => '1',
        'notify_event_invites' => '1',
        'notify_messages' => '1',
        'notify_friend_requests' => '1',
        'notify_follow_requests' => '1',
        'theme_style' => 'classic',
        'theme_mode' => 'system',
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

test('a user can choose a visual style and light or dark mode, and the choice is shared with every page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '1',
        'show_friends_list' => '1',
        'appear_in_friends_lists' => '1',
        'searchable' => '1',
        'push_notifications_enabled' => '1',
        'notify_event_invites' => '1',
        'notify_messages' => '1',
        'notify_friend_requests' => '1',
        'notify_follow_requests' => '1',
        'theme_style' => 'soft',
        'theme_mode' => 'dark',
    ])->assertRedirect()->assertValid();

    expect($user->fresh()->theme_style)->toBe(ThemeStyle::Soft);
    expect($user->fresh()->theme_mode)->toBe(ThemeMode::Dark);

    $this->actingAs($user->fresh())->get('/events')
        ->assertInertia(fn ($page) => $page
            ->where('theme.style', 'soft')
            ->where('theme.mode', 'dark'))
        ->assertSee('data-style="soft"', false)
        ->assertSee('data-mode="dark"', false);
});

test('an unknown style or mode is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'allow_public_follow' => '1',
        'show_friends_list' => '1',
        'appear_in_friends_lists' => '1',
        'searchable' => '1',
        'push_notifications_enabled' => '1',
        'notify_event_invites' => '1',
        'notify_messages' => '1',
        'notify_friend_requests' => '1',
        'notify_follow_requests' => '1',
        'theme_style' => 'neon',
        'theme_mode' => 'dim',
    ])->assertInvalid(['theme_style', 'theme_mode'], 'updateProfileInformation');
});

test('guests get the classic style following the system', function () {
    $this->get('/login')
        ->assertSee('data-style="classic"', false)
        ->assertSee('data-mode="system"', false);
});
