<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  list<User>  $people
 */
function seenMarkConversation(array $people): Conversation
{
    $conversation = Conversation::factory()->create([
        'type' => count($people) > 2 ? ConversationType::Group : ConversationType::Direct,
        'creator_id' => $people[0]->id,
    ]);

    foreach ($people as $person) {
        ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $person->id]);
    }

    return $conversation;
}

test('a reader can mark someone elses message as seen, whatever the privacy switches say', function () {
    Event::fake([MessageUpdated::class]);
    [$sender, $reader] = User::factory()->count(2)->create()->all();
    expect($sender->share_read_receipts)->toBeFalse()->and($reader->share_read_receipts)->toBeFalse();
    $conversation = seenMarkConversation([$sender, $reader]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $this->actingAs($reader)
        ->postJson("/messages/{$conversation->id}/messages/{$message->id}/seen")
        ->assertOk()
        ->assertJsonPath('message.id', $message->id)
        ->assertJsonPath('message.seen_by', [['id' => $reader->id, 'name' => $reader->name, 'avatar_url' => $reader->avatar_url]]);

    Event::assertDispatched(MessageUpdated::class, fn (MessageUpdated $event) => $event->message->is($message));
    expect($message->seenBy()->pluck('users.id')->all())->toBe([$reader->id]);
});

test('marking a message as seen twice changes nothing', function () {
    [$sender, $reader] = User::factory()->count(2)->create()->all();
    $conversation = seenMarkConversation([$sender, $reader]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $this->actingAs($reader)->postJson("/messages/{$conversation->id}/messages/{$message->id}/seen")->assertOk();
    $this->actingAs($reader)->postJson("/messages/{$conversation->id}/messages/{$message->id}/seen")->assertOk()
        ->assertJsonCount(1, 'message.seen_by');

    expect($message->seenBy()->count())->toBe(1);
});

test('the marks travel with the message: on the page, in the broadcast, oldest first', function () {
    [$sender, $first, $second] = User::factory()->count(3)->create()->all();
    $conversation = seenMarkConversation([$sender, $first, $second]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $this->travelTo(now());
    $this->actingAs($first)->postJson("/messages/{$conversation->id}/messages/{$message->id}/seen")->assertOk();
    $this->travelTo(now()->addMinute());
    $this->actingAs($second)->postJson("/messages/{$conversation->id}/messages/{$message->id}/seen")->assertOk();

    $this->actingAs($sender)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.id', $message->id)
            ->where('messages.0.seen_by.0.id', $first->id)
            ->where('messages.0.seen_by.1.id', $second->id));

    $broadcast = (new MessageUpdated($message->fresh()))->broadcastWith()['message'];
    expect(array_column($broadcast['seen_by'], 'id'))->toBe([$first->id, $second->id]);
});

test('your own message, a deleted message and a message in another conversation cannot be marked', function () {
    [$sender, $reader, $stranger] = User::factory()->count(3)->create()->all();
    $conversation = seenMarkConversation([$sender, $reader]);
    $elsewhere = seenMarkConversation([$sender, $stranger]);
    $own = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $reader->id]);
    $deleted = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'is_deleted' => true]);
    $other = Message::factory()->create(['conversation_id' => $elsewhere->id, 'user_id' => $sender->id]);

    $this->actingAs($reader)->postJson("/messages/{$conversation->id}/messages/{$own->id}/seen")->assertForbidden();
    $this->actingAs($reader)->postJson("/messages/{$conversation->id}/messages/{$deleted->id}/seen")->assertForbidden();
    $this->actingAs($reader)->postJson("/messages/{$conversation->id}/messages/{$other->id}/seen")->assertNotFound();

    expect(Message::query()->whereHas('seenBy')->exists())->toBeFalse();
});

test('only participants can mark a message as seen; staff reading along cannot', function () {
    [$sender, $reader] = User::factory()->count(2)->create()->all();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $conversation = seenMarkConversation([$sender, $reader]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);

    $this->actingAs(User::factory()->create())->postJson("/messages/{$conversation->id}/messages/{$message->id}/seen")->assertForbidden();
    $this->actingAs($moderator)->postJson("/messages/{$conversation->id}/messages/{$message->id}/seen")->assertForbidden();

    expect($message->seenBy()->count())->toBe(0);
});

test('a message sent just now carries an empty list of marks', function () {
    [$sender, $reader] = User::factory()->count(2)->create()->all();
    $conversation = seenMarkConversation([$sender, $reader]);

    $this->actingAs($sender)
        ->postJson("/messages/{$conversation->id}/send", ['body' => 'Hello'])
        ->assertCreated()
        ->assertJsonPath('message.seen_by', []);
});
