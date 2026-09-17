<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
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

test('staff cannot send messages into a conversation they are not part of', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $a->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $a->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $b->id]);

    $this->actingAs($moderator)->get("/messages/{$conversation->id}")->assertSuccessful();
    $this->actingAs($moderator)->post("/messages/{$conversation->id}/send", ['body' => 'Hello from staff'])->assertForbidden();

    $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'body' => 'Hello from staff']);
});

test('a conversation shows the newest fifty messages and older ones can be fetched page by page', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    foreach (range(1, 60) as $number) {
        Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => "Message {$number}"]);
    }

    $this->actingAs($user)->get("/messages/{$conversation->id}")
        ->assertInertia(fn ($page) => $page
            ->has('messages', 50)
            ->where('messages.0.body', 'Message 11')
            ->where('messages.49.body', 'Message 60')
            ->where('hasOlder', true));

    $eleventh = Message::query()->where('body', 'Message 11')->firstOrFail();

    $this->actingAs($user)->getJson("/messages/{$conversation->id}/messages?before={$eleventh->id}")
        ->assertOk()
        ->assertJsonCount(10, 'messages')
        ->assertJsonPath('messages.0.body', 'Message 1')
        ->assertJsonPath('messages.9.body', 'Message 10')
        ->assertJsonPath('has_older', false);
});

test('older messages cannot be fetched by a non-participant', function () {
    $user = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs($outsider)->getJson("/messages/{$conversation->id}/messages?before=".($message->id + 1))->assertForbidden();
});
