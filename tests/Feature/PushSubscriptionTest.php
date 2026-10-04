<?php

use App\Models\PushSubscription;
use App\Models\User;

test('a user can register a push subscription, which keeps the browsers user agent', function () {
    $user = User::factory()->create();
    $userAgent = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36';

    $response = $this->actingAs($user)
        ->withHeader('User-Agent', $userAgent)
        ->post('/push-subscriptions', [
            'endpoint' => 'https://push.example.com/subscription/abc',
            'key' => 'public-key-value',
            'token' => 'auth-token-value',
            'content_encoding' => 'aes128gcm',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $user->id,
        'subscribable_type' => User::class,
        'endpoint' => 'https://push.example.com/subscription/abc',
        'public_key' => 'public-key-value',
        'auth_token' => 'auth-token-value',
        'content_encoding' => 'aes128gcm',
        'user_agent' => $userAgent,
    ]);
});

test('the push helper subscribing with JSON gets an empty 204 instead of a redirect', function (array $headers) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->json('POST', '/push-subscriptions', [
            'endpoint' => 'https://push.example.com/subscription/abc',
            'key' => 'public-key-value',
            'token' => 'auth-token-value',
            'content_encoding' => 'aes128gcm',
        ], $headers)
        ->assertNoContent();

    expect($user->pushSubscriptions()->count())->toBe(1);
})->with([
    'asking for JSON' => [[]],
    'a bare fetch sending JSON' => [['Accept' => '*/*']],
]);

test('an Inertia visit subscribing is sent back to its page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/notifications')
        ->json('POST', '/push-subscriptions', [
            'endpoint' => 'https://push.example.com/subscription/abc',
            'content_encoding' => 'aes128gcm',
        ], ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
        ->assertRedirect('/settings/notifications');

    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('subscribing again from the same browser updates that device rather than adding one', function () {
    $user = User::factory()->create();
    $device = PushSubscription::factory()->for($user, 'subscribable')->create([
        'endpoint' => 'https://push.example.com/subscription/abc',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
    ]);

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0')
        ->postJson('/push-subscriptions', [
            'endpoint' => 'https://push.example.com/subscription/abc',
            'key' => 'new-public-key',
            'token' => 'new-auth-token',
            'content_encoding' => 'aes128gcm',
        ])
        ->assertNoContent();

    expect($user->pushSubscriptions()->count())->toBe(1);
    $device->refresh();
    expect($device->public_key)->toBe('new-public-key');
    expect($device->user_agent)->toBe('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0');
});

test('an overlong user agent is cut to fit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('User-Agent', str_repeat('a', 600))
        ->postJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/subscription/abc'])
        ->assertNoContent();

    expect($user->pushSubscriptions()->sole()->user_agent)->toBe(str_repeat('a', 512));
});

test('an unknown content encoding is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/push-subscriptions', [
            'endpoint' => 'https://push.example.com/subscription/abc',
            'content_encoding' => 'rot13',
        ])
        ->assertInvalid(['content_encoding']);

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('a user can remove a push subscription by its endpoint', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/subscription/abc', 'key', 'token');

    $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.com/subscription/abc']);

    $response = $this->actingAs($user)->delete('/push-subscriptions', [
        'endpoint' => 'https://push.example.com/subscription/abc',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/subscription/abc']);
});

test('the push helper unsubscribing with JSON gets an empty 204', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/subscription/abc', 'key', 'token');

    $this->actingAs($user)
        ->deleteJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/subscription/abc'])
        ->assertNoContent();

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('a user can remove one of their devices from the notification settings', function () {
    $user = User::factory()->create();
    $device = PushSubscription::factory()->for($user, 'subscribable')->create();
    $otherDevice = PushSubscription::factory()->for($user, 'subscribable')->create();

    $this->actingAs($user)
        ->from('/settings/notifications')
        ->delete("/push-subscriptions/{$device->id}")
        ->assertRedirect('/settings/notifications');

    $this->assertModelMissing($device);
    $this->assertModelExists($otherDevice);
});

test('removing a device with JSON gets an empty 204', function () {
    $user = User::factory()->create();
    $device = PushSubscription::factory()->for($user, 'subscribable')->create();

    $this->actingAs($user)
        ->deleteJson("/push-subscriptions/{$device->id}")
        ->assertNoContent();

    $this->assertModelMissing($device);
});

test('a user cannot remove someone elses device', function () {
    $user = User::factory()->create();
    $someoneElsesDevice = PushSubscription::factory()->create();

    $this->actingAs($user)
        ->delete("/push-subscriptions/{$someoneElsesDevice->id}")
        ->assertForbidden();

    $this->assertModelExists($someoneElsesDevice);
});

test('removing a device that does not exist is not found', function () {
    $this->actingAs(User::factory()->create())
        ->delete('/push-subscriptions/12345')
        ->assertNotFound();
});

test('guests cannot remove devices', function () {
    $device = PushSubscription::factory()->create();

    $this->delete("/push-subscriptions/{$device->id}")->assertRedirect('/login');

    $this->assertModelExists($device);
});
