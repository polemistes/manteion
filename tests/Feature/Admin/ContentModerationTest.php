<?php

use App\Enums\ConversationType;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventImage;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;

test('staff can view a private event they are not otherwise connected to', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $event = Event::factory()->create();

    $this->actingAs($moderator)->get("/events/{$event->id}")->assertSuccessful();
});

test('a regular member cannot view a private event they are not connected to', function () {
    $member = User::factory()->create(['role' => UserRole::Member]);
    $event = Event::factory()->create();

    $this->actingAs($member)->get("/events/{$event->id}")->assertForbidden();
});

test('staff can open a conversation they are not a participant in', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $recipient->id]);

    $this->actingAs($moderator)->get("/messages/{$conversation->id}")->assertSuccessful();
});

test('a moderator can delete another users comment and leave a placeholder', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $author = User::factory()->create();
    $event = Event::factory()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $author->id, 'body' => 'Something bad']);

    $this->actingAs($moderator)
        ->delete("/events/{$event->id}/comments/{$comment->id}", ['leave_placeholder' => true])
        ->assertRedirect();

    $comment->refresh();
    expect($comment->is_deleted)->toBeTrue();
    expect($comment->body)->toBe('Deleted by a moderator');
});

test('a moderator can delete another users comment without a placeholder', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $author = User::factory()->create();
    $event = Event::factory()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $author->id]);

    $this->actingAs($moderator)->delete("/events/{$event->id}/comments/{$comment->id}")->assertRedirect();

    expect(EventComment::find($comment->id))->toBeNull();
});

test('a regular member cannot use the placeholder option on their own comment delete', function () {
    $author = User::factory()->create();
    $event = Event::factory()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $author->id]);

    $this->actingAs($author)
        ->delete("/events/{$event->id}/comments/{$comment->id}", ['leave_placeholder' => true])
        ->assertRedirect();

    // Non-staff requests are never treated as staff, regardless of the
    // leave_placeholder flag: the comment is hard-deleted as normal.
    expect(EventComment::find($comment->id))->toBeNull();
});

test('an administrator can delete another users event and leave a placeholder', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $owner = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id, 'description' => 'Original description']);

    $this->actingAs($admin)
        ->delete("/events/{$event->id}", ['leave_placeholder' => true])
        ->assertRedirect();

    $event->refresh();
    expect($event->is_deleted)->toBeTrue();
    expect($event->description)->toBe('Deleted by a moderator');
});

test('a placeholder-deleted event is excluded from the events index', function () {
    $viewer = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $viewer->id, 'is_deleted' => true]);

    $response = $this->actingAs($viewer)->get('/events');

    $response->assertInertia(fn ($page) => $page->where('events', fn ($events) => ! collect($events)->pluck('id')->contains($event->id)));
});

test('staff can delete a message and leave a placeholder', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $recipient->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'body' => 'Bad content']);

    $this->actingAs($moderator)
        ->delete("/messages/{$conversation->id}/messages/{$message->id}", ['leave_placeholder' => true])
        ->assertRedirect();

    $message->refresh();
    expect($message->is_deleted)->toBeTrue();
    expect($message->body)->toBe('Deleted by a moderator');
});

test('staff can delete an event image, a regular member cannot delete anothers', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $owner = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $image = EventImage::factory()->for($event)->create();

    $other = User::factory()->create();
    $this->actingAs($other)->delete("/events/{$event->id}/images/{$image->id}")->assertForbidden();

    $this->actingAs($moderator)->delete("/events/{$event->id}/images/{$image->id}")->assertRedirect();
    expect(EventImage::find($image->id))->toBeNull();
});

test('deleting reported content auto resolves the pending reports', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $event = Event::factory()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $author->id]);

    $report = Report::factory()->for($reporter, 'reporter')->create([
        'reportable_type' => (new EventComment)->getMorphClass(),
        'reportable_id' => $comment->id,
        'status' => ReportStatus::Pending,
    ]);

    $this->actingAs($moderator)->delete("/events/{$event->id}/comments/{$comment->id}")->assertRedirect();

    expect($report->fresh()->status)->toBe(ReportStatus::Resolved);
    expect($report->fresh()->resolved_by)->toBe($moderator->id);
});
