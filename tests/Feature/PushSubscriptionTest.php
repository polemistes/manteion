<?php

use App\Enums\PushKind;
use App\Models\PushSubscription;
use App\Models\User;

test('a user can register a push subscription, which keeps the browsers user agent', function () {
    $user = User::factory()->create();
    $userAgent = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36';

    $response = $this->actingAs($user)
        ->withHeader('User-Agent', $userAgent)
        ->post('/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'key' => 'public-key-value',
            'token' => 'auth-token-value',
            'content_encoding' => 'aes128gcm',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $user->id,
        'subscribable_type' => User::class,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
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
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
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
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'content_encoding' => 'aes128gcm',
        ], ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
        ->assertRedirect('/settings/notifications');

    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('subscribing again from the same browser updates that device rather than adding one', function () {
    $user = User::factory()->create();
    $device = PushSubscription::factory()->for($user, 'subscribable')->create([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
    ]);

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0')
        ->postJson('/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
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
        ->postJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])
        ->assertNoContent();

    expect($user->pushSubscriptions()->sole()->user_agent)->toBe(str_repeat('a', 512));
});

test('an unknown content encoding is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'content_encoding' => 'rot13',
        ])
        ->assertInvalid(['content_encoding']);

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('a user can remove a push subscription by its endpoint', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'key', 'token');

    $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc']);

    $response = $this->actingAs($user)->delete('/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc']);
});

test('the push helper unsubscribing with JSON gets an empty 204', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'key', 'token');

    $this->actingAs($user)
        ->deleteJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])
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

test('only a browser push service can be an endpoint', function (string $endpoint, bool $allowed) {
    $response = $this->actingAs(User::factory()->create())
        ->postJson('/push-subscriptions', ['endpoint' => $endpoint, 'key' => 'k', 'token' => 't']);

    $allowed ? $response->assertNoContent() : $response->assertUnprocessable()->assertJsonValidationErrors('endpoint');
})->with([
    'Chrome' => ['https://fcm.googleapis.com/fcm/send/abc', true],
    'Firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/abc', true],
    'Edge' => ['https://wns2-par02p.notify.windows.com/w/?token=abc', true],
    'Safari' => ['https://web.push.apple.com/QGuQ', true],
    'an internal address' => ['http://127.0.0.1:6379/internal', false],
    'plain http' => ['http://fcm.googleapis.com/fcm/send/abc', false],
    'another host' => ['https://evil.example.com/fcm/send/abc', false],
    'a lookalike host' => ['https://fcm.googleapis.com.evil.example/x', false],
    'a port' => ['https://fcm.googleapis.com:8443/x', false],
    'credentials' => ['https://user@fcm.googleapis.com/x', false],
]);

test('an account keeps its newest ten devices', function () {
    $user = User::factory()->create();
    $oldest = PushSubscription::factory()->for($user, 'subscribable')->create();
    PushSubscription::factory()->count(9)->for($user, 'subscribable')->create();

    $this->actingAs($user)
        ->postJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/newest'])
        ->assertNoContent();

    expect($user->pushSubscriptions()->count())->toBe(10)
        ->and($user->pushSubscriptions()->whereKey($oldest->id)->exists())->toBeFalse();
});

/**
 * What an FCM registration token looks like.
 */
function fcmToken(string $seed = 'a'): string
{
    return str_repeat($seed, 22).':APA91b'.str_repeat($seed, 134);
}

const ANDROID_APP_USER_AGENT = 'Mozilla/5.0 (Linux; Android 15; Pixel 8 Build/AP3A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/129.0.6668.100 Mobile Safari/537.36 Manteion-Android';

test('the Android app registers itself as a device by its FCM token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('User-Agent', ANDROID_APP_USER_AGENT)
        ->postJson('/push-subscriptions', ['kind' => 'fcm', 'endpoint' => fcmToken()])
        ->assertNoContent();

    $device = $user->pushSubscriptions()->sole();
    expect($device->kind)->toBe(PushKind::Fcm)
        ->and($device->endpoint)->toBe(fcmToken())
        ->and($device->public_key)->toBeNull()
        ->and($device->auth_token)->toBeNull()
        ->and($device->content_encoding)->toBeNull()
        ->and($device->user_agent)->toBe(ANDROID_APP_USER_AGENT);
});

test('a browser is a web push device unless it says otherwise', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'content_encoding' => 'aes128gcm'])
        ->assertNoContent();

    expect($user->pushSubscriptions()->sole()->kind)->toBe(PushKind::WebPush);
});

test('registering the app again with the same token refreshes that device', function () {
    $user = User::factory()->create();
    $device = PushSubscription::factory()->app()->for($user, 'subscribable')->create(['endpoint' => fcmToken(), 'user_agent' => 'old']);

    $this->actingAs($user)
        ->withHeader('User-Agent', ANDROID_APP_USER_AGENT)
        ->postJson('/push-subscriptions', ['kind' => 'fcm', 'endpoint' => fcmToken()])
        ->assertNoContent();

    expect($user->pushSubscriptions()->sole())
        ->id->toBe($device->id)
        ->user_agent->toBe(ANDROID_APP_USER_AGENT);
});

test('an app token another account had moves to whoever signed in on that phone', function () {
    $before = User::factory()->create();
    $now = User::factory()->create();
    PushSubscription::factory()->app()->for($before, 'subscribable')->create(['endpoint' => fcmToken()]);

    $this->actingAs($now)
        ->postJson('/push-subscriptions', ['kind' => 'fcm', 'endpoint' => fcmToken()])
        ->assertNoContent();

    expect($before->pushSubscriptions()->count())->toBe(0)
        ->and($now->pushSubscriptions()->sole()->endpoint)->toBe(fcmToken());
});

test('an app device must send an FCM token', function (string $endpoint) {
    $this->actingAs(User::factory()->create())
        ->postJson('/push-subscriptions', ['kind' => 'fcm', 'endpoint' => $endpoint])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
})->with([
    'a URL' => ['https://fcm.googleapis.com/fcm/send/abc'],
    'too short' => ['abc:def'],
    'with spaces' => ['abcdefghijklmnopqrstuvwxyz 0123456789'],
]);

test('an app device sends no web push keys', function (string $field, string $value) {
    $this->actingAs(User::factory()->create())
        ->postJson('/push-subscriptions', ['kind' => 'fcm', 'endpoint' => fcmToken(), $field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'key' => ['key', 'public-key'],
    'token' => ['token', 'auth-token'],
    'content encoding' => ['content_encoding', 'aes128gcm'],
]);

test('an unknown kind of device is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/push-subscriptions', ['kind' => 'apns', 'endpoint' => fcmToken()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kind');
});

test('the ten-device limit counts browsers and app installs together', function () {
    $user = User::factory()->create();
    $oldest = PushSubscription::factory()->for($user, 'subscribable')->create();
    PushSubscription::factory()->count(5)->for($user, 'subscribable')->create();
    PushSubscription::factory()->count(4)->app()->for($user, 'subscribable')->create();

    $this->actingAs($user)
        ->postJson('/push-subscriptions', ['kind' => 'fcm', 'endpoint' => fcmToken()])
        ->assertNoContent();

    expect($user->pushSubscriptions()->count())->toBe(10)
        ->and($user->pushSubscriptions()->whereKey($oldest->id)->exists())->toBeFalse();
});

test('the app removes itself by its token', function () {
    $user = User::factory()->create();
    PushSubscription::factory()->app()->for($user, 'subscribable')->create(['endpoint' => fcmToken()]);
    $browser = PushSubscription::factory()->for($user, 'subscribable')->create();

    $this->actingAs($user)
        ->deleteJson('/push-subscriptions', ['endpoint' => fcmToken()])
        ->assertNoContent();

    expect($user->pushSubscriptions()->pluck('id')->all())->toBe([$browser->id]);
});
