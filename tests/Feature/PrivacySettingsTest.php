<?php

use App\Enums\UserRole;
use App\Models\User;

/** Every switch as a new account has it: open to others, except showing that you have read. */
const PRIVACY_DEFAULTS = [
    'allow_public_follow' => true,
    'show_friends_list' => true,
    'appear_in_friends_lists' => true,
    'searchable' => true,
    'share_typing' => true,
    'share_read_receipts' => false,
];

/**
 * @return array<string, bool>
 */
function privacyOf(User $user): array
{
    return $user->fresh()->only(array_keys(PRIVACY_DEFAULTS));
}

test('a new account has the expected privacy defaults', function () {
    expect(privacyOf(User::factory()->create()))->toBe(PRIVACY_DEFAULTS);
});

test('each privacy switch can be saved on its own, leaving the others alone', function (string $setting) {
    $user = User::factory()->create();
    $flipped = ! PRIVACY_DEFAULTS[$setting];

    $this->actingAs($user)
        ->from('/settings/privacy')
        ->put('/settings/privacy', [$setting => $flipped])
        ->assertRedirect('/settings/privacy')
        ->assertSessionHasNoErrors();

    expect(privacyOf($user))->toBe([...PRIVACY_DEFAULTS, $setting => $flipped]);
})->with(array_keys(PRIVACY_DEFAULTS));

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
        ...PRIVACY_DEFAULTS,
        'show_friends_list' => false,
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
