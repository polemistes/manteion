<?php

use App\Enums\ConversationType;
use App\Events\BadgesUpdated;
use App\Events\ParticipantsUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\Event;

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

test('adding someone tells the open thread and updates the new members badges', function () {
    $creator = User::factory()->create();
    $newFriend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $creator->id, 'addressee_id' => $newFriend->id]);
    $conversation = Conversation::factory()->group()->create(['creator_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => User::factory()->create()->id]);
    Event::fake([ParticipantsUpdated::class, BadgesUpdated::class]);

    $this->actingAs($creator)->post("/messages/{$conversation->id}/participants", ['user_id' => $newFriend->id])->assertRedirect();

    Event::assertDispatched(ParticipantsUpdated::class, fn (ParticipantsUpdated $event) => $event->conversationId === $conversation->id);
    Event::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $newFriend->id);

    // Adding someone who is already there changes nothing.
    $this->actingAs($creator)->post("/messages/{$conversation->id}/participants", ['user_id' => $newFriend->id])->assertRedirect();
    Event::assertDispatchedTimes(ParticipantsUpdated::class, 1);
});

test('leaving tells the open thread and drops the conversation from the leavers badges', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $conversation = Conversation::factory()->group()->create(['creator_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $participant->id]);
    $conversation->setViewing($participant, true);
    Event::fake([ParticipantsUpdated::class, BadgesUpdated::class]);

    $this->actingAs($participant)->delete("/messages/{$conversation->id}/participants")->assertRedirect('/messages');

    Event::assertDispatched(ParticipantsUpdated::class, fn (ParticipantsUpdated $event) => $event->conversationId === $conversation->id);
    Event::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $participant->id);
    expect($conversation->isBeingViewedBy($participant))->toBeFalse();
});

test('someone outside a group cannot add people to it', function () {
    $creator = User::factory()->create();
    $outsider = User::factory()->create();
    $outsidersFriend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $outsider->id, 'addressee_id' => $outsidersFriend->id]);
    $conversation = Conversation::factory()->group()->create(['creator_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $creator->id]);

    $this->actingAs($outsider)
        ->post("/messages/{$conversation->id}/participants", ['user_id' => $outsidersFriend->id])
        ->assertForbidden();
    $this->actingAs($outsider)->delete("/messages/{$conversation->id}/participants")->assertForbidden();

    expect($conversation->participants()->count())->toBe(1);
});
