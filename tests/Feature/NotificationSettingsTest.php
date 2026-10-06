<?php

use App\Enums\NotificationCategory;
use App\Models\User;

test('only the message categories push by default', function () {
    $defaults = collect(NotificationCategory::cases())
        ->mapWithKeys(fn (NotificationCategory $category) => [$category->value => $category->pushDefault()])
        ->all();

    expect($defaults)->toBe([
        'direct_messages' => true,
        'group_messages' => true,
        'event_invites' => false,
        'event_comments' => false,
        'followed_events' => false,
        'friend_requests' => false,
        'followers' => false,
        'leaflet_responses' => false,
    ]);
});

test('every category is either a message or an activity category', function () {
    expect(NotificationCategory::messageCategories())
        ->toBe([NotificationCategory::DirectMessages, NotificationCategory::GroupMessages]);

    expect([...NotificationCategory::messageCategories(), ...NotificationCategory::activityCategories()])
        ->toBe(NotificationCategory::cases());
});

test('a user who never changed anything gets the complete default settings', function () {
    $user = User::factory()->create();

    expect($user->notification_settings)->toBeNull();
    expect($user->notificationSettings())->toBe([
        'push' => [
            'direct_messages' => true,
            'group_messages' => true,
            'event_invites' => false,
            'event_comments' => false,
            'followed_events' => false,
            'friend_requests' => false,
            'followers' => false,
            'leaflet_responses' => false,
        ],
        'message_previews' => true,
        'admin_email' => [
            'new_user' => false,
            'account_deleted' => false,
            'account_suspended' => false,
            'message_sent' => false,
            'event_created' => false,
            'leaflet_published' => false,
            'report_filed' => false,
        ],
    ]);
    expect($user->wantsPush(NotificationCategory::DirectMessages))->toBeTrue();
    expect($user->wantsPush(NotificationCategory::EventInvites))->toBeFalse();
    expect($user->showsMessagePreviews())->toBeTrue();
});

test('stored settings override the defaults and the rest keep them', function () {
    $user = User::factory()->create([
        'notification_settings' => [
            'push' => ['direct_messages' => false, 'event_invites' => true],
            'message_previews' => false,
        ],
    ]);

    $settings = $user->fresh()->notificationSettings();

    expect($settings['push'])->toBe([
        'direct_messages' => false,
        'group_messages' => true,
        'event_invites' => true,
        'event_comments' => false,
        'followed_events' => false,
        'friend_requests' => false,
        'followers' => false,
        'leaflet_responses' => false,
    ]);
    expect($settings['message_previews'])->toBeFalse();
    expect($user->wantsPush(NotificationCategory::DirectMessages))->toBeFalse();
    expect($user->wantsPush(NotificationCategory::GroupMessages))->toBeTrue();
    expect($user->wantsPush(NotificationCategory::EventInvites))->toBeTrue();
    expect($user->showsMessagePreviews())->toBeFalse();
});

test('unknown or malformed stored settings fall back to the defaults', function () {
    $unknownCategory = User::factory()->create([
        'notification_settings' => ['push' => ['carrier_pigeons' => true]],
    ]);
    expect($unknownCategory->notificationSettings()['push'])->not->toHaveKey('carrier_pigeons');
    expect($unknownCategory->notificationSettings()['push'])->toHaveCount(count(NotificationCategory::cases()));

    $malformed = User::factory()->create(['notification_settings' => ['push' => 'yes please']]);
    expect($malformed->wantsPush(NotificationCategory::DirectMessages))->toBeTrue();
    expect($malformed->wantsPush(NotificationCategory::Followers))->toBeFalse();
});

test('the factory push states change only the given categories', function () {
    $user = User::factory()
        ->pushEnabledFor(NotificationCategory::Followers, NotificationCategory::LeafletResponses)
        ->pushDisabledFor(NotificationCategory::GroupMessages)
        ->create();

    expect($user->fresh()->notificationSettings()['push'])->toBe([
        'direct_messages' => true,
        'group_messages' => false,
        'event_invites' => false,
        'event_comments' => false,
        'followed_events' => false,
        'friend_requests' => false,
        'followers' => true,
        'leaflet_responses' => true,
    ]);
});
