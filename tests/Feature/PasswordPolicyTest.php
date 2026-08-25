<?php

test('registration rejects a password shorter than 10 characters', function () {
    $response = $this->post('/register', [
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'email' => 'ada@example.com',
        'password' => 'short123',
        'password_confirmation' => 'short123',
    ]);

    $response->assertInvalid(['password']);
    $this->assertGuest();
});

test('registration accepts a long passphrase with no character-class requirements', function () {
    $response = $this->post('/register', [
        'name' => 'Ada Lovelace',
        'username' => 'ada',
        'email' => 'ada@example.com',
        'password' => 'all lowercase words here',
        'password_confirmation' => 'all lowercase words here',
    ]);

    $response->assertValid(['password']);
    $this->assertAuthenticated();
});
