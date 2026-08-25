<?php

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\User;

test('a participant can add one of their own friends to a group conversation', function () {
    $creator = User::factory()->create();
    $existingParticipant = User::factory()->create();
    $newFriend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $existingParticipant->id, 'addressee_id' => $newFriend->id]);

    $conversation = Conversation::factory()->group()->create(['creator_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $existingParticipant->id]);

    $response = $this->actingAs($existingParticipant)
        ->post("/messages/{$conversation->id}/participants", ['user_id' => $newFriend->id]);

    $response->assertRedirect();
    $this->assertDatabaseHas('conversation_participants', [
        'conversation_id' => $conversation->id,
        'user_id' => $newFriend->id,
    ]);
});

test('a participant cannot add someone who is not their own friend', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $stranger = User::factory()->create();

    $conversation = Conversation::factory()->group()->create(['creator_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $participant->id]);

    $response = $this->actingAs($participant)
        ->post("/messages/{$conversation->id}/participants", ['user_id' => $stranger->id]);

    $response->assertInvalid(['user_id']);
});

test('participants cannot be added to a direct conversation', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);

    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)
        ->post("/messages/{$conversation->id}/participants", ['user_id' => $friend->id])
        ->assertForbidden();
});

test('a participant can leave a group conversation', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $conversation = Conversation::factory()->group()->create(['creator_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $creator->id]);
    $participantRow = ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $participant->id]);

    $response = $this->actingAs($participant)->delete("/messages/{$conversation->id}/participants");

    $response->assertRedirect('/messages');
    $this->assertModelMissing($participantRow);
});

test('a participant cannot leave a direct conversation', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->delete("/messages/{$conversation->id}/participants")->assertForbidden();
});
