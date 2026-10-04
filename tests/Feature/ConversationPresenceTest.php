<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * @return array{0: User, 1: Conversation}
 */
function chatPresenceSetup(): array
{
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => User::factory()->create()->id]);

    return [$user, $conversation];
}

test('a viewing heartbeat marks the user as looking at the thread, and false ends it', function () {
    [$user, $conversation] = chatPresenceSetup();
    $key = "conversation.{$conversation->id}.viewer.{$user->id}";

    $this->actingAs($user)->postJson("/messages/{$conversation->id}/presence", ['viewing' => true])->assertNoContent();

    expect(Cache::has($key))->toBeTrue();
    expect($conversation->isBeingViewedBy($user))->toBeTrue();

    $this->actingAs($user)->postJson("/messages/{$conversation->id}/presence", ['viewing' => false])->assertNoContent();

    expect(Cache::has($key))->toBeFalse();
});

test('a heartbeat lapses after 45 seconds unless renewed', function () {
    [$user, $conversation] = chatPresenceSetup();

    $this->actingAs($user)->postJson("/messages/{$conversation->id}/presence", ['viewing' => true])->assertNoContent();

    $this->travel(30)->seconds();
    expect($conversation->isBeingViewedBy($user))->toBeTrue();
    $this->actingAs($user)->postJson("/messages/{$conversation->id}/presence", ['viewing' => true])->assertNoContent();

    $this->travel(40)->seconds();
    expect($conversation->isBeingViewedBy($user))->toBeTrue();

    $this->travel(6)->seconds();
    expect($conversation->isBeingViewedBy($user))->toBeFalse();
});

test('only participants can report themselves as viewing, for themselves', function () {
    [$user, $conversation] = chatPresenceSetup();
    $outsider = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);

    $this->actingAs($outsider)->postJson("/messages/{$conversation->id}/presence", ['viewing' => true])->assertForbidden();
    $this->actingAs($moderator)->postJson("/messages/{$conversation->id}/presence", ['viewing' => true])->assertForbidden();

    expect($conversation->isBeingViewedBy($outsider))->toBeFalse();
    expect($conversation->isBeingViewedBy($moderator))->toBeFalse();
    expect($conversation->isBeingViewedBy($user))->toBeFalse();
});

test('a heartbeat needs to say whether the user is viewing', function () {
    [$user, $conversation] = chatPresenceSetup();

    $this->actingAs($user)->postJson("/messages/{$conversation->id}/presence", [])->assertJsonValidationErrors('viewing');
});
