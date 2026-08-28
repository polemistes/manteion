<?php

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;

test('a sender can delete their own message, which is tombstoned rather than removed', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => 'Secret plans']);

    $response = $this->actingAs($user)->delete("/messages/{$conversation->id}/messages/{$message->id}");

    $response->assertRedirect();
    $message->refresh();
    expect($message->is_deleted)->toBeTrue();
    expect($message->body)->toBe('');
});

test('a participant cannot delete another participants message', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => 'Not yours']);

    $response = $this->actingAs($user)->delete("/messages/{$conversation->id}/messages/{$message->id}");

    $response->assertForbidden();
    expect($message->fresh()->is_deleted)->toBeFalse();
});

test('a deleted messages body is never sent to other participants', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => 'Secret plans']);

    $this->actingAs($user)->delete("/messages/{$conversation->id}/messages/{$message->id}")->assertRedirect();

    $response = $this->actingAs($other)->get("/messages/{$conversation->id}");

    $response->assertInertia(fn ($page) => $page
        ->where('messages.0.body', null)
        ->where('messages.0.is_deleted', true));
});
