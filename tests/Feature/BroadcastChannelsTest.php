<?php

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * The test environment broadcasts through the "null" driver, which
 * authorizes nothing. Switch to Reverb with dummy credentials (nothing is
 * sent anywhere: authorizing only signs the channel name locally) and
 * register the channels on it, so /broadcasting/auth answers for real.
 */
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');
});

test('a user may listen on their own user channel only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', ['channel_name' => "private-App.Models.User.{$user->id}", 'socket_id' => '1234.5678'])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->actingAs($user)
        ->post('/broadcasting/auth', ['channel_name' => "private-App.Models.User.{$other->id}", 'socket_id' => '1234.5678'])
        ->assertForbidden();
});

test('guests may not listen on any private channel', function () {
    $user = User::factory()->create();

    $this->post('/broadcasting/auth', ['channel_name' => "private-App.Models.User.{$user->id}", 'socket_id' => '1234.5678'])
        ->assertForbidden();
});

test('only participants may listen on a conversation channel', function () {
    $participant = User::factory()->create();
    $outsider = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $conversation = Conversation::factory()->create(['creator_id' => $participant->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $participant->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => User::factory()->create()->id]);
    $channel = "private-conversations.{$conversation->id}";

    $this->actingAs($participant)
        ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->actingAs($outsider)
        ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
        ->assertForbidden();

    // Staff may open a reported conversation, but not follow it live.
    $this->actingAs($moderator)
        ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
        ->assertForbidden();
});

test('a conversation channel for a conversation that does not exist is refused', function () {
    $this->actingAs(User::factory()->create())
        ->post('/broadcasting/auth', ['channel_name' => 'private-conversations.999999', 'socket_id' => '1234.5678'])
        ->assertForbidden();
});

test('an event channel is open to whoever may view the event', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $privateEvent = Event::factory()->create(['user_id' => $owner->id]);
    $searchableEvent = Event::factory()->searchable()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post('/broadcasting/auth', ['channel_name' => "private-events.{$privateEvent->id}", 'socket_id' => '1234.5678'])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->actingAs($outsider)
        ->post('/broadcasting/auth', ['channel_name' => "private-events.{$privateEvent->id}", 'socket_id' => '1234.5678'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post('/broadcasting/auth', ['channel_name' => "private-events.{$searchableEvent->id}", 'socket_id' => '1234.5678'])
        ->assertOk();

    $this->actingAs($moderator)
        ->post('/broadcasting/auth', ['channel_name' => "private-events.{$privateEvent->id}", 'socket_id' => '1234.5678'])
        ->assertOk();
});
