<?php

use App\Enums\ConversationType;
use App\Enums\NotificationCategory;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * A conversation between the given users, in that order; more than two
 * make a group.
 *
 * @param  list<User>  $participants
 */
function chatPushConversation(array $participants, ?string $name = null): Conversation
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

/**
 * The push as the service worker receives it: the JSON payload and the
 * delivery options.
 *
 * @return array{payload: array<string, mixed>, options: array<string, mixed>}
 */
function chatPushFor(Message $message, User $recipient): array
{
    $notification = new NewMessageReceived($message);
    $push = $notification->toWebPush($recipient, $notification);

    return ['payload' => json_decode(json_encode($push->toArray()), true), 'options' => $push->getOptions()];
}

test('a direct message push shows the sender and the text', function () {
    $sender = User::factory()->create(['name' => 'Kari', 'avatar_path' => 'avatars/kari.jpg']);
    $recipient = User::factory()->create();
    $conversation = chatPushConversation([$sender, $recipient]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'body' => 'See you at eight?']);

    $push = chatPushFor($message, $recipient);

    expect($push['payload'])->toBe([
        'title' => 'Kari',
        'badge' => '/icons/badge-96.png',
        'body' => 'See you at eight?',
        'icon' => url($sender->avatar_url),
        'lang' => 'en',
        'renotify' => true,
        'tag' => "conversation-{$conversation->id}",
        'data' => [
            'url' => "/messages/{$conversation->id}",
            'type' => 'message',
            'category' => 'direct_messages',
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'conversation_type' => 'direct',
            'conversation_title' => null,
            'sender_name' => 'Kari',
            'sender_avatar' => url($sender->avatar_url),
            'text' => 'See you at eight?',
            'badge_count' => 1,
        ],
        'timestamp' => $message->created_at->getTimestampMs(),
    ]);
    expect($push['payload']['icon'])->toStartWith('http');
    expect($push['options'])->toBe(['TTL' => 86400, 'urgency' => 'high', 'topic' => "c{$conversation->id}"]);
});

test('a sender without a picture is shown with the app icon', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $message = Message::factory()->create(['conversation_id' => chatPushConversation([$sender, $recipient])->id, 'user_id' => $sender->id]);

    expect(chatPushFor($message, $recipient)['payload']['icon'])->toBe('/icons/icon-192.png');
});

test('a group message push shows the group as title and who wrote it in the body', function () {
    $sender = User::factory()->create(['name' => 'Kari']);
    $recipient = User::factory()->create(['name' => 'Ola']);
    $third = User::factory()->create(['name' => 'Per']);
    $named = chatPushConversation([$sender, $recipient, $third], 'Hyttetur');
    $unnamed = chatPushConversation([$sender, $recipient, $third]);

    $inNamed = chatPushFor(Message::factory()->create(['conversation_id' => $named->id, 'user_id' => $sender->id, 'body' => 'Who brings coffee?']), $recipient);
    expect($inNamed['payload'])->title->toBe('Hyttetur')->body->toBe('Kari: Who brings coffee?');

    $inUnnamed = chatPushFor(Message::factory()->create(['conversation_id' => $unnamed->id, 'user_id' => $sender->id, 'body' => 'Hello']), $recipient);
    expect($inUnnamed['payload'])->title->toBe('Kari, Per')->body->toBe('Kari: Hello');

    // The parts the Android app builds its conversation notification from.
    expect($inNamed['payload']['data'])
        ->category->toBe('group_messages')
        ->conversation_type->toBe('group')
        ->conversation_title->toBe('Hyttetur')
        ->sender_name->toBe('Kari')
        ->sender_avatar->toBeNull()
        ->text->toBe('Who brings coffee?');
});

test('without previews a push only says there is a new message', function () {
    $sender = User::factory()->create(['name' => 'Kari']);
    $recipient = User::factory()->create(['notification_settings' => ['message_previews' => false]]);
    $direct = chatPushConversation([$sender, $recipient]);
    $group = chatPushConversation([$sender, $recipient, User::factory()->create()], 'Hyttetur');

    $inDirect = chatPushFor(Message::factory()->create(['conversation_id' => $direct->id, 'user_id' => $sender->id, 'body' => 'Secret']), $recipient);
    expect($inDirect['payload'])->title->toBe('Kari')->body->toBe('New message');

    $inGroup = chatPushFor(Message::factory()->create(['conversation_id' => $group->id, 'user_id' => $sender->id, 'body' => 'Secret']), $recipient);
    expect($inGroup['payload'])->title->toBe('Hyttetur')->body->toBe('New message');

    expect($inDirect['payload']['data']['text'])->toBeNull();
    expect($inGroup['payload']['data']['text'])->toBeNull();
    expect(json_encode([$inDirect, $inGroup]))->not->toContain('Secret');
});

test('a long message is cut short in the push', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $message = Message::factory()->create([
        'conversation_id' => chatPushConversation([$sender, $recipient])->id,
        'user_id' => $sender->id,
        'body' => "Line one\nline two ".str_repeat('word ', 60),
    ]);

    $body = chatPushFor($message, $recipient)['payload']['body'];

    expect($body)->toStartWith('Line one line two word')->toEndWith('…');
    expect(mb_strlen($body))->toBeLessThanOrEqual(NewMessageReceived::PREVIEW_LENGTH + 1);
});

test('the push carries the recipients total badge count for the app icon', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = chatPushConversation([$sender, $recipient]);
    Message::factory()->create(['conversation_id' => chatPushConversation([$sender, $recipient])->id, 'user_id' => $sender->id]);
    Friendship::factory()->create(['requester_id' => User::factory()->create()->id, 'addressee_id' => $recipient->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    expect(chatPushFor($message, $recipient)['payload']['data']['badge_count'])->toBe(3);
});

test('the push goes out by default, but not to someone who muted the conversation, has it open, or turned the kind off', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = chatPushConversation([$sender, $recipient]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);
    $notification = new NewMessageReceived($message);

    expect($notification->shouldSend($recipient, WebPushChannel::class))->toBeTrue();

    $conversation->participantFor($recipient)->update(['muted_at' => now()]);
    expect($notification->shouldSend($recipient, WebPushChannel::class))->toBeFalse();
    $conversation->participantFor($recipient)->update(['muted_at' => null]);

    $conversation->setViewing($recipient, true);
    expect($notification->shouldSend($recipient, WebPushChannel::class))->toBeFalse();
    $conversation->setViewing($recipient, false);
    expect($notification->shouldSend($recipient, WebPushChannel::class))->toBeTrue();

    $recipient->update(['notification_settings' => ['push' => [NotificationCategory::DirectMessages->value => false]]]);
    expect($notification->shouldSend($recipient->fresh(), WebPushChannel::class))->toBeFalse();
});

test('the push is dropped once the message is deleted or the recipient left', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = chatPushConversation([$sender, $recipient, User::factory()->create()]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $conversation->participantFor($recipient)->delete();
    expect((new NewMessageReceived($message))->shouldSend($recipient, WebPushChannel::class))->toBeFalse();

    $stillThere = $conversation->participants()->where('user_id', '!=', $sender->id)->sole()->user;
    $message->update(['body' => '', 'is_deleted' => true]);
    expect((new NewMessageReceived($message))->shouldSend($stillThere, WebPushChannel::class))->toBeFalse();
});

test('someone reading along in the open thread gets no push for a new message', function () {
    Notification::fake();
    $sender = User::factory()->create();
    $reading = User::factory()->create();
    $away = User::factory()->create();
    $conversation = chatPushConversation([$sender, $reading, $away]);

    $this->actingAs($reading)->postJson("/messages/{$conversation->id}/presence", ['viewing' => true])->assertNoContent();
    $this->actingAs($sender)->postJson("/messages/{$conversation->id}/send", ['body' => 'Hi both'])->assertCreated();

    Notification::assertNotSentTo($reading, NewMessageReceived::class);
    Notification::assertSentTo($away, NewMessageReceived::class);
});

test('the push reaches the device through the queue in the recipients language', function () {
    $pushes = new class
    {
        /** @var list<array{locale: string, payload: array<string, mixed>}> */
        public array $sent = [];

        public function send(User $notifiable, NewMessageReceived $notification): void
        {
            $this->sent[] = [
                'locale' => app()->getLocale(),
                'payload' => $notification->toWebPush($notifiable, $notification)->toArray(),
            ];
        }
    };
    $this->app->instance(WebPushChannel::class, $pushes);
    $sender = User::factory()->create(['name' => 'Kari']);
    $recipient = User::factory()->create(['locale' => 'nb', 'notification_settings' => ['message_previews' => false]]);
    $conversation = chatPushConversation([$sender, $recipient, User::factory()->create()], 'Hyttetur');

    $this->actingAs($sender)->postJson("/messages/{$conversation->id}/send", ['body' => 'Hemmelig'])->assertCreated();

    $toRecipient = collect($pushes->sent)->firstWhere('locale', 'nb');
    expect($toRecipient['payload'])->title->toBe('Hyttetur')->body->toBe('Ny melding')->lang->toBe('nb');
    expect(app()->getLocale())->toBe('en');
});
