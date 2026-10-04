<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\BadgesUpdated;
use App\Events\IncomingMessage;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('a participant can send a message', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $response = $this->actingAs($user)->post("/messages/{$conversation->id}/send", ['body' => 'Hey there!']);

    $response->assertRedirect();
    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'body' => 'Hey there!',
    ]);
});

test('a non-participant cannot view or send messages in a conversation', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($outsider)->get("/messages/{$conversation->id}")->assertForbidden();
    $this->actingAs($outsider)->post("/messages/{$conversation->id}/send", ['body' => 'Sneaky'])->assertForbidden();

    $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'body' => 'Sneaky']);
});

test('sending a message notifies the other participants but not the sender', function () {
    Notification::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->post("/messages/{$conversation->id}/send", ['body' => 'Hey there!'])->assertRedirect();

    Notification::assertSentTo($other, NewMessageReceived::class);
    Notification::assertNotSentTo($user, NewMessageReceived::class);
});

test('sending a message marks the conversation as read for the sender', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    $participant = ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->post("/messages/{$conversation->id}/send", ['body' => 'Hello'])->assertRedirect();

    expect($participant->fresh()->last_read_at)->not->toBeNull();
});

test('staff cannot send messages into a conversation they are not part of', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $a->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $a->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $b->id]);

    $this->actingAs($moderator)->get("/messages/{$conversation->id}")->assertSuccessful();
    $this->actingAs($moderator)->post("/messages/{$conversation->id}/send", ['body' => 'Hello from staff'])->assertForbidden();

    $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'body' => 'Hello from staff']);
});

test('a conversation shows the newest fifty messages and older ones can be fetched page by page', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    foreach (range(1, 60) as $number) {
        Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => "Message {$number}"]);
    }

    $this->actingAs($user)->get("/messages/{$conversation->id}")
        ->assertInertia(fn ($page) => $page
            ->has('messages', 50)
            ->where('messages.0.body', 'Message 11')
            ->where('messages.49.body', 'Message 60')
            ->where('hasOlder', true));

    $eleventh = Message::query()->where('body', 'Message 11')->firstOrFail();

    $this->actingAs($user)->getJson("/messages/{$conversation->id}/messages?before={$eleventh->id}")
        ->assertOk()
        ->assertJsonCount(10, 'messages')
        ->assertJsonPath('messages.0.body', 'Message 1')
        ->assertJsonPath('messages.9.body', 'Message 10')
        ->assertJsonPath('has_older', false);
});

test('older messages cannot be fetched by a non-participant', function () {
    $user = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs($outsider)->getJson("/messages/{$conversation->id}/messages?before=".($message->id + 1))->assertForbidden();
});

test('a message sent as JSON comes back as the thread shows it', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $response = $this->actingAs($user)->postJson("/messages/{$conversation->id}/send", ['body' => 'Hey there!']);

    $message = Message::query()->sole();
    $response->assertCreated()->assertExactJson(['message' => [
        'id' => $message->id,
        'body' => 'Hey there!',
        'is_deleted' => false,
        'created_at' => $message->created_at->toIso8601String(),
        'sender' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'avatar_url' => null],
        'is_mine' => true,
        'can_delete' => true,
        'can_report' => false,
        'reactions' => [],
    ]]);
});

test('an empty message sent as JSON is refused with the errors as JSON', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs($user)->postJson("/messages/{$conversation->id}/send", ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    expect(Message::query()->count())->toBe(0);
});

test('a message sent from the page goes back to it', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->from("/messages/{$conversation->id}")
        ->post("/messages/{$conversation->id}/send", ['body' => 'Hello'])
        ->assertRedirect("/messages/{$conversation->id}");
});

test('sending tells the thread, alerts each recipient who has not muted it, and updates everyones badges', function () {
    $sender = User::factory()->create();
    $listening = User::factory()->create();
    $muting = User::factory()->create();
    $conversation = Conversation::factory()->group()->create(['name' => 'Hyttetur', 'creator_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $listening->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $muting->id, 'muted_at' => now()]);
    Event::fake([MessageSent::class, IncomingMessage::class, BadgesUpdated::class]);

    $this->actingAs($sender)->postJson("/messages/{$conversation->id}/send", ['body' => 'Who brings the coffee?'])->assertCreated();

    $message = Message::query()->sole();
    Event::assertDispatchedTimes(MessageSent::class, 1);
    Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->is($message)
        && $event->broadcastWith()['message']['body'] === 'Who brings the coffee?'
        && $event->broadcastWith()['message']['conversation_id'] === $conversation->id);

    Event::assertDispatchedTimes(IncomingMessage::class, 1);
    Event::assertDispatched(IncomingMessage::class, fn (IncomingMessage $event) => $event->recipientId === $listening->id
        && $event->broadcastWith() === [
            'conversation_id' => $conversation->id,
            'conversation_type' => 'group',
            'conversation_title' => 'Hyttetur',
            'message_id' => $message->id,
            'sender' => ['id' => $sender->id, 'name' => $sender->name, 'avatar_url' => null],
            'preview' => 'Who brings the coffee?',
        ]);

    $badgeUserIds = collect(Event::dispatched(BadgesUpdated::class))->map(fn (array $args) => $args[0]->userId)->sort()->values()->all();
    expect($badgeUserIds)->toBe(collect([$sender->id, $listening->id])->sort()->values()->all());
});

test('sending pushes to every other participant, in their own language', function () {
    Notification::fake();
    $sender = User::factory()->create();
    $english = User::factory()->create(['locale' => 'en']);
    $norwegian = User::factory()->create(['locale' => 'nb']);
    $conversation = Conversation::factory()->group()->create(['creator_id' => $sender->id]);
    foreach ([$sender, $english, $norwegian] as $participant) {
        ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $participant->id]);
    }

    $this->actingAs($sender)->postJson("/messages/{$conversation->id}/send", ['body' => 'Hei!'])->assertCreated();

    Notification::assertSentTo($english, NewMessageReceived::class, fn ($notification, $channels, $notifiable, $locale) => $locale === 'en');
    Notification::assertSentTo($norwegian, NewMessageReceived::class, fn ($notification, $channels, $notifiable, $locale) => $locale === 'nb');
    Notification::assertNotSentTo($sender, NewMessageReceived::class);
});

test('a thread that lost its connection catches up on everything newer, oldest first', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    foreach (range(1, 5) as $number) {
        Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => "Message {$number}"]);
    }
    $lastSeen = Message::query()->where('body', 'Message 2')->firstOrFail();

    $this->actingAs($user)->getJson("/messages/{$conversation->id}/messages?after={$lastSeen->id}")
        ->assertOk()
        ->assertJsonCount(3, 'messages')
        ->assertJsonPath('messages.0.body', 'Message 3')
        ->assertJsonPath('messages.2.body', 'Message 5')
        ->assertJsonPath('messages.0.is_mine', false)
        ->assertJsonMissingPath('has_older');
});

test('catching up returns at most two hundred messages, continuing from the last one seen', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    $first = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    Message::factory()->count(205)->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/messages/{$conversation->id}/messages?after={$first->id}")
        ->assertOk()
        ->assertJsonCount(200, 'messages');

    expect($response->json('messages.0.id'))->toBe($first->id + 1);
    expect($response->json('messages.199.id'))->toBe($first->id + 200);
});

test('a page of messages is asked for either before or after a message', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs($user)->getJson("/messages/{$conversation->id}/messages")->assertJsonValidationErrors(['before', 'after']);
    $this->actingAs($user)->getJson("/messages/{$conversation->id}/messages?before=5&after=1")->assertJsonValidationErrors('before');
    $this->actingAs($user)->getJson("/messages/{$conversation->id}/messages?after=abc")->assertJsonValidationErrors('after');
});

test('newer messages cannot be fetched by a non-participant', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs(User::factory()->create())->getJson("/messages/{$conversation->id}/messages?after=0")->assertForbidden();
});
