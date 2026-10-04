<?php

use App\Enums\FriendshipStatus;
use App\Enums\NotificationCategory;
use App\Events\BadgesUpdated;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('a user can send a friend request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $response = $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id]);

    $response->assertRedirect();
    $this->assertDatabaseHas('friendships', [
        'requester_id' => $sender->id,
        'addressee_id' => $recipient->id,
        'status' => FriendshipStatus::Pending->value,
    ]);
});

test('sending a friend request notifies the recipient', function () {
    Notification::fake();
    $sender = User::factory()->create();
    $recipient = User::factory()->pushEnabledFor(NotificationCategory::FriendRequests)->create();

    $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id])->assertRedirect();

    Notification::assertSentTo($recipient, FriendRequestReceived::class);
});

test('a user cannot send a friend request to themselves', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/friendships', ['user_id' => $user->id]);

    $response->assertInvalid(['user_id']);
});

test('a user cannot send a duplicate friend request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);

    $response = $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id]);

    $response->assertInvalid(['user_id']);
});

test('only the addressee can accept a friend request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);

    $this->actingAs($sender)->patch("/friendships/{$friendship->id}", ['action' => 'accept'])->assertForbidden();

    $this->actingAs($recipient)->patch("/friendships/{$friendship->id}", ['action' => 'accept'])->assertRedirect();

    expect($friendship->fresh()->status)->toBe(FriendshipStatus::Accepted);
});

test('the addressee can decline a friend request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);

    $this->actingAs($recipient)->patch("/friendships/{$friendship->id}", ['action' => 'decline'])->assertRedirect();

    expect($friendship->fresh()->status)->toBe(FriendshipStatus::Declined);
});

test('either party can remove an accepted friendship', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $friendship = Friendship::factory()->accepted()->create(['requester_id' => $userA->id, 'addressee_id' => $userB->id]);

    $this->actingAs($userB)->delete("/friendships/{$friendship->id}")->assertRedirect();

    $this->assertModelMissing($friendship);
});

test('an unrelated user cannot remove a friendship', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $outsider = User::factory()->create();
    $friendship = Friendship::factory()->accepted()->create(['requester_id' => $userA->id, 'addressee_id' => $userB->id]);

    $this->actingAs($outsider)->delete("/friendships/{$friendship->id}")->assertForbidden();
});

test('accepted friendships appear in both users friend lists', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $userA->id, 'addressee_id' => $userB->id]);

    expect($userA->fresh()->isFriendsWith($userB))->toBeTrue();
    expect($userA->fresh()->friends()->pluck('id'))->toContain($userB->id);
});

test('a declined request still looks sent to the requester and is hidden from the addressee', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id, 'status' => FriendshipStatus::Declined]);

    $this->actingAs($sender)->get('/contacts')->assertInertia(fn ($page) => $page
        ->where('sentFriendRequests.0.friendship_id', $friendship->id)
        ->has('pendingFriendRequests', 0));

    $this->actingAs($sender)->get("/users/{$recipient->id}")->assertInertia(fn ($page) => $page
        ->where('friend_status', 'request_sent')
        ->where('friendship_id', $friendship->id));

    $this->actingAs($recipient)->get('/contacts')->assertInertia(fn ($page) => $page
        ->has('pendingFriendRequests', 0)
        ->has('sentFriendRequests', 0));

    $this->actingAs($recipient)->get("/users/{$sender->id}")->assertInertia(fn ($page) => $page
        ->where('friend_status', 'none')
        ->where('friendship_id', null));
});

test('a declined request blocks a repeat request in the same direction', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id, 'status' => FriendshipStatus::Declined]);

    $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id])->assertInvalid(['user_id']);
});

test('the person who declined can still send their own request, replacing the declined one', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $declined = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id, 'status' => FriendshipStatus::Declined]);

    $this->actingAs($recipient)->post('/friendships', ['user_id' => $sender->id])->assertRedirect()->assertValid();

    $this->assertModelMissing($declined);
    $this->assertDatabaseHas('friendships', [
        'requester_id' => $recipient->id,
        'addressee_id' => $sender->id,
        'status' => FriendshipStatus::Pending->value,
    ]);
});

test('the requester can cancel a declined request', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id, 'status' => FriendshipStatus::Declined]);

    $this->actingAs($recipient)->delete("/friendships/{$friendship->id}")->assertForbidden();
    $this->actingAs($sender)->delete("/friendships/{$friendship->id}")->assertRedirect();

    $this->assertModelMissing($friendship);
});

test('a friend request reaches the recipients activity in their language, push or not', function () {
    Notification::fake();
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['locale' => 'nb']);

    $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id])->assertRedirect();

    Notification::assertSentTo($recipient, FriendRequestReceived::class, fn (FriendRequestReceived $notification, array $channels) => $channels === ['database', 'broadcast']
        && $notification->locale === 'nb'
        && $notification->friendship->requester->is($sender));
});

test('accepting a friend request tells the requester, in their language', function () {
    Notification::fake();
    $sender = User::factory()->create(['locale' => 'nb']);
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);

    $this->actingAs($recipient)->patch("/friendships/{$friendship->id}", ['action' => 'accept'])->assertRedirect();

    Notification::assertSentTo($sender, FriendRequestAccepted::class, fn (FriendRequestAccepted $notification) => $notification->friendship->is($friendship)
        && $notification->locale === 'nb');
    Notification::assertNotSentTo($recipient, FriendRequestAccepted::class);
});

test('declining a friend request is never announced', function () {
    Notification::fake();
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);

    $this->actingAs($recipient)->patch("/friendships/{$friendship->id}", ['action' => 'decline'])->assertRedirect();

    Notification::assertNothingSent();
});

test('sending, answering and withdrawing a friend request update both sides badges', function (string $action) {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = $action === 'send' ? null : Friendship::factory()->create([
        'requester_id' => $sender->id,
        'addressee_id' => $recipient->id,
        'status' => $action === 'remove' ? FriendshipStatus::Accepted : FriendshipStatus::Pending,
    ]);
    Event::fake([BadgesUpdated::class]);

    $response = match ($action) {
        'send' => $this->actingAs($sender)->post('/friendships', ['user_id' => $recipient->id]),
        'accept', 'decline' => $this->actingAs($recipient)->patch("/friendships/{$friendship->id}", ['action' => $action]),
        'cancel' => $this->actingAs($sender)->delete("/friendships/{$friendship->id}"),
        'remove' => $this->actingAs($recipient)->delete("/friendships/{$friendship->id}"),
    };

    $response->assertRedirect();

    Event::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $sender->id);
    Event::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $recipient->id);
})->with(['send', 'accept', 'decline', 'cancel', 'remove']);

test('a refused friend request change updates no badges', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);
    Event::fake([BadgesUpdated::class]);

    $this->actingAs($sender)->patch("/friendships/{$friendship->id}", ['action' => 'accept'])->assertForbidden();
    $this->actingAs(User::factory()->create())->delete("/friendships/{$friendship->id}")->assertForbidden();

    Event::assertNotDispatched(BadgesUpdated::class);
});

test('cancelling a friend request withdraws the recipients notification about it', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);
    $otherRequest = Friendship::factory()->create(['addressee_id' => $recipient->id]);
    $recipient->notify(new FriendRequestReceived($friendship));
    $recipient->notify(new FriendRequestReceived($otherRequest));

    $this->actingAs($sender)->delete("/friendships/{$friendship->id}")->assertRedirect();

    expect($recipient->notifications()->sole()->data['friendship_id'])->toBe($otherRequest->id);
});

test('removing a friend keeps the notifications of how the friendship began', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::factory()->accepted()->create(['requester_id' => $sender->id, 'addressee_id' => $recipient->id]);
    $recipient->notify(new FriendRequestReceived($friendship));
    $sender->notify(new FriendRequestAccepted($friendship));

    $this->actingAs($recipient)->delete("/friendships/{$friendship->id}")->assertRedirect();

    expect($recipient->notifications()->count())->toBe(1);
    expect($sender->notifications()->count())->toBe(1);
});
