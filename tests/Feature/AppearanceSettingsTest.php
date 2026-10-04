<?php

use App\Enums\ThemeMode;
use App\Models\User;

test('a user can choose light or dark mode, and the choice is shared with every page', function () {
    $user = User::factory()->create(['theme_mode' => ThemeMode::Light]);

    $this->actingAs($user)
        ->from('/settings/appearance')
        ->put('/settings/appearance', ['theme_mode' => 'dark'])
        ->assertRedirect('/settings/appearance')
        ->assertSessionHasNoErrors();

    expect($user->fresh()->theme_mode)->toBe(ThemeMode::Dark);

    $this->actingAs($user->fresh())->get('/events')
        ->assertInertia(fn ($page) => $page
            ->where('theme.mode', 'dark')
            ->missing('theme.style'))
        ->assertSee('data-mode="dark"', false)
        ->assertDontSee('data-style', false);
});

test('a user can go back to following the system', function () {
    $user = User::factory()->create(['theme_mode' => ThemeMode::Dark]);

    $this->actingAs($user)
        ->put('/settings/appearance', ['theme_mode' => 'system'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->theme_mode)->toBe(ThemeMode::System);
});

test('an unknown or missing mode is rejected', function (array $input) {
    $user = User::factory()->create(['theme_mode' => ThemeMode::Light]);

    $this->actingAs($user)
        ->put('/settings/appearance', $input)
        ->assertInvalid(['theme_mode']);

    expect($user->fresh()->theme_mode)->toBe(ThemeMode::Light);
})->with([
    'an unknown mode' => [['theme_mode' => 'dim']],
    'the removed style choice' => [['theme_mode' => 'soft']],
    'no mode' => [[]],
]);

test('guests get the mode following the system', function () {
    $this->get('/login')
        ->assertSee('data-mode="system"', false)
        ->assertDontSee('data-style', false);
});

test('guests cannot change the appearance', function () {
    $this->put('/settings/appearance', ['theme_mode' => 'dark'])->assertRedirect('/login');
});
