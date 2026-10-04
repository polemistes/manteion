<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('reacting to a leaflet adds the emoji and reacting again removes it', function () {
    $user = User::factory()->create();
    $leaflet = Leaflet::factory()->official()->create();

    $this->actingAs($user)->post('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => '🎉'])->assertRedirect();
    expect(Reaction::query()->count())->toBe(1);

    $this->actingAs($user)->get('/leaflets')
        ->assertInertia(fn ($page) => $page
            ->where('leaflets.0.reactions.0.emoji', '🎉')
            ->where('leaflets.0.reactions.0.count', 1)
            ->where('leaflets.0.reactions.0.reacted', true));

    $this->actingAs($user)->post('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => '🎉'])->assertRedirect();
    expect(Reaction::query()->count())->toBe(0);
});

test('a user has one reaction per item: another emoji replaces it', function () {
    $user = User::factory()->create();
    $leaflet = Leaflet::factory()->official()->create();

    $this->actingAs($user)->post('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => '👍'])->assertRedirect();
    $this->actingAs($user)->post('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => '❤️'])->assertRedirect();

    expect(Reaction::query()->count())->toBe(1);
    expect(Reaction::query()->first()->emoji)->toBe('❤️');

    $this->actingAs($user)->get('/leaflets')
        ->assertInertia(fn ($page) => $page
            ->has('leaflets.0.reactions', 1)
            ->where('leaflets.0.reactions.0.emoji', '❤️')
            ->where('leaflets.0.reactions.0.reacted', true));
});

test('reactions are summarised per emoji, most used first, with the viewers own marked', function () {
    $viewer = User::factory()->create();
    $others = User::factory()->count(2)->create();
    $leaflet = Leaflet::factory()->official()->create();

    Reaction::factory()->create(['user_id' => $viewer->id, 'reactable_id' => $leaflet->id, 'emoji' => '👍']);
    foreach ($others as $other) {
        Reaction::factory()->create(['user_id' => $other->id, 'reactable_id' => $leaflet->id, 'emoji' => '❤️']);
    }

    $this->actingAs($viewer)->get('/leaflets')
        ->assertInertia(fn ($page) => $page
            ->where('leaflets.0.reactions.0', ['emoji' => '❤️', 'count' => 2, 'reacted' => false])
            ->where('leaflets.0.reactions.1', ['emoji' => '👍', 'count' => 1, 'reacted' => true]));
});

test('any single unicode emoji is accepted, including flags and sequences', function (string $emoji) {
    $user = User::factory()->create();
    $leaflet = Leaflet::factory()->official()->create();

    $this->actingAs($user)->post('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => $emoji])->assertValid();
    expect(Reaction::query()->value('emoji'))->toBe($emoji);
})->with(['🇳🇴', '👩‍💻', '👍🏽', '1️⃣', '❤️', '🎉', '🙋‍♀️', '👩‍❤️‍💋‍👨', '🏴󠁧󠁢󠁳󠁣󠁴󠁿', '©️']);

test('text, several emoji, or nothing is not a reaction', function (string $invalid) {
    $user = User::factory()->create();
    $leaflet = Leaflet::factory()->official()->create();

    $this->actingAs($user)->post('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => $invalid])->assertInvalid(['emoji']);
    expect(Reaction::query()->count())->toBe(0);
})->with(['ok', 'a', '🎉🎉', '', '👍👍🏽', '🇳🇴🇸🇪', 'x🎉']);

test('you can react to a response on a leaflet you can read, and not otherwise', function () {
    $author = User::factory()->create();
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $friend->id, 'addressee_id' => $author->id]);
    $leaflet = Leaflet::factory()->friends()->create(['user_id' => $author->id]);
    $response = LeafletResponse::factory()->create(['leaflet_id' => $leaflet->id, 'user_id' => $author->id]);

    $this->actingAs($friend)->post('/reactions', ['reactable_type' => 'leaflet_response', 'reactable_id' => $response->id, 'emoji' => '😂'])->assertRedirect();
    $this->actingAs($stranger)->post('/reactions', ['reactable_type' => 'leaflet_response', 'reactable_id' => $response->id, 'emoji' => '😂'])->assertForbidden();

    $this->actingAs($friend)->get('/leaflets')
        ->assertInertia(fn ($page) => $page->where('leaflets.0.responses.0.reactions.0.emoji', '😂'));
});

test('participants can react to messages, staff who are not participants cannot', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    $this->actingAs($user)->post('/reactions', ['reactable_type' => 'message', 'reactable_id' => $message->id, 'emoji' => '👍'])->assertRedirect();
    $this->actingAs($moderator)->post('/reactions', ['reactable_type' => 'message', 'reactable_id' => $message->id, 'emoji' => '👍'])->assertForbidden();

    $this->actingAs($other)->get("/messages/{$conversation->id}")
        ->assertInertia(fn ($page) => $page
            ->where('messages.0.reactions.0.emoji', '👍')
            ->where('messages.0.reactions.0.count', 1)
            ->where('messages.0.reactions.0.reacted', false));
});

test('reactions disappear with what they were attached to', function () {
    $user = User::factory()->create();
    $leaflet = Leaflet::factory()->create(['user_id' => $user->id]);
    Reaction::factory()->create(['user_id' => $user->id, 'reactable_id' => $leaflet->id]);

    $this->actingAs($user)->delete("/leaflets/{$leaflet->id}")->assertRedirect();

    expect(Reaction::query()->count())->toBe(0);
});

test('a reaction sent as JSON gets the items reactions back as they now are', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $leaflet = Leaflet::factory()->official()->create();
    Reaction::factory()->create(['user_id' => $other->id, 'reactable_id' => $leaflet->id, 'emoji' => '❤️']);

    $this->actingAs($user)->postJson('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => '🎉'])
        ->assertOk()
        ->assertExactJson(['reactions' => [
            ['emoji' => '❤️', 'count' => 1, 'reacted' => false],
            ['emoji' => '🎉', 'count' => 1, 'reacted' => true],
        ]]);

    $this->actingAs($user)->postJson('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => '🎉'])
        ->assertOk()
        ->assertExactJson(['reactions' => [['emoji' => '❤️', 'count' => 1, 'reacted' => false]]]);
});

test('reacting to a message updates it live for the others in the conversation, reacting to a leaflet does not', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    $leaflet = Leaflet::factory()->official()->create();
    Event::fake([MessageUpdated::class]);

    $this->actingAs($user)->postJson('/reactions', ['reactable_type' => 'message', 'reactable_id' => $message->id, 'emoji' => '👍'])
        ->assertOk()
        ->assertJsonPath('reactions.0', ['emoji' => '👍', 'count' => 1, 'reacted' => true]);

    Event::assertDispatched(MessageUpdated::class, fn (MessageUpdated $event) => $event->message->is($message)
        && $event->broadcastWith()['message']['reactions'] === [['emoji' => '👍', 'count' => 1, 'user_ids' => [$user->id]]]);

    $this->actingAs($user)->post('/reactions', ['reactable_type' => 'leaflet', 'reactable_id' => $leaflet->id, 'emoji' => '👍'])->assertRedirect();
    Event::assertDispatchedTimes(MessageUpdated::class, 1);
});
