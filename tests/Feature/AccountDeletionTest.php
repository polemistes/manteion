<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventImage;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\InvitedToEvent;
use App\Notifications\NewEventComment;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores one activity notification for the user, as the queue would.
 */
function storeActivityNotificationFor(User $user): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => InvitedToEvent::class,
        'data' => ['actor_id' => null, 'subject_type' => 'event', 'subject_id' => 1, 'subject_title' => 'Event', 'url' => '/events/1', 'excerpt' => null, 'pending' => null],
    ]);
}

test('a user can delete their account with their password and is logged out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete('/profile', ['password' => 'password']);

    $response->assertRedirect('/');
    $this->assertGuest();
    expect(User::find($user->id))->toBeNull();
});

test('deleting an account requires the correct password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete('/profile', ['password' => 'wrong-password']);

    $response->assertSessionHasErrors(['password']);
    $this->assertAuthenticated();
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting an account requires a password to be provided', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete('/profile');

    $response->assertSessionHasErrors(['password']);
    expect(User::find($user->id))->not->toBeNull();
});

test('deleting an account removes the avatar and owned event images from storage, and cascades related data', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/mine.jpg']);
    Storage::disk('public')->put('avatars/mine.jpg', 'fake-content');

    $friend = User::factory()->create();
    $friendship = Friendship::factory()->accepted()->create([
        'requester_id' => $user->id,
        'addressee_id' => $friend->id,
    ]);

    $event = Event::factory()->create(['user_id' => $user->id]);
    $image = EventImage::factory()->for($event)->create(['path' => 'events/mine.jpg']);
    Storage::disk('public')->put('events/mine.jpg', 'fake-content');

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    Storage::disk('public')->assertMissing('avatars/mine.jpg');
    Storage::disk('public')->assertMissing('events/mine.jpg');
    expect(Friendship::find($friendship->id))->toBeNull();
    expect(Event::find($event->id))->toBeNull();
    expect(EventImage::find($image->id))->toBeNull();
});

test('deleting an account removes its push devices and its notifications, and nobody elses', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    PushSubscription::factory()->count(2)->create(['subscribable_id' => $user->id]);
    $othersDevice = PushSubscription::factory()->create(['subscribable_id' => $other->id]);
    storeActivityNotificationFor($user);
    storeActivityNotificationFor($other);

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    expect(PushSubscription::query()->pluck('id')->all())->toBe([$othersDevice->id])
        ->and(DatabaseNotification::query()->pluck('notifiable_id')->all())->toBe([$other->id]);
});

test('an administrator deleting an account also removes its push devices and notifications', function () {
    $administrator = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create();
    PushSubscription::factory()->create(['subscribable_id' => $member->id]);
    storeActivityNotificationFor($member);

    $this->actingAs($administrator)
        ->delete(route('admin.users.destroy', $member), ['password' => 'password'])
        ->assertRedirect(route('admin.users.index'));

    expect(User::find($member->id))->toBeNull()
        ->and(PushSubscription::query()->count())->toBe(0)
        ->and(DatabaseNotification::query()->count())->toBe(0);
});

test('deleting the account that started a conversation does not delete the conversation for the other participants', function () {
    $creator = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $creator->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    $creatorMessage = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $creator->id]);
    $otherMessage = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($creator)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    expect(Conversation::find($conversation->id))->not->toBeNull();
    expect(Conversation::find($conversation->id)->creator_id)->toBeNull();
    expect(ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $other->id)->exists())->toBeTrue();
    expect(Message::find($otherMessage->id))->not->toBeNull();
    // The deleted user's own participation and messages are still gone.
    expect(ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $creator->id)->exists())->toBeFalse();
    expect(Message::find($creatorMessage->id))->toBeNull();
});

test('a deleted account leaves nothing of theirs in other people’s Activity', function () {
    $leaving = User::factory()->create();
    $host = User::factory()->create();
    $guest = User::factory()->create();
    $staying = User::factory()->create();

    $hostEvent = Event::factory()->create(['user_id' => $host->id]);
    $comment = EventComment::factory()->create(['event_id' => $hostEvent->id, 'user_id' => $leaving->id, 'body' => 'My phone number is 99 99 99 99']);
    $host->notify(new NewEventComment($comment));

    $theirEvent = Event::factory()->create(['user_id' => $leaving->id]);
    $guest->notify(new InvitedToEvent($theirEvent, $staying));

    $guest->notify(new InvitedToEvent($hostEvent, $staying));

    $this->actingAs($leaving)->delete('/profile', ['password' => 'password'])->assertRedirect();

    expect($host->notifications()->count())->toBe(0)
        ->and($guest->notifications()->pluck('data')->pluck('subject_id')->all())->toBe([$hostEvent->id]);
});
