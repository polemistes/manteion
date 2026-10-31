<?php

use App\Enums\CallPushKind;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Notifications\CallNotification;
use App\Notifications\Channels\FcmChannel;
use App\Services\CallRings;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * A caller, a callee, their direct conversation and a ring the callee is
 * being offered.
 *
 * @return array{0: User, 1: User, 2: Conversation, 3: array<string, mixed>}
 */
function callPushSetup(): array
{
    $caller = User::factory()->create(['name' => 'Kari', 'avatar_path' => 'avatars/kari.jpg']);
    $callee = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $caller->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $caller->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $callee->id]);
    $ring = CallRings::ring($callee->id, $conversation, $caller, '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', 'tab-caller');

    return [$caller, $callee, $conversation, $ring];
}

/**
 * @param  array<string, mixed>  $ring
 * @return array{payload: array<string, mixed>, options: array<string, mixed>}
 */
function callPushFor(CallPushKind $kind, Conversation $conversation, User $caller, User $callee, array $ring): array
{
    $notification = CallNotification::about($kind, $conversation, $caller, $ring);
    $push = $notification->toWebPush($callee, $notification);

    return ['payload' => json_decode(json_encode($push->toArray()), true), 'options' => $push->getOptions()];
}

test('the ring push names the caller, shares the call’s tag and expires with the ring', function () {
    [$caller, $callee, $conversation, $ring] = callPushSetup();

    $push = callPushFor(CallPushKind::Ring, $conversation, $caller, $callee, $ring);

    expect($push['payload'])->toBe([
        'title' => 'Incoming video call',
        'badge' => '/icons/badge-96.png',
        'body' => 'Kari is calling',
        'icon' => url($caller->avatar_url),
        'lang' => 'en',
        'renotify' => true,
        'tag' => 'call-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
        'data' => [
            'url' => "/messages/{$conversation->id}",
            'type' => 'call',
            'category' => 'video_calls',
            'conversation_id' => $conversation->id,
            'call_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
            'caller_name' => 'Kari',
            'caller_avatar' => url($caller->avatar_url),
            'started_at' => $ring['started_at'],
            'expires_at' => $ring['expires_at'],
        ],
        'timestamp' => $ring['started_at'],
    ]);
    expect($push['options']['TTL'])->toBeLessThanOrEqual(CallRings::SECONDS)->toBeGreaterThan(0);
    expect($push['options']['urgency'])->toBe('high');
    expect($push['options']['topic'])->toBe("call-{$conversation->id}");
});

test('missed and over pushes replace the ring under the same tag', function () {
    [$caller, $callee, $conversation, $ring] = callPushSetup();

    $missed = callPushFor(CallPushKind::Missed, $conversation, $caller, $callee, $ring);
    $over = callPushFor(CallPushKind::Over, $conversation, $caller, $callee, $ring);

    expect($missed['payload']['title'])->toBe('Missed video call');
    expect($missed['payload']['body'])->toBe('Kari called');
    expect($missed['payload']['tag'])->toBe('call-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d');
    expect($missed['payload']['data']['type'])->toBe('missed_call');
    expect($missed['options']['TTL'])->toBe(86400);
    expect($missed['options']['urgency'])->toBe('normal');

    expect($over['payload']['title'])->toBe('Call ended');
    expect($over['payload']['tag'])->toBe('call-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d');
    expect($over['payload']['data']['type'])->toBe('call_over');
    expect($over['options']['TTL'])->toBe(60);
});

test('the push is in the callee’s language', function () {
    [$caller, $callee, $conversation, $ring] = callPushSetup();
    $callee->update(['locale' => 'nb']);
    $notification = CallNotification::about(CallPushKind::Ring, $conversation, $caller, $ring)->locale('nb');

    $push = app()->call(fn () => $notification->locale === 'nb' ? app('translator')->setLocale('nb') : null);
    $payload = json_decode(json_encode($notification->toWebPush($callee, $notification)->toArray()), true);

    expect($payload['title'])->toBe('Innkommende videosamtale');
    expect($payload['body'])->toBe('Kari ringer');
});

test('calls go to browsers and the app while the category is on', function () {
    [$caller, $callee, $conversation, $ring] = callPushSetup();
    $notification = CallNotification::about(CallPushKind::Ring, $conversation, $caller, $ring);

    expect($notification->via($callee))->toBe([WebPushChannel::class, FcmChannel::class]);
    expect($notification->shouldSend($callee, WebPushChannel::class))->toBeTrue();

    $callee->update(['notification_settings' => ['push' => ['video_calls' => false]]]);

    expect($notification->via($callee->fresh()))->toBe([]);
    expect($notification->shouldSend($callee->fresh(), WebPushChannel::class))->toBeFalse();
});

test('a ring is not pushed once settled, to a muted conversation, or to someone looking at it', function () {
    [$caller, $callee, $conversation, $ring] = callPushSetup();
    $ringing = CallNotification::about(CallPushKind::Ring, $conversation, $caller, $ring);
    $missed = CallNotification::about(CallPushKind::Missed, $conversation, $caller, $ring);

    $conversation->setViewing($callee, true);
    expect($ringing->shouldSend($callee, WebPushChannel::class))->toBeFalse();
    $conversation->setViewing($callee, false);
    expect($ringing->shouldSend($callee, WebPushChannel::class))->toBeTrue();

    $conversation->participantFor($callee)->update(['muted_at' => now()]);
    expect($ringing->shouldSend($callee, WebPushChannel::class))->toBeFalse();
    expect($missed->shouldSend($callee, WebPushChannel::class))->toBeTrue();
    $conversation->participantFor($callee)->update(['muted_at' => null]);

    CallRings::settle($callee->id, $ring['call_id']);
    expect($ringing->shouldSend($callee->fresh(), WebPushChannel::class))->toBeFalse();
    expect($missed->shouldSend($callee->fresh(), WebPushChannel::class))->toBeTrue();
});
