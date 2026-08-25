<?php

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\User;

test('a user can start a direct conversation with a friend', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);

    $response = $this->actingAs($user)->post('/messages', ['user_ids' => [$friend->id]]);

    $conversation = Conversation::query()->where('type', ConversationType::Direct)->firstOrFail();
    $response->assertRedirect("/messages/{$conversation->id}");
    expect($conversation->participants()->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$user->id, $friend->id])->sort()->values()->all());
});

test('starting a second direct conversation with the same friend reuses the existing thread', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);

    $this->actingAs($user)->post('/messages', ['user_ids' => [$friend->id]]);
    $this->actingAs($user)->post('/messages', ['user_ids' => [$friend->id]]);

    expect(Conversation::query()->count())->toBe(1);
});

test('a user cannot start a conversation with someone who is not their friend', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $response = $this->actingAs($user)->post('/messages', ['user_ids' => [$stranger->id]]);

    $response->assertInvalid(['user_ids']);
    expect(Conversation::query()->count())->toBe(0);
});

test('a user can start a group conversation with multiple friends', function () {
    $user = User::factory()->create();
    $friendA = User::factory()->create();
    $friendB = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friendA->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friendB->id]);

    $response = $this->actingAs($user)->post('/messages', [
        'user_ids' => [$friendA->id, $friendB->id],
        'name' => 'Weekend Trip',
    ]);

    $conversation = Conversation::query()->where('type', ConversationType::Group)->firstOrFail();
    $response->assertRedirect("/messages/{$conversation->id}");
    expect($conversation->name)->toBe('Weekend Trip');
    expect($conversation->participants)->toHaveCount(3);
});

test('a group conversation is rejected if any selected person is not a friend', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);

    $response = $this->actingAs($user)->post('/messages', ['user_ids' => [$friend->id, $stranger->id]]);

    $response->assertInvalid(['user_ids']);
    expect(Conversation::query()->count())->toBe(0);
});
