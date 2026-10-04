<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\BadgesUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\BadgeCounts;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('the unread messages count reflects messages from other participants only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn ($page) => $page->where('badges.messages', 0));

    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn ($page) => $page->where('badges.messages', 1));
});

test('viewing a conversation marks it as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->get("/messages/{$conversation->id}")->assertOk();

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn ($page) => $page->where('badges.messages', 0));
});

test('a users own message does not count as unread for themselves', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn ($page) => $page->where('badges.messages', 0));
});

test('reading through the read endpoint clears the badge and tells the readers other devices', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    expect(BadgeCounts::for($user)['messages'])->toBe(1);
    Event::fake([BadgesUpdated::class]);

    $this->actingAs($user)->postJson("/messages/{$conversation->id}/read")->assertNoContent();

    expect(BadgeCounts::for($user)['messages'])->toBe(0);
    expect($conversation->participantFor($user)->fresh()->last_read_at)->not->toBeNull();
    Event::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $user->id);
});

test('only participants can mark a conversation read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs(User::factory()->create())->postJson("/messages/{$conversation->id}/read")->assertForbidden();
    $this->actingAs($moderator)->postJson("/messages/{$conversation->id}/read")->assertForbidden();
    $this->actingAs($user)->postJson('/messages/999999/read')->assertNotFound();

    expect(ConversationParticipant::query()->whereNotNull('last_read_at')->exists())->toBeFalse();
});

test('opening a conversation with something unread updates the readers badges everywhere', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    Event::fake([BadgesUpdated::class]);

    $this->actingAs($user)->get("/messages/{$conversation->id}")->assertOk();
    Event::assertDispatchedTimes(BadgesUpdated::class, 1);

    // Nothing new since: opening it again changes no count.
    $this->actingAs($user)->get("/messages/{$conversation->id}")->assertOk();
    Event::assertDispatchedTimes(BadgesUpdated::class, 1);
});

test('a partial reload of a conversation does not mark it read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $response = $this->actingAs($user)->get("/messages/{$conversation->id}")->assertOk();

    $this->travel(1)->minute();
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => 'Arrived in a background tab']);

    $response->assertInertia(fn (Assert $page) => $page->reloadOnly('messages', fn (Assert $reload) => $reload
        ->where('messages.0.body', 'Arrived in a background tab')
        ->missing('conversations')
        ->missing('addCandidates')));

    expect(BadgeCounts::for($user)['messages'])->toBe(1);
});

test('a prefetch of a conversation does not mark it read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->get("/messages/{$conversation->id}", ['Purpose' => 'prefetch'])->assertOk();

    expect(BadgeCounts::for($user)['messages'])->toBe(1);
});

test('an administrator browsing as a member does not mark the members conversations read', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)
        ->withSession(['impersonator_id' => $admin->id])
        ->get("/messages/{$conversation->id}")
        ->assertOk();
    $this->actingAs($user)
        ->withSession(['impersonator_id' => $admin->id])
        ->postJson("/messages/{$conversation->id}/read")
        ->assertForbidden();

    expect(BadgeCounts::for($user)['messages'])->toBe(1);
});
