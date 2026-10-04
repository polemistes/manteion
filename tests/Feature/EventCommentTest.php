<?php

use App\Enums\UserRole;
use App\Events\BadgesUpdated;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewEventComment;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Notification;

test('the owner can always comment', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id, 'allow_invitee_comments' => false]);

    $this->actingAs($owner)->post("/events/{$event->id}/comments", ['body' => 'Welcome everyone!'])->assertRedirect();

    $this->assertDatabaseHas('event_comments', ['event_id' => $event->id, 'user_id' => $owner->id]);
});

test('a follower can comment only when the owner allows it', function () {
    $owner = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);
    $event = Event::factory()->public()->create(['user_id' => $owner->id, 'allow_invitee_comments' => false]);

    $this->actingAs($follower)->post("/events/{$event->id}/comments", ['body' => 'Can I come?'])->assertForbidden();

    $event->update(['allow_invitee_comments' => true]);

    $this->actingAs($follower)->post("/events/{$event->id}/comments", ['body' => 'Can I come?'])->assertRedirect();
    $this->assertDatabaseHas('event_comments', ['event_id' => $event->id, 'user_id' => $follower->id]);
    // Commenting is not attending: no attendee row is created behind the
    // commenter's back, which would list them among the attendees and keep
    // the event in their list after unfollowing.
    $this->assertDatabaseMissing('event_attendees', ['event_id' => $event->id, 'user_id' => $follower->id]);
});

test('a comment author or the event owner can delete a comment, but no one else', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $outsider = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $comment = EventComment::factory()->create(['event_id' => $event->id, 'user_id' => $author->id]);

    $this->actingAs($outsider)->delete("/events/{$event->id}/comments/{$comment->id}")->assertForbidden();
    $this->actingAs($owner)->delete("/events/{$event->id}/comments/{$comment->id}")->assertRedirect();
    $this->assertModelMissing($comment);
});

test('a comment notifies the host, in their language', function () {
    Notification::fake();
    $host = User::factory()->create(['locale' => 'nb']);
    $guest = User::factory()->create();
    Follow::factory()->create(['follower_id' => $guest->id, 'followed_id' => $host->id]);
    $event = Event::factory()->public()->create(['user_id' => $host->id]);

    $this->actingAs($guest)->post("/events/{$event->id}/comments", ['body' => 'Can I bring a friend?'])->assertRedirect();

    $comment = EventComment::query()->sole();
    Notification::assertSentTo($host, NewEventComment::class, fn (NewEventComment $notification) => $notification->comment->is($comment)
        && $notification->locale === 'nb');
    Notification::assertNotSentTo($guest, NewEventComment::class);
});

test('the host commenting on their own event notifies no one', function () {
    Notification::fake();
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);

    $this->actingAs($host)->post("/events/{$event->id}/comments", ['body' => 'Welcome everyone!'])->assertRedirect();

    Notification::assertNothingSent();
});

test('deleting a comment withdraws the hosts notification about it', function () {
    $host = User::factory()->create();
    $author = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $comment = EventComment::factory()->create(['event_id' => $event->id, 'user_id' => $author->id]);
    $otherComment = EventComment::factory()->create(['event_id' => $event->id, 'user_id' => $author->id]);
    $host->notify(new NewEventComment($comment));
    $host->notify(new NewEventComment($otherComment));
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($author)->delete("/events/{$event->id}/comments/{$comment->id}")->assertRedirect();

    expect($host->notifications()->sole()->data['comment_id'])->toBe($otherComment->id);
    Events::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $host->id);
});

test('a moderator replacing a comment with a placeholder withdraws the hosts notification too', function () {
    $host = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $event = Event::factory()->create(['user_id' => $host->id]);
    $comment = EventComment::factory()->create(['event_id' => $event->id, 'body' => 'Something rude']);
    $host->notify(new NewEventComment($comment));
    $host->notifications()->update(['read_at' => now()]);
    Events::fake([BadgesUpdated::class]);

    $this->actingAs($moderator)
        ->delete("/events/{$event->id}/comments/{$comment->id}", ['leave_placeholder' => true])
        ->assertRedirect();

    expect($comment->fresh()->is_deleted)->toBeTrue();
    expect($host->notifications()->count())->toBe(0);
    // It had been read, so no badge changed.
    Events::assertNotDispatched(BadgesUpdated::class);
});
