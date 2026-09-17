<?php

use App\Models\Event;
use App\Models\EventComment;
use App\Models\Follow;
use App\Models\User;

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
