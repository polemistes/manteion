<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A conversation with the given users as participants, in that order. Two
 * people make a direct conversation, more a group (named if given a name).
 *
 * @param  list<User>  $participants
 */
function chatListConversation(array $participants, ?string $name = null): Conversation
{
    $conversation = Conversation::factory()->create([
        'type' => count($participants) > 2 ? ConversationType::Group : ConversationType::Direct,
        'name' => $name,
        'creator_id' => $participants[0]->id,
    ]);

    foreach ($participants as $participant) {
        ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $participant->id]);
    }

    return $conversation;
}

test('a direct conversation is listed from the viewers side', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create(['name' => 'Kari Nordmann', 'avatar_path' => 'avatars/kari.jpg']);
    $conversation = chatListConversation([$user, $friend]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id, 'body' => "Hello\n\nthere"]);

    $this->actingAs($user)->get('/messages')
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/Index')
            ->has('conversations', 1)
            ->where('conversations.0', [
                'id' => $conversation->id,
                'type' => 'direct',
                'title' => 'Kari Nordmann',
                'avatar_url' => $friend->avatar_url,
                'avatars' => [['id' => $friend->id, 'name' => 'Kari Nordmann', 'avatar_url' => $friend->avatar_url]],
                'last_message' => [
                    'body' => 'Hello there',
                    'is_mine' => false,
                    'sender_name' => 'Kari Nordmann',
                    'is_deleted' => false,
                    'created_at' => $message->created_at->toIso8601String(),
                ],
                'unread' => true,
                'unread_count' => 1,
                'muted' => false,
                'activity_at' => $message->created_at->toIso8601String(),
            ]));
});

test('a group without a name is called after its other members and shows at most three faces', function () {
    $user = User::factory()->create(['name' => 'Me']);
    $others = collect(['Anne', 'Bjørn', 'Cecilie', 'Dag'])->map(fn (string $name) => User::factory()->create(['name' => $name]));
    $unnamed = chatListConversation([$user, ...$others]);
    $named = chatListConversation([$user, $others[0], $others[1]], 'Hyttetur');

    $conversations = collect($this->actingAs($user)->get('/messages')->inertiaProps('conversations'))->keyBy('id');

    expect($conversations)->toHaveCount(2);
    expect($conversations[$unnamed->id])
        ->type->toBe('group')
        ->title->toBe('Anne, Bjørn, Cecilie, Dag')
        ->avatar_url->toBeNull()
        ->last_message->toBeNull();
    expect(collect($conversations[$unnamed->id]['avatars'])->pluck('name')->all())->toBe(['Anne', 'Bjørn', 'Cecilie']);
    expect($conversations[$named->id]['title'])->toBe('Hyttetur');
});

test('unread counts only messages from others since the viewer last read the conversation', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $neverRead = chatListConversation([$user, $friend]);
    Message::factory()->count(3)->create(['conversation_id' => $neverRead->id, 'user_id' => $friend->id]);
    Message::factory()->create(['conversation_id' => $neverRead->id, 'user_id' => $user->id]);

    $read = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $read->id, 'user_id' => $friend->id, 'created_at' => now()->subHours(2)]);
    $read->participantFor($user)->update(['last_read_at' => now()->subHour()]);
    Message::factory()->count(2)->create(['conversation_id' => $read->id, 'user_id' => $friend->id, 'created_at' => now()->subMinutes(10)]);

    $upToDate = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $upToDate->id, 'user_id' => $friend->id, 'created_at' => now()->subHour()]);
    $upToDate->participantFor($user)->update(['last_read_at' => now()]);

    $conversations = collect($this->actingAs($user)->get('/messages')->inertiaProps('conversations'))->keyBy('id');

    expect($conversations[$neverRead->id])->unread_count->toBe(3)->unread->toBeTrue();
    expect($conversations[$read->id])->unread_count->toBe(2)->unread->toBeTrue();
    expect($conversations[$upToDate->id])->unread_count->toBe(0)->unread->toBeFalse();
});

test('a muted conversation is flagged, and still shows that it has unread messages', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $conversation = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);
    $conversation->participantFor($user)->update(['muted_at' => now()]);

    $this->actingAs($user)->get('/messages')
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.muted', true)
            ->where('conversations.0.unread_count', 1));
});

test('conversations are listed by latest activity, an empty one by when it was started', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $old = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $old->id, 'user_id' => $friend->id, 'created_at' => now()->subDays(3)]);
    $recent = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $recent->id, 'user_id' => $user->id, 'created_at' => now()->subMinute()]);
    $empty = Conversation::factory()->create(['creator_id' => $user->id, 'created_at' => now()->subDay()]);
    ConversationParticipant::factory()->create(['conversation_id' => $empty->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $empty->id, 'user_id' => $friend->id]);

    $this->actingAs($user)->get('/messages')
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.id', $recent->id)
            ->where('conversations.0.last_message.is_mine', true)
            ->where('conversations.1.id', $empty->id)
            ->where('conversations.1.activity_at', $empty->created_at->toIso8601String())
            ->where('conversations.2.id', $old->id));
});

test('only the viewers own conversations are listed', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $mine = chatListConversation([$user, $friend]);
    chatListConversation([$friend, User::factory()->create()]);

    $this->actingAs($user)->get('/messages')
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversations', 1)
            ->where('conversations.0.id', $mine->id));
});

test('a deleted last message shows no text, while a moderator placeholder does', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $wiped = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $wiped->id, 'user_id' => $friend->id, 'body' => '', 'is_deleted' => true]);
    $moderated = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $moderated->id, 'user_id' => $friend->id, 'body' => 'Deleted by a moderator', 'is_deleted' => true]);

    $conversations = collect($this->actingAs($user)->get('/messages')->inertiaProps('conversations'))->keyBy('id');

    expect($conversations[$wiped->id]['last_message'])->body->toBeNull()->is_deleted->toBeTrue();
    expect($conversations[$moderated->id]['last_message'])->body->toBe('Deleted by a moderator')->is_deleted->toBeTrue();
});

test('a long last message is shortened for the list', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $conversation = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id, 'body' => str_repeat('a', 300)]);

    $this->actingAs($user)->get('/messages')
        ->assertInertia(fn (Assert $page) => $page->where('conversations.0.last_message.body', str_repeat('a', 120).'…'));
});

test('a direct conversation whose other person deleted their account still lists', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $conversation = chatListConversation([$user, $friend]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => 'Are you there?']);
    $friend->delete();

    $this->actingAs($user)->get('/messages')
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.title', 'Deleted account')
            ->where('conversations.0.avatar_url', null)
            ->where('conversations.0.avatars', []));

    $this->actingAs($user)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->where('conversation.title', 'Deleted account'));
});

test('the list costs the same few queries however many conversations there are', function () {
    $user = User::factory()->create();
    $addConversations = function (int $count) use ($user) {
        foreach (range(1, $count) as $i) {
            $others = User::factory()->count(2)->create();
            $conversation = chatListConversation([$user, ...$others]);
            Message::factory()->count(3)->create(['conversation_id' => $conversation->id, 'user_id' => $others[0]->id]);
        }
    };
    $queriesFor = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/messages')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $addConversations(1);
    $queriesFor();
    $withOne = $queriesFor();

    $addConversations(6);
    $withSeven = $queriesFor();

    expect($withSeven)->toBe($withOne);
});

test('a conversation page has the conversation from the viewers side, its participants and the list beside it', function () {
    $user = User::factory()->create(['name' => 'Me']);
    $friend = User::factory()->create(['name' => 'Ola', 'avatar_path' => 'avatars/ola.jpg']);
    $candidate = User::factory()->create(['name' => 'Per']);
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $candidate->id]);
    $conversation = chatListConversation([$user, $friend]);
    $conversation->participantFor($user)->update(['muted_at' => now()]);
    $other = chatListConversation([$user, $candidate]);

    $this->actingAs($user)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/Show')
            ->where('conversation', [
                'id' => $conversation->id,
                'type' => 'direct',
                'title' => 'Ola',
                'avatar_url' => $friend->avatar_url,
                'muted' => true,
            ])
            ->where('participants', [
                ['id' => $user->id, 'name' => 'Me', 'username' => $user->username, 'avatar_url' => null, 'is_me' => true],
                ['id' => $friend->id, 'name' => 'Ola', 'username' => $friend->username, 'avatar_url' => $friend->avatar_url, 'is_me' => false],
            ])
            ->has('messages', 0)
            ->where('hasOlder', false)
            ->where('canAddParticipant', false)
            ->where('canLeave', false)
            ->has('addCandidates', 1)
            ->where('addCandidates.0.id', $candidate->id)
            ->has('conversations', 2)
            ->where('conversations', fn ($conversations) => collect($conversations)->pluck('id')->sort()->values()->all() === [$conversation->id, $other->id]));
});

test('older messages are flagged only when there are more than one page', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $conversation = chatListConversation([$user, $friend]);
    Message::factory()->count(50)->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);

    $this->actingAs($user)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->has('messages', 50)->where('hasOlder', false));

    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);

    $this->actingAs($user)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->has('messages', 50)->where('hasOlder', true));
});

test('staff looking into a reported conversation see both people and change nothing', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $a = User::factory()->create(['name' => 'Anne']);
    $b = User::factory()->create(['name' => 'Bjørn']);
    $conversation = chatListConversation([$a, $b]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $a->id]);

    $this->actingAs($moderator)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation.title', 'Anne, Bjørn')
            ->where('conversation.muted', false)
            ->has('messages', 1)
            ->has('conversations', 0));

    expect(ConversationParticipant::query()->whereNotNull('last_read_at')->exists())->toBeFalse();
});

test('a conversation page costs the same few queries however many messages and reactions it shows', function () {
    $user = User::factory()->create();
    $others = User::factory()->count(3)->create();
    $conversation = chatListConversation([$user, ...$others]);
    $addMessages = function (int $count) use ($conversation, $others) {
        foreach (range(1, $count) as $i) {
            $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $others[$i % 3]->id]);
            Reaction::factory()->create(['user_id' => $others[($i + 1) % 3]->id, 'reactable_type' => 'message', 'reactable_id' => $message->id]);
        }
    };
    $queriesFor = function () use ($user, $conversation): int {
        // Read already, so no load also marks it read (whose queries depend on
        // whether the clock ticked into a new second since the last one).
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()->addHour()]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get("/messages/{$conversation->id}")->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $addMessages(2);
    $queriesFor();
    $withTwo = $queriesFor();

    $addMessages(20);
    $withTwentyTwo = $queriesFor();

    expect($withTwentyTwo)->toBe($withTwo);
});
