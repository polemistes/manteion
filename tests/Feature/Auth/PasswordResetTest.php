<?php

use App\Mail\PasswordResetRequested;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('requesting a reset link for an email with one match sends one email with one link', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'single@example.com']);

    $this->post('/forgot-password', ['email' => 'single@example.com'])->assertSessionHasNoErrors();

    expect(PasswordResetRequest::query()->where('user_id', $user->id)->count())->toBe(1);
    Mail::assertSent(PasswordResetRequested::class, fn (PasswordResetRequested $mail) => $mail->hasTo('single@example.com')
        && count($mail->links) === 1
        && $mail->links[0]['user']->is($user));
});

test('requesting a reset link for an email with several matches sends one email listing every account', function () {
    Mail::fake();
    $first = User::factory()->create(['email' => 'shared@example.com', 'username' => 'account_one']);
    $second = User::factory()->create(['email' => 'shared@example.com', 'username' => 'account_two']);

    $this->post('/forgot-password', ['email' => 'shared@example.com'])->assertSessionHasNoErrors();

    expect(PasswordResetRequest::query()->count())->toBe(2);
    Mail::assertSent(PasswordResetRequested::class, function (PasswordResetRequested $mail) use ($first, $second) {
        $usernames = collect($mail->links)->map(fn ($link) => $link['user']->username)->all();

        return $mail->hasTo('shared@example.com')
            && count($mail->links) === 2
            && in_array($first->username, $usernames, true)
            && in_array($second->username, $usernames, true);
    });
});

test('requesting a reset link for an email with no matches responds the same way and sends nothing', function () {
    Mail::fake();

    $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

    $response->assertSessionHasNoErrors();
    Mail::assertNothingSent();
    expect(PasswordResetRequest::query()->count())->toBe(0);
});

test('requesting a new link replaces a users previous pending token', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'again@example.com']);

    $this->post('/forgot-password', ['email' => 'again@example.com']);
    $firstToken = PasswordResetRequest::query()->where('user_id', $user->id)->first();

    $this->post('/forgot-password', ['email' => 'again@example.com']);

    expect(PasswordResetRequest::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(PasswordResetRequest::query()->find($firstToken->id))->toBeNull();
});

test('redeeming a valid token changes the password and lets the user log in with it', function () {
    $user = User::factory()->create();
    $token = 'a-valid-plaintext-token';
    PasswordResetRequest::query()->create(['user_id' => $user->id, 'token' => hash('sha256', $token)]);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'password' => 'a brand new passphrase',
        'password_confirmation' => 'a brand new passphrase',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/login');
    expect(Hash::check('a brand new passphrase', $user->fresh()->password))->toBeTrue();
    expect(PasswordResetRequest::query()->where('user_id', $user->id)->exists())->toBeFalse();

    $this->post('/login', ['username' => $user->username, 'password' => 'a brand new passphrase']);
    $this->assertAuthenticatedAs($user);
});

test('redeeming an unknown token is rejected and leaves the password unchanged', function () {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $response = $this->post('/reset-password', [
        'token' => 'this-token-does-not-exist',
        'password' => 'a brand new passphrase',
        'password_confirmation' => 'a brand new passphrase',
    ]);

    $response->assertInvalid(['token']);
    expect($user->fresh()->password)->toBe($originalPassword);
});

test('redeeming an expired token is rejected', function () {
    $user = User::factory()->create();
    $token = 'an-expired-plaintext-token';
    PasswordResetRequest::query()->create(['user_id' => $user->id, 'token' => hash('sha256', $token)])
        ->forceFill(['created_at' => now()->subMinutes(config('auth.passwords.users.expire', 60) + 1)])
        ->save();

    $response = $this->post('/reset-password', [
        'token' => $token,
        'password' => 'a brand new passphrase',
        'password_confirmation' => 'a brand new passphrase',
    ]);

    $response->assertInvalid(['token']);
});

test('a token cannot be redeemed twice', function () {
    $user = User::factory()->create();
    $token = 'a-single-use-token';
    PasswordResetRequest::query()->create(['user_id' => $user->id, 'token' => hash('sha256', $token)]);

    $this->post('/reset-password', [
        'token' => $token,
        'password' => 'first new passphrase',
        'password_confirmation' => 'first new passphrase',
    ])->assertSessionHasNoErrors();

    $response = $this->post('/reset-password', [
        'token' => $token,
        'password' => 'second new passphrase',
        'password_confirmation' => 'second new passphrase',
    ]);

    $response->assertInvalid(['token']);
    expect(Hash::check('first new passphrase', $user->fresh()->password))->toBeTrue();
});

test('redeeming a token for one account does not affect another account sharing the same email', function () {
    $victim = User::factory()->create(['email' => 'shared@example.com']);
    $attacker = User::factory()->create(['email' => 'shared@example.com']);
    $victimPassword = $victim->password;
    $token = 'only-for-the-attacker';
    PasswordResetRequest::query()->create(['user_id' => $attacker->id, 'token' => hash('sha256', $token)]);

    $this->post('/reset-password', [
        'token' => $token,
        'password' => 'attackers new passphrase',
        'password_confirmation' => 'attackers new passphrase',
    ])->assertSessionHasNoErrors();

    expect($victim->fresh()->password)->toBe($victimPassword);
    expect(Hash::check('attackers new passphrase', $attacker->fresh()->password))->toBeTrue();
});
