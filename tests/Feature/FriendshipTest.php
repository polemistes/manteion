<?php

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestReceived;
use Illuminate\Support\Facades\Notification;

test('a user can send a friend request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $response = $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id]);

    $response->assertRedirect();
    $this->assertDatabaseHas('friendships', [
        'requester_id' => $sender->id,
        'addressee_id' => $recipient->id,
        'status' => FriendshipStatus::Pending->value,
    ]);
});

test('sending a friend request notifies the recipient', function () {
    Notification::fake();
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id])->assertRedirect();

    Notification::assertSentTo($recipient, FriendRequestReceived::class);
});

test('a user cannot send a friend request to themselves', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/friendships', ['user_id' => $user->id]);

    $response->assertInvalid(['user_id']);
});

test('a user cannot send a duplicate friend request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);

    $response = $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id]);

    $response->assertInvalid(['user_id']);
});

test('only the addressee can accept a friend request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);

    $this->actingAs($sender)->patch("/friendships/{$friendship->id}", ['action' => 'accept'])->assertForbidden();

    $this->actingAs($recipient)->patch("/friendships/{$friendship->id}", ['action' => 'accept'])->assertRedirect();

    expect($friendship->fresh()->status)->toBe(FriendshipStatus::Accepted);
});

test('the addressee can decline a friend request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);

    $this->actingAs($recipient)->patch("/friendships/{$friendship->id}", ['action' => 'decline'])->assertRedirect();

    expect($friendship->fresh()->status)->toBe(FriendshipStatus::Declined);
});

test('either party can remove an accepted friendship', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $friendship = Friendship::factory()->accepted()->create(['requester_id' => $userA->id, 'addressee_id' => $userB->id]);

    $this->actingAs($userB)->delete("/friendships/{$friendship->id}")->assertRedirect();

    $this->assertModelMissing($friendship);
});

test('an unrelated user cannot remove a friendship', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $outsider = User::factory()->create();
    $friendship = Friendship::factory()->accepted()->create(['requester_id' => $userA->id, 'addressee_id' => $userB->id]);

    $this->actingAs($outsider)->delete("/friendships/{$friendship->id}")->assertForbidden();
});

test('accepted friendships appear in both users friend lists', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $userA->id, 'addressee_id' => $userB->id]);

    expect($userA->fresh()->isFriendsWith($userB))->toBeTrue();
    expect($userA->fresh()->friends()->pluck('id'))->toContain($userB->id);
});
