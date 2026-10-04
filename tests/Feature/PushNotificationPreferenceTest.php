<?php

use App\Enums\NotificationCategory;
use App\Models\Conversation;
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

test('NewMessageReceived in a direct conversation is sent via webpush unless direct messages are turned off', function () {
    $message = Message::factory()->create();

    $byDefault = User::factory()->create();
    expect((new NewMessageReceived($message))->via($byDefault))->toBe([WebPushChannel::class]);

    $directOff = User::factory()->pushDisabledFor(NotificationCategory::DirectMessages)->create();
    expect((new NewMessageReceived($message))->via($directOff))->toBe([]);

    $groupsOff = User::factory()->pushDisabledFor(NotificationCategory::GroupMessages)->create();
    expect((new NewMessageReceived($message))->via($groupsOff))->toBe([WebPushChannel::class]);
});

test('NewMessageReceived in a group conversation follows the group messages category', function () {
    $message = Message::factory()->for(Conversation::factory()->group())->create();

    $byDefault = User::factory()->create();
    expect((new NewMessageReceived($message))->via($byDefault))->toBe([WebPushChannel::class]);

    $groupsOff = User::factory()->pushDisabledFor(NotificationCategory::GroupMessages)->create();
    expect((new NewMessageReceived($message))->via($groupsOff))->toBe([]);

    $directOff = User::factory()->pushDisabledFor(NotificationCategory::DirectMessages)->create();
    expect((new NewMessageReceived($message))->via($directOff))->toBe([WebPushChannel::class]);
});

test('FriendRequestReceived is stored and broadcast regardless, and adds webpush only once the friend requests category is turned on', function () {
    $friendship = Friendship::factory()->create();

    $byDefault = User::factory()->create();
    expect((new FriendRequestReceived($friendship))->via($byDefault))->toBe(['database', 'broadcast']);

    $enabled = User::factory()->pushEnabledFor(NotificationCategory::FriendRequests)->create();
    expect((new FriendRequestReceived($friendship))->via($enabled))->toBe(['database', 'broadcast', WebPushChannel::class]);
});

test('NewFollower is stored and broadcast regardless, and adds webpush only once the followers category is turned on', function () {
    $follow = Follow::factory()->create();

    $byDefault = User::factory()->create();
    expect((new NewFollower($follow))->via($byDefault))->toBe(['database', 'broadcast']);

    $enabled = User::factory()->pushEnabledFor(NotificationCategory::Followers)->create();
    expect((new NewFollower($follow))->via($enabled))->toBe(['database', 'broadcast', WebPushChannel::class]);
});

test('InvitedToEvent is stored and broadcast regardless, and adds webpush only once event invites are turned on', function () {
    $event = Event::factory()->create();
    $inviter = User::factory()->create();

    $byDefault = User::factory()->create();
    expect((new InvitedToEvent($event, $inviter))->via($byDefault))->toBe(['database', 'broadcast']);

    $enabled = User::factory()->pushEnabledFor(NotificationCategory::EventInvites)->create();
    expect((new InvitedToEvent($event, $inviter))->via($enabled))->toBe(['database', 'broadcast', WebPushChannel::class]);
});
