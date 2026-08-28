<?php

use App\Models\User;

test('a user can register a push subscription', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/push-subscriptions', [
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
    ]);
});

test('a user can remove a push subscription', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/subscription/abc', 'key', 'token');

    $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.com/subscription/abc']);

    $response = $this->actingAs($user)->delete('/push-subscriptions', [
        'endpoint' => 'https://push.example.com/subscription/abc',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/subscription/abc']);
});
