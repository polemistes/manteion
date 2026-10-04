<?php

use App\Enums\UserRole;
use App\Models\User;

/**
 * @return array<string, bool>
 */
function privacyOf(User $user): array
{
    return $user->fresh()->only(['allow_public_follow', 'show_friends_list', 'appear_in_friends_lists', 'searchable']);
}

test('each privacy switch can be saved on its own, leaving the others alone', function (string $setting) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/privacy')
        ->put('/settings/privacy', [$setting => false])
        ->assertRedirect('/settings/privacy')
        ->assertSessionHasNoErrors();

    expect(privacyOf($user))->toBe([
        'allow_public_follow' => true,
        'show_friends_list' => true,
        'appear_in_friends_lists' => true,
        'searchable' => true,
        $setting => false,
    ]);
})->with(['allow_public_follow', 'show_friends_list', 'appear_in_friends_lists', 'searchable']);

test('several privacy switches can be saved at once', function () {
    $user = User::factory()->privateFollow()->unsearchable()->create();

    $this->actingAs($user)
        ->put('/settings/privacy', [
            'allow_public_follow' => true,
            'show_friends_list' => false,
            'searchable' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(privacyOf($user))->toBe([
        'allow_public_follow' => true,
        'show_friends_list' => false,
        'appear_in_friends_lists' => true,
        'searchable' => true,
    ]);
});

test('a privacy switch only takes true or false', function (mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/privacy', ['searchable' => $value])
        ->assertInvalid(['searchable']);

    expect($user->fresh()->searchable)->toBeTrue();
})->with([
    'a word' => 'no',
    'a numeric string' => '0',
    'a number' => 0,
    'nothing' => [null],
    'a list' => [[false]],
]);

test('the privacy endpoint changes nothing but the privacy switches', function () {
    $user = User::factory()->create(['name' => 'Original Name']);

    $this->actingAs($user)
        ->put('/settings/privacy', [
            'searchable' => false,
            'name' => 'Someone Else',
            'role' => UserRole::Administrator->value,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->searchable)->toBeFalse();
    expect($user->name)->toBe('Original Name');
    expect($user->role)->toBe(UserRole::Member);
});

test('guests cannot change privacy settings', function () {
    $this->put('/settings/privacy', ['searchable' => false])->assertRedirect('/login');
});
