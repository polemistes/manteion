<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\CallSignal;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * A direct conversation between a caller and a callee.
 *
 * @return array{0: User, 1: User, 2: Conversation}
 */
function callSignalSetup(): array
{
    $caller = User::factory()->create(['name' => 'Ada Caller']);
    $callee = User::factory()->create(['name' => 'Bo Callee']);
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $caller->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $caller->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $callee->id]);

    return [$caller, $callee, $conversation];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function callSignalPayload(array $overrides = []): array
{
    return [
        'call_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
        'type' => 'ring',
        'from_tab' => 'tab-caller',
        'to_tab' => null,
        'data' => [],
        ...$overrides,
    ];
}

test('a signal is broadcast to both participants’ own channels, the sender’s included', function () {
    Event::fake([CallSignal::class]);
    [$caller, $callee, $conversation] = callSignalSetup();
    $callId = Str::uuid()->toString();

    $this->actingAs($caller)
        ->postJson("/messages/{$conversation->id}/call", callSignalPayload([
            'call_id' => $callId,
            'type' => 'offer',
            'to_tab' => 'tab-callee',
            'data' => ['sdp' => ['type' => 'offer', 'sdp' => 'v=0']],
        ]))
        ->assertNoContent();

    Event::assertDispatched(CallSignal::class, function (CallSignal $event) use ($caller, $callee, $conversation, $callId) {
        expect(array_map('strval', $event->broadcastOn()))->toEqualCanonicalizing([
            "private-App.Models.User.{$caller->id}",
            "private-App.Models.User.{$callee->id}",
        ]);
        expect($event->broadcastAs())->toBe('call.signal');
        expect($event->broadcastWith())->toBe([
            'conversation_id' => $conversation->id,
            'call_id' => $callId,
            'type' => 'offer',
            'from' => ['id' => $caller->id, 'name' => 'Ada Caller', 'avatar_url' => $caller->avatar_url],
            'from_tab' => 'tab-caller',
            'to_tab' => 'tab-callee',
            'data' => ['sdp' => ['type' => 'offer', 'sdp' => 'v=0']],
        ]);

        return true;
    });
});

test('a session description keeps its trailing line ending', function () {
    // SDP lines end in CRLF, the last one included; a browser rejects a
    // description whose last line lost it, so the usual string trimming
    // must leave this field alone.
    Event::fake([CallSignal::class]);
    [$caller, , $conversation] = callSignalSetup();
    $sdp = "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\na=ssrc:1 msid:stream track\r\n";

    $this->actingAs($caller)
        ->postJson("/messages/{$conversation->id}/call", callSignalPayload([
            'type' => 'answer',
            'to_tab' => 'tab-caller',
            'data' => ['sdp' => ['type' => 'answer', 'sdp' => $sdp]],
        ]))
        ->assertNoContent();

    Event::assertDispatched(CallSignal::class, fn (CallSignal $event) => $event->data['sdp']['sdp'] === $sdp);
});

test('a ring without an addressee or data still goes through', function () {
    Event::fake([CallSignal::class]);
    [$caller, , $conversation] = callSignalSetup();

    $this->actingAs($caller)
        ->postJson("/messages/{$conversation->id}/call", ['call_id' => Str::uuid()->toString(), 'type' => 'ring', 'from_tab' => 'tab-caller'])
        ->assertNoContent();

    Event::assertDispatched(CallSignal::class, fn (CallSignal $event) => $event->toTab === null && $event->data === []);
});

test('outsiders, staff and group conversations cannot signal a call', function () {
    Event::fake([CallSignal::class]);
    [$caller, $callee, $conversation] = callSignalSetup();
    $outsider = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);

    $this->actingAs($outsider)->postJson("/messages/{$conversation->id}/call", callSignalPayload())->assertForbidden();
    $this->actingAs($moderator)->postJson("/messages/{$conversation->id}/call", callSignalPayload())->assertForbidden();

    $group = Conversation::factory()->group()->create(['creator_id' => $caller->id]);

    foreach ([$caller, $callee, $outsider] as $member) {
        ConversationParticipant::factory()->create(['conversation_id' => $group->id, 'user_id' => $member->id]);
    }

    $this->actingAs($caller)->postJson("/messages/{$group->id}/call", callSignalPayload())->assertForbidden();

    Event::assertNotDispatched(CallSignal::class);
});

test('a signal needs a call id, a known type and a sending tab', function () {
    Event::fake([CallSignal::class]);
    [$caller, , $conversation] = callSignalSetup();
    $url = "/messages/{$conversation->id}/call";

    $this->actingAs($caller)->postJson($url, callSignalPayload(['call_id' => 'not-a-uuid']))->assertJsonValidationErrors('call_id');
    $this->actingAs($caller)->postJson($url, callSignalPayload(['type' => 'poke']))->assertJsonValidationErrors('type');
    $this->actingAs($caller)->postJson($url, callSignalPayload(['from_tab' => '']))->assertJsonValidationErrors('from_tab');
    $this->actingAs($caller)->postJson($url, callSignalPayload(['data' => 'text']))->assertJsonValidationErrors('data');

    Event::assertNotDispatched(CallSignal::class);
});

test('guests cannot signal a call', function () {
    [, , $conversation] = callSignalSetup();

    $this->postJson("/messages/{$conversation->id}/call", callSignalPayload())->assertUnauthorized();
});

test('pages share whether calls are on and which STUN servers the browser uses', function () {
    config(['calls.stun_servers' => ['stun:stun.example.no:3478', 'stun:stun2.example.no:3478']]);
    [$caller] = callSignalSetup();

    $this->actingAs($caller)->get('/messages')->assertInertia(fn ($page) => $page->where('calls', [
        'enabled' => true,
        'iceServers' => [['urls' => 'stun:stun.example.no:3478'], ['urls' => 'stun:stun2.example.no:3478']],
    ]));
});

test('calls can be switched off', function () {
    Event::fake([CallSignal::class]);
    config(['calls.enabled' => false]);
    [$caller, , $conversation] = callSignalSetup();

    $this->actingAs($caller)->postJson("/messages/{$conversation->id}/call", callSignalPayload())->assertNotFound();
    $this->actingAs($caller)->get('/messages')->assertInertia(fn ($page) => $page->where('calls.enabled', false));

    Event::assertNotDispatched(CallSignal::class);
});
