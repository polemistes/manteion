<?php

use App\Enums\NotificationCategory;
use App\Enums\ThemeMode;
use App\Enums\UserRole;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\PushTest;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    // These tests are about the props, and the committed Vite build may not
    // have the settings pages in it yet.
    $this->withoutVite();
});

test('guests are sent to the login page', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/settings', '/settings/account', '/settings/privacy', '/settings/notifications', '/settings/appearance', '/settings/language']);

test('the settings hub renders', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Index'));
});

test('the old profile page sends its visitors to the settings hub', function () {
    $this->actingAs(User::factory()->create())
        ->get('/profile')
        ->assertRedirectToRoute('settings.index');
});

test('the account page gets the account details and nothing else about the user', function () {
    $user = User::factory()->create(['bio' => 'I like board games.', 'avatar_path' => 'avatars/me.jpg']);

    $this->actingAs($user)
        ->get('/settings/account')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Account')
            ->has('user', fn ($props) => $props
                ->where('name', $user->name)
                ->where('username', $user->username)
                ->where('email', $user->email)
                ->where('bio', 'I like board games.')
                ->where('avatar_url', $user->avatar_url)));
});

test('the privacy page gets every privacy switch', function () {
    $user = User::factory()->privateFollow()->hideFromFriendsLists()->create();

    $this->actingAs($user)
        ->get('/settings/privacy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Privacy')
            ->where('privacy', [
                'allow_public_follow' => false,
                'show_friends_list' => true,
                'appear_in_friends_lists' => false,
                'searchable' => true,
                'share_typing' => true,
                'share_read_receipts' => false,
            ]));
});

test('the notifications page gets the effective settings, the categories in display order and the users own devices, newest first', function () {
    $user = User::factory()->create([
        'notification_settings' => ['push' => [NotificationCategory::Followers->value => true], 'message_previews' => false],
    ]);

    $android = PushSubscription::factory()->for($user, 'subscribable')->create(['user_agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36']);
    $iphone = PushSubscription::factory()->for($user, 'subscribable')->create(['user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1']);
    $windows = PushSubscription::factory()->for($user, 'subscribable')->create(['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0']);
    $fromBeforeUserAgents = PushSubscription::factory()->for($user, 'subscribable')->create(['user_agent' => null]);
    PushSubscription::factory()->create(['user_agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36']);

    $this->actingAs($user)
        ->get('/settings/notifications')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Notifications')
            ->where('notificationSettings', [
                'push' => [
                    'direct_messages' => true,
                    'group_messages' => true,
                    'event_invites' => false,
                    'event_comments' => false,
                    'followed_events' => false,
                    'friend_requests' => false,
                    'followers' => true,
                    'organizer_invites' => false,
                    'leaflet_responses' => false,
                ],
                'message_previews' => false,
                'admin_email' => [
                    'new_user' => false,
                    'account_deleted' => false,
                    'account_suspended' => false,
                    'message_sent' => false,
                    'event_created' => false,
                    'leaflet_published' => false,
                    'report_filed' => false,
                ],
            ])
            ->where('categories', [
                ['value' => 'direct_messages', 'push_default' => true],
                ['value' => 'group_messages', 'push_default' => true],
                ['value' => 'event_invites', 'push_default' => false],
                ['value' => 'event_comments', 'push_default' => false],
                ['value' => 'followed_events', 'push_default' => false],
                ['value' => 'friend_requests', 'push_default' => false],
                ['value' => 'followers', 'push_default' => false],
                ['value' => 'organizer_invites', 'push_default' => false],
                ['value' => 'leaflet_responses', 'push_default' => false],
            ])
            ->where('devices', [
                ['id' => $fromBeforeUserAgents->id, 'label' => 'Unknown device', 'created_at' => $fromBeforeUserAgents->created_at->toIso8601String(), 'endpoint_hash' => hash('sha256', $fromBeforeUserAgents->endpoint)],
                ['id' => $windows->id, 'label' => 'Firefox on Windows', 'created_at' => $windows->created_at->toIso8601String(), 'endpoint_hash' => hash('sha256', $windows->endpoint)],
                ['id' => $iphone->id, 'label' => 'Safari on iPhone', 'created_at' => $iphone->created_at->toIso8601String(), 'endpoint_hash' => hash('sha256', $iphone->endpoint)],
                ['id' => $android->id, 'label' => 'Chrome on Android', 'created_at' => $android->created_at->toIso8601String(), 'endpoint_hash' => hash('sha256', $android->endpoint)],
            ]));
});

test('the Android app is named as the app, in the users language', function (string $locale, string $label) {
    $user = User::factory()->create(['locale' => $locale]);
    PushSubscription::factory()->app()->for($user, 'subscribable')->create();

    $this->actingAs($user)
        ->get('/settings/notifications')
        ->assertInertia(fn ($page) => $page->where('devices.0.label', $label));
})->with([
    'English' => ['en', 'Manteion app on Android'],
    'Norwegian' => ['nb', 'Manteion-appen på Android'],
]);

test('device names follow the users language, and fall back to whichever half is recognised', function () {
    $user = User::factory()->create(['locale' => 'nb']);

    PushSubscription::factory()->for($user, 'subscribable')->create(['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0']);
    PushSubscription::factory()->for($user, 'subscribable')->create(['user_agent' => 'Mozilla/5.0 (X11; FreeBSD amd64; rv:131.0) Gecko/20100101 Firefox/131.0']);
    PushSubscription::factory()->for($user, 'subscribable')->create(['user_agent' => 'SomeApp/1.0 (Windows NT 10.0; Win64; x64)']);
    PushSubscription::factory()->for($user, 'subscribable')->create(['user_agent' => 'curl/8.9.1']);

    $this->actingAs($user)
        ->get('/settings/notifications')
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.label', 'Ukjent enhet')
            ->where('devices.1.label', 'Windows')
            ->where('devices.2.label', 'Firefox')
            ->where('devices.3.label', 'Firefox på Windows'));
});

test('the appearance page gets the light or dark mode', function () {
    $user = User::factory()->create(['theme_mode' => ThemeMode::Dark]);

    $this->actingAs($user)
        ->get('/settings/appearance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Appearance')
            ->where('theme_mode', 'dark'));
});

test('someone who never chose a mode follows the system', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/appearance')
        ->assertInertia(fn ($page) => $page->where('theme_mode', 'system'));
});

test('while impersonating, an administrator cannot change the users settings', function (string $method, string $url, array $data) {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create(['searchable' => true, 'theme_mode' => ThemeMode::Light]);
    $device = PushSubscription::factory()->for($member, 'subscribable')->create();

    $this->actingAs($admin)->post("/admin/users/{$member->id}/impersonate");

    $this->call($method, str_replace('{device}', (string) $device->id, $url), $data)->assertForbidden();

    $member->refresh();
    expect($member->searchable)->toBeTrue();
    expect($member->theme_mode)->toBe(ThemeMode::Light);
    expect($member->notification_settings)->toBeNull();
    $this->assertModelExists($device);
})->with([
    'privacy' => ['PUT', '/settings/privacy', ['searchable' => false]],
    'notifications' => ['PUT', '/settings/notifications', ['push' => ['event_invites' => true]]],
    'appearance' => ['PUT', '/settings/appearance', ['theme_mode' => 'dark']],
    'a device' => ['DELETE', '/push-subscriptions/{device}', []],
]);

test('the language page renders', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/language')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Language'));
});

test('a test push goes to the device that asked, and only to it', function () {
    Notification::fake();
    $user = User::factory()->create();
    $device = PushSubscription::factory()->for($user, 'subscribable')->create();
    $other = PushSubscription::factory()->for($user, 'subscribable')->create();

    $this->actingAs($user)
        ->postJson('/settings/notifications/test', ['endpoint' => $device->endpoint])
        ->assertNoContent();

    Notification::assertSentTo($user, PushTest::class, function (PushTest $notification, array $channels) use ($user, $device) {
        $routed = $user->routeNotificationForWebPush($notification);

        return $channels === [WebPushChannel::class, FcmChannel::class]
            && $routed->pluck('id')->all() === [$device->id];
    });
    expect($user->routeNotificationForWebPush()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$device->id, $other->id])->sort()->values()->all());
});

test('every test push has a tag of its own, so a new one is not a quiet replacement', function () {
    $user = User::factory()->create();
    $device = PushSubscription::factory()->for($user, 'subscribable')->create();
    $notification = new PushTest($device->endpoint);

    $this->travelTo(now());
    $first = $notification->toWebPush($user, $notification)->toArray();
    $this->travelTo(now()->addSecond());
    $second = $notification->toWebPush($user, $notification)->toArray();

    expect($first['tag'])->toStartWith('push-test')
        ->and($second['tag'])->not->toBe($first['tag'])
        ->and($first['renotify'])->toBeTrue();
});

test('a test push is only for the users own devices', function () {
    Notification::fake();
    $someoneElses = PushSubscription::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson('/settings/notifications/test', ['endpoint' => $someoneElses->endpoint])
        ->assertNotFound();

    Notification::assertNothingSent();
});
