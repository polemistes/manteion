<?php

use App\Enums\NotificationCategory;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestReceived;
use NotificationChannels\WebPush\WebPushChannel;

test('turning one category on stores just that choice, so the others keep following their defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/notifications')
        ->put('/settings/notifications', ['push' => ['event_invites' => true]])
        ->assertRedirect('/settings/notifications')
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->notification_settings)->toBe(['push' => ['event_invites' => true]]);
    expect($user->wantsPush(NotificationCategory::EventInvites))->toBeTrue();
    expect($user->wantsPush(NotificationCategory::DirectMessages))->toBeTrue();
    expect($user->wantsPush(NotificationCategory::Followers))->toBeFalse();
});

test('a change is merged into the stored settings', function () {
    $user = User::factory()->create([
        'notification_settings' => [
            'push' => ['direct_messages' => false, 'followers' => true],
            'message_previews' => false,
        ],
    ]);

    $this->actingAs($user)
        ->put('/settings/notifications', ['push' => ['followers' => false, 'leaflet_responses' => true]])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->notification_settings)->toBe([
        'push' => ['direct_messages' => false, 'followers' => false, 'leaflet_responses' => true],
        'message_previews' => false,
    ]);
});

test('message previews can be switched on their own', function () {
    $user = User::factory()->pushDisabledFor(NotificationCategory::GroupMessages)->create();

    $this->actingAs($user)
        ->put('/settings/notifications', ['message_previews' => false])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->notification_settings)->toBe([
        'push' => ['group_messages' => false],
        'message_previews' => false,
    ]);
    expect($user->showsMessagePreviews())->toBeFalse();
});

test('a request without changes stores nothing', function (array $input) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/notifications', $input)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->notification_settings)->toBeNull();
})->with([
    'nothing at all' => [[]],
    'no categories' => [['push' => []]],
]);

test('malformed stored settings are replaced by the change', function () {
    $user = User::factory()->create(['notification_settings' => ['push' => 'yes please']]);

    $this->actingAs($user)
        ->put('/settings/notifications', ['push' => ['followers' => true]])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->notification_settings)->toBe(['push' => ['followers' => true]]);
});

test('an unknown category is rejected and nothing is stored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/notifications', ['push' => ['event_invites' => true, 'carrier_pigeons' => true]])
        ->assertInvalid(['push']);

    expect($user->fresh()->notification_settings)->toBeNull();
});

test('settings only take true or false', function (array $input, string $invalidKey) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/notifications', $input)
        ->assertInvalid([$invalidKey]);

    expect($user->fresh()->notification_settings)->toBeNull();
})->with([
    'a word for a category' => [['push' => ['event_invites' => 'yes']], 'push.event_invites'],
    'a numeric string for a category' => [['push' => ['event_invites' => '1']], 'push.event_invites'],
    'nothing for a category' => [['push' => ['event_invites' => null]], 'push.event_invites'],
    'a word for all categories' => [['push' => 'all'], 'push'],
    'a list of categories' => [['push' => [true, false]], 'push'],
    'a word for message previews' => [['message_previews' => 'off'], 'message_previews'],
    'a number for message previews' => [['message_previews' => 0], 'message_previews'],
]);

test('a category turned on in the settings reaches the users devices', function () {
    $user = User::factory()->create();
    $notification = new FriendRequestReceived(Friendship::factory()->create(['addressee_id' => $user->id]));

    expect($notification->via($user))->not->toContain(WebPushChannel::class);

    $this->actingAs($user)->put('/settings/notifications', ['push' => ['friend_requests' => true]]);

    expect($notification->via($user->fresh()))->toContain(WebPushChannel::class);
});

test('guests cannot change notification settings', function () {
    $this->put('/settings/notifications', ['message_previews' => false])->assertRedirect('/login');
});
