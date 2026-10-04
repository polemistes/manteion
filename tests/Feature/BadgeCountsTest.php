<?php

use App\Enums\ConversationType;
use App\Enums\FriendshipStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use App\Notifications\InvitedToEvent;
use App\Services\BadgeCounts;
use Illuminate\Support\Str;

/**
 * A conversation with the given users as participants, none of whom has
 * read it yet.
 */
function conversationForBadges(User ...$participants): Conversation
{
    $conversation = Conversation::factory()->create([
        'type' => count($participants) > 2 ? ConversationType::Group : ConversationType::Direct,
        'creator_id' => $participants[0]->id,
    ]);

    foreach ($participants as $participant) {
        ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $participant->id]);
    }

    return $conversation;
}

test('each conversation with unread messages from others counts once', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $other = User::factory()->create();

    $direct = conversationForBadges($user, $friend);
    Message::factory()->count(3)->create(['conversation_id' => $direct->id, 'user_id' => $friend->id]);
    $group = conversationForBadges($user, $friend, $other);
    Message::factory()->create(['conversation_id' => $group->id, 'user_id' => $other->id]);
    conversationForBadges($user, $other);

    expect(BadgeCounts::for($user)['messages'])->toBe(2);
});

test('a read conversation stops counting until someone else writes again', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $conversation = conversationForBadges($user, $friend);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id, 'created_at' => now()->subHour()]);

    $conversation->participantFor($user)->update(['last_read_at' => now()->subMinutes(30)]);
    expect(BadgeCounts::for($user)['messages'])->toBe(0);

    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id, 'created_at' => now()]);
    expect(BadgeCounts::for($user)['messages'])->toBe(1);
});

test('the users own messages never count, read or not', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $neverOpened = conversationForBadges($user, $friend);
    Message::factory()->create(['conversation_id' => $neverOpened->id, 'user_id' => $user->id]);
    $opened = conversationForBadges($user, $friend);
    $opened->participantFor($user)->update(['last_read_at' => now()->subHour()]);
    Message::factory()->create(['conversation_id' => $opened->id, 'user_id' => $user->id, 'created_at' => now()]);

    expect(BadgeCounts::for($user)['messages'])->toBe(0);
    expect(BadgeCounts::for($friend)['messages'])->toBe(2);
});

test('muted conversations do not count', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $muted = conversationForBadges($user, $friend);
    Message::factory()->create(['conversation_id' => $muted->id, 'user_id' => $friend->id]);
    $muted->participantFor($user)->update(['muted_at' => now()]);
    $loud = conversationForBadges($user, $friend);
    Message::factory()->create(['conversation_id' => $loud->id, 'user_id' => $friend->id]);

    expect(BadgeCounts::for($user)['messages'])->toBe(1);

    // Muting only quiets it for the user who muted it.
    Message::factory()->create(['conversation_id' => $muted->id, 'user_id' => $user->id]);
    expect(BadgeCounts::for($friend)['messages'])->toBe(1);
});

test('conversations the user is not part of do not count', function () {
    $user = User::factory()->create();
    $conversation = conversationForBadges(User::factory()->create(), User::factory()->create());
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $conversation->creator_id]);

    expect(BadgeCounts::for($user)['messages'])->toBe(0);
});

test('activity counts the users unread notifications only', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();
    $notify = fn (User $recipient, bool $read) => $recipient->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => InvitedToEvent::class,
        'data' => ['subject_type' => 'event', 'subject_id' => 1],
        'read_at' => $read ? now() : null,
    ]);
    $notify($user, false);
    $notify($user, false);
    $notify($user, true);
    $notify($someoneElse, false);

    expect(BadgeCounts::for($user)['activity'])->toBe(2);
});

test('requests counts pending incoming friend and follow requests only', function () {
    $user = User::factory()->create();
    Friendship::factory()->create(['requester_id' => User::factory()->create()->id, 'addressee_id' => $user->id]);
    Friendship::factory()->create(['requester_id' => User::factory()->create()->id, 'addressee_id' => $user->id, 'status' => FriendshipStatus::Accepted]);
    Friendship::factory()->create(['requester_id' => User::factory()->create()->id, 'addressee_id' => $user->id, 'status' => FriendshipStatus::Declined]);
    Friendship::factory()->create(['requester_id' => $user->id, 'addressee_id' => User::factory()->create()->id]);
    Follow::factory()->pending()->create(['follower_id' => User::factory()->create()->id, 'followed_id' => $user->id]);
    Follow::factory()->create(['follower_id' => User::factory()->create()->id, 'followed_id' => $user->id]);
    Follow::factory()->pending()->create(['follower_id' => $user->id, 'followed_id' => User::factory()->create()->id]);

    expect(BadgeCounts::for($user)['requests'])->toBe(2);
});

test('someone with nothing waiting gets zero everywhere', function () {
    expect(BadgeCounts::for(User::factory()->create()))->toBe(BadgeCounts::none());
    expect(BadgeCounts::none())->toBe(['messages' => 0, 'activity' => 0, 'requests' => 0]);
});

test('GET /badges returns the current counts as JSON', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $conversation = conversationForBadges($user, $friend);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);
    Friendship::factory()->create(['requester_id' => $friend->id, 'addressee_id' => $user->id]);

    $this->actingAs($user)->getJson('/badges')
        ->assertOk()
        ->assertExactJson(['messages' => 1, 'activity' => 0, 'requests' => 1]);
});

test('GET /badges is only for signed-in users', function () {
    $this->getJson('/badges')->assertUnauthorized();
    $this->get('/badges')->assertRedirect('/login');
});

test('every page shares the badges, but a partial reload that does not ask for them skips them', function () {
    $user = User::factory()->create();
    Follow::factory()->pending()->create(['follower_id' => User::factory()->create()->id, 'followed_id' => $user->id]);

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn ($page) => $page
            ->where('badges', ['messages' => 0, 'activity' => 0, 'requests' => 1])
            ->reloadOnly('events', fn ($reload) => $reload->missing('badges')));
});

test('guests get zero badges', function () {
    $this->get('/login')
        ->assertInertia(fn ($page) => $page->where('badges', ['messages' => 0, 'activity' => 0, 'requests' => 0]));
});
