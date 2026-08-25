<?php

use App\Models\User;

test('a guest can switch locale and it persists in the session', function () {
    $this->put('/locale', ['locale' => 'nb'])->assertRedirect();

    $this->get('/')
        ->assertInertia(fn ($page) => $page->where('locale', 'nb'));
});

test('an authenticated user switching locale persists it to their account', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)->put('/locale', ['locale' => 'nb'])->assertRedirect();

    expect($user->fresh()->locale)->toBe('nb');

    $this->get('/events')
        ->assertInertia(fn ($page) => $page->where('locale', 'nb'));
});

test('switching to an unsupported locale is rejected', function () {
    $this->put('/locale', ['locale' => 'xx'])->assertInvalid(['locale']);
});

test('validation errors come back in norwegian when the locale is nb', function () {
    User::factory()->create(['username' => 'taken_username']);

    $this->put('/locale', ['locale' => 'nb'])->assertRedirect();

    $response = $this->post('/register', [
        'name' => 'New Person',
        'username' => 'taken_username',
        'email' => 'new-person@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertInvalid(['username']);
    $errors = session('errors')->getBag('default');
    expect($errors->first('username'))->toBe('Brukernavn er allerede i bruk.');
});
