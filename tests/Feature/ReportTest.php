<?php

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;

test('a user can report an event comment they can see', function () {
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $event = Event::factory()->searchable()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $author->id]);

    $response = $this->actingAs($reporter)->post('/reports', [
        'reportable_type' => 'comment',
        'reportable_id' => $comment->id,
        'reason' => 'Offensive language',
    ]);

    $response->assertRedirect();
    expect(Report::query()->where('reporter_id', $reporter->id)->where('reportable_id', $comment->id)->exists())->toBeTrue();
});

test('reporting the same content twice does not create a duplicate report', function () {
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $event = Event::factory()->searchable()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $author->id]);

    $this->actingAs($reporter)->post('/reports', ['reportable_type' => 'comment', 'reportable_id' => $comment->id]);
    $this->actingAs($reporter)->post('/reports', ['reportable_type' => 'comment', 'reportable_id' => $comment->id]);

    expect(Report::query()->where('reporter_id', $reporter->id)->where('reportable_id', $comment->id)->count())->toBe(1);
});

test('a user cannot report their own content', function () {
    $author = User::factory()->create();
    $event = Event::factory()->searchable()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $author->id]);

    $this->actingAs($author)->post('/reports', ['reportable_type' => 'comment', 'reportable_id' => $comment->id])
        ->assertForbidden();
});

test('a user cannot report a message in a conversation they are not part of', function () {
    $reporter = User::factory()->create();
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $recipient->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $this->actingAs($reporter)->post('/reports', ['reportable_type' => 'message', 'reportable_id' => $message->id])
        ->assertForbidden();
});

test('a user can report an event', function () {
    $reporter = User::factory()->create();
    $event = Event::factory()->searchable()->create();

    $this->actingAs($reporter)->post('/reports', ['reportable_type' => 'event', 'reportable_id' => $event->id])
        ->assertRedirect();

    expect(Report::query()->where('reportable_id', $event->id)->exists())->toBeTrue();
});
