<?php

use App\Models\User;

test('a user can register with a name, username, email and password', function () {
    $response = $this->post('/register', [
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/events');

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    expect($user->username)->toBe('ada');
    expect($user->allow_public_follow)->toBeTrue();
});

test('registration requires a unique username', function () {
    User::factory()->create(['username' => 'ada']);

    $response = $this->post('/register', [
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'email' => 'ada2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertInvalid(['username']);
    $this->assertGuest();
});
