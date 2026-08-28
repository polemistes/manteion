<?php

use App\Models\Event;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use App\Notifications\FriendRequestReceived;
use App\Notifications\InvitedToEvent;
use App\Notifications\NewFollower;
use App\Notifications\NewMessageReceived;
use NotificationChannels\WebPush\WebPushChannel;

test('NewMessageReceived is sent via webpush only when both the master switch and the category are on', function () {
    $message = Message::factory()->create();

    $enabled = User::factory()->create(['push_notifications_enabled' => true, 'notify_messages' => true]);
    expect((new NewMessageReceived($message))->via($enabled))->toBe([WebPushChannel::class]);

    $categoryOff = User::factory()->create(['push_notifications_enabled' => true, 'notify_messages' => false]);
    expect((new NewMessageReceived($message))->via($categoryOff))->toBe([]);

    $masterOff = User::factory()->pushNotificationsDisabled()->create(['notify_messages' => true]);
    expect((new NewMessageReceived($message))->via($masterOff))->toBe([]);
});

test('FriendRequestReceived is sent via webpush only when both the master switch and the category are on', function () {
    $friendship = Friendship::factory()->create();

    $enabled = User::factory()->create(['push_notifications_enabled' => true, 'notify_friend_requests' => true]);
    expect((new FriendRequestReceived($friendship))->via($enabled))->toBe([WebPushChannel::class]);

    $disabled = User::factory()->create(['notify_friend_requests' => false]);
    expect((new FriendRequestReceived($friendship))->via($disabled))->toBe([]);
});

test('NewFollower is sent via webpush only when both the master switch and the category are on', function () {
    $follow = Follow::factory()->create();

    $enabled = User::factory()->create(['push_notifications_enabled' => true, 'notify_follow_requests' => true]);
    expect((new NewFollower($follow))->via($enabled))->toBe([WebPushChannel::class]);

    $disabled = User::factory()->create(['notify_follow_requests' => false]);
    expect((new NewFollower($follow))->via($disabled))->toBe([]);
});

test('InvitedToEvent keeps its database channel regardless, and adds webpush only when enabled', function () {
    $event = Event::factory()->create();
    $inviter = User::factory()->create();

    $enabled = User::factory()->create(['push_notifications_enabled' => true, 'notify_event_invites' => true]);
    expect((new InvitedToEvent($event, $inviter))->via($enabled))->toBe(['database', WebPushChannel::class]);

    $disabled = User::factory()->create(['notify_event_invites' => false]);
    expect((new InvitedToEvent($event, $inviter))->via($disabled))->toBe(['database']);
});
