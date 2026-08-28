<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('a user can log in with correct credentials', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect('/events');
});

test('logging in with remember me issues a long-lived remember cookie', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();
    $response->assertCookie(Auth::guard('web')->getRecallerName());
});

test('a user cannot log in with an incorrect password', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertInvalid(['username']);
});

test('two accounts sharing an email can each log in with their own username', function () {
    $first = User::factory()->create(['email' => 'shared@example.com', 'username' => 'first_account']);
    $second = User::factory()->create(['email' => 'shared@example.com', 'username' => 'second_account']);

    $this->post('/login', ['username' => 'first_account', 'password' => 'password']);
    $this->assertAuthenticatedAs($first);

    $this->post('/logout');

    $this->post('/login', ['username' => 'second_account', 'password' => 'password']);
    $this->assertAuthenticatedAs($second);
});

test('a user can log out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
