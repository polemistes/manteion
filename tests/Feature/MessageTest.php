<?php

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\Notification;

test('a participant can send a message', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $response = $this->actingAs($user)->post("/messages/{$conversation->id}/send", ['body' => 'Hey there!']);

    $response->assertRedirect();
    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'body' => 'Hey there!',
    ]);
});

test('a non-participant cannot view or send messages in a conversation', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($outsider)->get("/messages/{$conversation->id}")->assertForbidden();
    $this->actingAs($outsider)->post("/messages/{$conversation->id}/send", ['body' => 'Sneaky'])->assertForbidden();

    $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'body' => 'Sneaky']);
});

test('sending a message notifies the other participants but not the sender', function () {
    Notification::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->post("/messages/{$conversation->id}/send", ['body' => 'Hey there!'])->assertRedirect();

    Notification::assertSentTo($other, NewMessageReceived::class);
    Notification::assertNotSentTo($user, NewMessageReceived::class);
});

test('sending a message marks the conversation as read for the sender', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    $participant = ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->post("/messages/{$conversation->id}/send", ['body' => 'Hello'])->assertRedirect();

    expect($participant->fresh()->last_read_at)->not->toBeNull();
});
