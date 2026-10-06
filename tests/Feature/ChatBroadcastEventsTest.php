<?php

use App\Enums\ConversationType;
use App\Events\IncomingMessage;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\ParticipantsUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;
use App\Services\MessageSummary;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Exceptions;

/**
 * Route broadcasts through a broadcaster that records them (or, with
 * $failing, throws like an unreachable WebSocket server) instead of the
 * test environment's "null" driver.
 */
function chatBroadcaster(bool $failing = false): object
{
    $broadcaster = new class($failing) implements Broadcaster
    {
        /** @var list<array{channels: list<string>, event: string, payload: array<string, mixed>}> */
        public array $sent = [];

        public function __construct(private bool $failing) {}

        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = [])
        {
            if ($this->failing) {
                throw new BroadcastException('Connection refused');
            }

            $this->sent[] = ['channels' => array_map('strval', $channels), 'event' => $event, 'payload' => $payload];
        }

        /**
         * @return list<array{channels: list<string>, event: string, payload: array<string, mixed>}>
         */
        public function named(string $event): array
        {
            return array_values(array_filter($this->sent, fn (array $sent) => $sent['event'] === $event));
        }
    };

    Broadcast::extend('chat-test', fn () => $broadcaster);
    config([
        'broadcasting.default' => 'chat-test',
        'broadcasting.connections.chat-test' => ['driver' => 'chat-test'],
    ]);
    Broadcast::forgetDrivers();

    return $broadcaster;
}

/**
 * A conversation between the given users, in that order.
 *
 * @param  list<User>  $participants
 */
function chatBroadcastConversation(array $participants, ?string $name = null): Conversation
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

test('chat events go out right away on their channels under the names the client listens for', function () {
    [$a, $b] = User::factory()->count(2)->create()->sortBy('id')->values()->all();
    $conversation = chatBroadcastConversation([$a, $b]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $a->id]);
    $channel = "private-conversations.{$conversation->id}";
    $participants = ["private-App.Models.User.{$a->id}", "private-App.Models.User.{$b->id}"];

    $events = [
        [new MessageSent($message), $participants, 'message.sent'],
        [new MessageUpdated($message), $participants, 'message.updated'],
        [new ParticipantsUpdated($message->conversation_id), [$channel], 'participants.updated'],
        [new IncomingMessage(42, $message), ['private-App.Models.User.42'], 'message.received'],
    ];

    foreach ($events as [$event, $channels, $name]) {
        expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
        expect(array_map('strval', $event->broadcastOn()))->toBe($channels);
        expect($event->broadcastAs())->toBe($name);
    }

    expect((new ParticipantsUpdated($message->conversation_id))->broadcastWith())->toBe([]);
});

test('a broadcast message is the same for everyone and says who reacted', function () {
    $sender = User::factory()->create(['avatar_path' => 'avatars/sender.jpg']);
    [$a, $b, $c] = User::factory()->count(3)->create();
    $message = Message::factory()->create(['user_id' => $sender->id, 'body' => 'Pizza tonight?']);
    foreach ([[$a, '👍'], [$b, '🎉'], [$c, '👍']] as [$user, $emoji]) {
        Reaction::factory()->create(['user_id' => $user->id, 'reactable_type' => 'message', 'reactable_id' => $message->id, 'emoji' => $emoji]);
    }

    expect((new MessageSent($message->fresh()))->broadcastWith())->toBe(['message' => [
        'id' => $message->id,
        'conversation_id' => $message->conversation_id,
        'body' => 'Pizza tonight?',
        'is_deleted' => false,
        'created_at' => $message->created_at->toIso8601String(),
        'sender' => ['id' => $sender->id, 'name' => $sender->name, 'username' => $sender->username, 'avatar_url' => $sender->avatar_url],
        'reactions' => [
            ['emoji' => '👍', 'count' => 2, 'user_ids' => [$a->id, $c->id]],
            ['emoji' => '🎉', 'count' => 1, 'user_ids' => [$b->id]],
        ],
        'seen_by' => [],
    ]]);
});

test('broadcast reactions come in the order a page load shows them', function () {
    $viewer = User::factory()->create();
    $message = Message::factory()->create();
    $reactions = ['😂', '❤️', '🎉', '❤️', '👍', '😂', '🙏', '❤️'];
    foreach ($reactions as $emoji) {
        Reaction::factory()->create(['user_id' => User::factory()->create()->id, 'reactable_type' => 'message', 'reactable_id' => $message->id, 'emoji' => $emoji]);
    }
    $message = $message->fresh();

    $broadcast = collect(MessageSummary::broadcast($message)['reactions'])->map(fn (array $reaction) => [$reaction['emoji'], $reaction['count']]);
    $pageLoad = collect(Reaction::summarize($message->reactions, $viewer))->map(fn (array $reaction) => [$reaction['emoji'], $reaction['count']]);

    expect($broadcast->all())->toBe($pageLoad->all());
    expect($broadcast->all())->toBe([['❤️', 3], ['😂', 2], ['🎉', 1], ['👍', 1], ['🙏', 1]]);
});

test('a deleted message is broadcast without its text, a moderator placeholder with it', function () {
    $wiped = Message::factory()->create(['body' => '', 'is_deleted' => true]);
    $moderated = Message::factory()->create(['body' => 'Deleted by a moderator', 'is_deleted' => true]);

    expect((new MessageUpdated($wiped))->broadcastWith()['message'])->body->toBeNull()->is_deleted->toBeTrue();
    expect((new MessageUpdated($moderated))->broadcastWith()['message'])->body->toBe('Deleted by a moderator')->is_deleted->toBeTrue();
});

test('an incoming message is titled from the recipients side, with a short one-line preview', function () {
    $sender = User::factory()->create(['name' => 'Anne']);
    $recipient = User::factory()->create(['name' => 'Bjørn']);
    $third = User::factory()->create(['name' => 'Cecilie']);
    $group = chatBroadcastConversation([$sender, $recipient, $third]);
    $direct = chatBroadcastConversation([$sender, $recipient]);
    $long = Message::factory()->create(['conversation_id' => $group->id, 'user_id' => $sender->id, 'body' => "First line\n".str_repeat('x', 200)]);
    $short = Message::factory()->create(['conversation_id' => $direct->id, 'user_id' => $sender->id, 'body' => 'Hi']);

    $inGroup = (new IncomingMessage($recipient->id, $long))->broadcastWith();
    expect($inGroup)
        ->conversation_type->toBe('group')
        ->conversation_title->toBe('Anne, Cecilie')
        ->sender->toBe(['id' => $sender->id, 'name' => 'Anne', 'avatar_url' => null]);
    expect($inGroup['preview'])->toStartWith('First line xxx')->toEndWith('…');
    expect(mb_strlen($inGroup['preview']))->toBe(MessageSummary::PREVIEW_LENGTH + 1);

    expect((new IncomingMessage($recipient->id, $short))->broadcastWith())
        ->conversation_type->toBe('direct')
        ->conversation_title->toBe('Anne')
        ->preview->toBe('Hi');
});

test('sending a message broadcasts to the thread, the recipient and both badges without a queue', function () {
    $broadcaster = chatBroadcaster();
    config(['queue.default' => 'database']);
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = chatBroadcastConversation([$sender, $recipient]);

    $this->actingAs($sender)
        ->withHeader('X-Socket-ID', '1234.5678')
        ->postJson("/messages/{$conversation->id}/send", ['body' => 'Live!'])
        ->assertCreated();

    $sent = $broadcaster->named('message.sent');
    expect($sent)->toHaveCount(1);
    expect($sent[0]['channels'])->toBe(collect([$sender->id, $recipient->id])->sort()->map(fn (int $id) => "private-App.Models.User.{$id}")->values()->all());
    expect($sent[0]['payload']['message']['body'])->toBe('Live!');
    // The sending tab has the message from the response already.
    expect($sent[0]['payload']['socket'])->toBe('1234.5678');

    $received = $broadcaster->named('message.received');
    expect($received)->toHaveCount(1);
    expect($received[0]['channels'])->toBe(["private-App.Models.User.{$recipient->id}"]);
    expect($received[0]['payload'])->toMatchArray(['conversation_id' => $conversation->id, 'preview' => 'Live!']);

    expect(collect($broadcaster->named('badges.updated'))->pluck('channels.0')->sort()->values()->all())
        ->toBe(collect(["private-App.Models.User.{$sender->id}", "private-App.Models.User.{$recipient->id}"])->sort()->values()->all());
    expect(collect($broadcaster->named('badges.updated'))->firstWhere('channels.0', "private-App.Models.User.{$recipient->id}")['payload'])
        ->toMatchArray(['messages' => 1]);
});

test('an unreachable WebSocket server does not fail sending, deleting or reacting', function () {
    Exceptions::fake();
    chatBroadcaster(failing: true);
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = chatBroadcastConversation([$sender, $recipient]);

    $this->actingAs($sender)->postJson("/messages/{$conversation->id}/send", ['body' => 'Still saved'])->assertCreated();
    $message = Message::query()->sole();
    $this->actingAs($recipient)->postJson('/reactions', ['reactable_type' => 'message', 'reactable_id' => $message->id, 'emoji' => '👍'])->assertOk();
    $this->actingAs($sender)->deleteJson("/messages/{$conversation->id}/messages/{$message->id}")->assertNoContent();

    Exceptions::assertReported(BroadcastException::class);
    expect($message->fresh()->is_deleted)->toBeTrue();
});

test('someone who left a group no longer gets its messages, whatever they still have open', function () {
    [$a, $b, $c] = User::factory()->count(3)->create()->all();
    $conversation = chatBroadcastConversation([$a, $b, $c], 'Hikers');

    $this->actingAs($c)->delete("/messages/{$conversation->id}/participants")->assertRedirect();

    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $a->id]);

    expect(array_map('strval', (new MessageSent($message))->broadcastOn()))
        ->not->toContain("private-App.Models.User.{$c->id}")
        ->toContain("private-App.Models.User.{$a->id}", "private-App.Models.User.{$b->id}");
});

test('reading a conversation tells the others when, for their "Seen", if the reader shows that', function () {
    $broadcaster = chatBroadcaster();
    [$sender, $reader] = User::factory()->count(2)->create()->all();
    $sender->update(['share_read_receipts' => true]);
    $reader->update(['share_read_receipts' => true]);
    $conversation = chatBroadcastConversation([$sender, $reader]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $this->actingAs($reader)->postJson("/messages/{$conversation->id}/read")->assertNoContent();

    $read = $broadcaster->named('conversation.read');
    expect($read)->toHaveCount(1)
        ->and($read[0]['channels'])->toBe(["private-App.Models.User.{$sender->id}"])
        ->and($read[0]['payload']['conversation_id'])->toBe($conversation->id)
        ->and($read[0]['payload']['user_id'])->toBe($reader->id)
        ->and($read[0]['payload']['read_at'])->not->toBeNull();
});

test('a read is told only to those who show their own reads: it goes both ways', function () {
    $broadcaster = chatBroadcaster();
    [$showing, $notShowing, $reader] = User::factory()->count(3)->create()->all();
    $showing->update(['share_read_receipts' => true]);
    $reader->update(['share_read_receipts' => true]);
    $conversation = chatBroadcastConversation([$showing, $notShowing, $reader], 'Hikers');
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $notShowing->id]);

    $this->actingAs($reader)->postJson("/messages/{$conversation->id}/read")->assertNoContent();

    $read = $broadcaster->named('conversation.read');
    expect($read)->toHaveCount(1)
        ->and($read[0]['channels'])->toBe(["private-App.Models.User.{$showing->id}"]);
});

test('opening a conversation with something new in it tells the others it was read, if the reader shows that', function () {
    $broadcaster = chatBroadcaster();
    [$sender, $reader] = User::factory()->count(2)->create()->all();
    $sender->update(['share_read_receipts' => true]);
    $reader->update(['share_read_receipts' => true]);
    $conversation = chatBroadcastConversation([$sender, $reader]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $this->actingAs($reader)->get("/messages/{$conversation->id}")->assertOk();

    expect($broadcaster->named('conversation.read'))->toHaveCount(1);
});

test('a reader who has not chosen to show that they have read tells no one, by either route', function () {
    $broadcaster = chatBroadcaster();
    [$sender, $reader] = User::factory()->count(2)->create()->all();
    $conversation = chatBroadcastConversation([$sender, $reader]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $this->actingAs($reader)->get("/messages/{$conversation->id}")->assertOk();
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);
    $this->actingAs($reader)->postJson("/messages/{$conversation->id}/read")->assertNoContent();

    expect($broadcaster->named('conversation.read'))->toHaveCount(0)
        ->and($conversation->participantFor($reader)->fresh()->last_read_at)->not->toBeNull();
});
