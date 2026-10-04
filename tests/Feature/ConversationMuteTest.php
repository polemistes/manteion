<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\BadgesUpdated;
use App\Events\IncomingMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use App\Services\BadgeCounts;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * A direct conversation between the two users, with an unread message from
 * the second to the first.
 *
 * @return array{0: User, 1: User, 2: Conversation}
 */
function chatMuteSetup(): array
{
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);

    return [$user, $friend, $conversation];
}

test('muting takes the conversation out of the messages badge, unmuting puts it back', function () {
    [$user, , $conversation] = chatMuteSetup();
    expect(BadgeCounts::for($user)['messages'])->toBe(1);

    $this->actingAs($user)->putJson("/messages/{$conversation->id}/mute", ['muted' => true])->assertNoContent();

    expect($conversation->participantFor($user)->fresh()->muted_at)->not->toBeNull();
    expect(BadgeCounts::for($user)['messages'])->toBe(0);

    $this->actingAs($user)->putJson("/messages/{$conversation->id}/mute", ['muted' => false])->assertNoContent();

    expect($conversation->participantFor($user)->fresh()->muted_at)->toBeNull();
    expect(BadgeCounts::for($user)['messages'])->toBe(1);
});

test('muting from the page goes back, and muting again keeps the original time', function () {
    [$user, , $conversation] = chatMuteSetup();

    $this->actingAs($user)
        ->from("/messages/{$conversation->id}")
        ->put("/messages/{$conversation->id}/mute", ['muted' => true])
        ->assertRedirect("/messages/{$conversation->id}");
    $mutedAt = $conversation->participantFor($user)->fresh()->muted_at;

    $this->travel(1)->hour();
    $this->actingAs($user)->putJson("/messages/{$conversation->id}/mute", ['muted' => true])->assertNoContent();

    expect($conversation->participantFor($user)->fresh()->muted_at->equalTo($mutedAt))->toBeTrue();
});

test('toggling mute updates the users badges on their other devices', function () {
    [$user, , $conversation] = chatMuteSetup();
    Event::fake([BadgesUpdated::class]);

    $this->actingAs($user)->putJson("/messages/{$conversation->id}/mute", ['muted' => true])->assertNoContent();

    Event::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $user->id);
});

test('only a participant can mute, and only their own side', function () {
    [$user, $friend, $conversation] = chatMuteSetup();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);

    $this->actingAs(User::factory()->create())->putJson("/messages/{$conversation->id}/mute", ['muted' => true])->assertForbidden();
    $this->actingAs($moderator)->putJson("/messages/{$conversation->id}/mute", ['muted' => true])->assertForbidden();

    $this->actingAs($user)->putJson("/messages/{$conversation->id}/mute", ['muted' => true])->assertNoContent();

    expect($conversation->participantFor($friend)->fresh()->muted_at)->toBeNull();
    expect(ConversationParticipant::query()->whereNotNull('muted_at')->count())->toBe(1);
});

test('muting needs a yes or a no', function () {
    [$user, , $conversation] = chatMuteSetup();

    $this->actingAs($user)->putJson("/messages/{$conversation->id}/mute", [])->assertJsonValidationErrors('muted');
    $this->actingAs($user)->putJson("/messages/{$conversation->id}/mute", ['muted' => 'perhaps'])->assertJsonValidationErrors('muted');
});

test('a new message in a muted conversation neither alerts nor pushes, but still reaches the thread', function () {
    [$user, $friend, $conversation] = chatMuteSetup();
    $conversation->participantFor($user)->update(['muted_at' => now()]);
    Event::fake([IncomingMessage::class, BadgesUpdated::class]);
    Notification::fake();

    $this->actingAs($friend)->postJson("/messages/{$conversation->id}/send", ['body' => 'Anyone?'])->assertCreated();

    Event::assertNotDispatched(IncomingMessage::class);
    Event::assertNotDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $user->id);
    Notification::assertNotSentTo($user, NewMessageReceived::class);
    $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'Anyone?']);
});
