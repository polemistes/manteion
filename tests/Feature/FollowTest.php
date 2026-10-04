<?php

use App\Enums\FollowStatus;
use App\Enums\NotificationCategory;
use App\Events\BadgesUpdated;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowRequestApproved;
use App\Notifications\NewFollower;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('following someone notifies them', function () {
    Notification::fake();
    $follower = User::factory()->create();
    $target = User::factory()->pushEnabledFor(NotificationCategory::Followers)->create(['allow_public_follow' => true]);

    $this->actingAs($follower)->post('/follows', ['user_id' => $target->id])->assertRedirect();

    Notification::assertSentTo($target, NewFollower::class);
});

test('following an open user is accepted immediately', function () {
    $follower = User::factory()->create();
    $target = User::factory()->create(['allow_public_follow' => true]);

    $this->actingAs($follower)->post('/follows', ['user_id' => $target->id])->assertRedirect();

    $this->assertDatabaseHas('follows', [
        'follower_id' => $follower->id,
        'followed_id' => $target->id,
        'status' => FollowStatus::Accepted->value,
    ]);
});

test('following a private user requires approval', function () {
    $follower = User::factory()->create();
    $target = User::factory()->privateFollow()->create();

    $this->actingAs($follower)->post('/follows', ['user_id' => $target->id])->assertRedirect();

    $this->assertDatabaseHas('follows', [
        'follower_id' => $follower->id,
        'followed_id' => $target->id,
        'status' => FollowStatus::Pending->value,
    ]);
});

test('a user cannot follow themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/follows', ['user_id' => $user->id])->assertInvalid(['user_id']);
});

test('a user cannot follow someone twice', function () {
    $follower = User::factory()->create();
    $target = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $target->id]);

    $this->actingAs($follower)->post('/follows', ['user_id' => $target->id])->assertInvalid(['user_id']);
});

test('only the followed user can approve a pending follow request', function () {
    $follower = User::factory()->create();
    $target = User::factory()->privateFollow()->create();
    $follow = Follow::factory()->pending()->create(['follower_id' => $follower->id, 'followed_id' => $target->id]);

    $this->actingAs($follower)->patch("/follows/{$follow->id}")->assertForbidden();

    $this->actingAs($target)->patch("/follows/{$follow->id}")->assertRedirect();

    expect($follow->fresh()->status)->toBe(FollowStatus::Accepted);
});

test('the follower can unfollow and the followed user can remove a follower', function () {
    $follower = User::factory()->create();
    $target = User::factory()->create();
    $follow = Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $target->id]);

    $this->actingAs($follower)->delete("/follows/{$follow->id}")->assertRedirect();

    $this->assertModelMissing($follow);
});

test('a follow reaches the followed users activity in their language, as a follow', function () {
    Notification::fake();
    $follower = User::factory()->create();
    $target = User::factory()->create(['allow_public_follow' => true, 'locale' => 'nb']);

    $this->actingAs($follower)->post('/follows', ['user_id' => $target->id])->assertRedirect();

    Notification::assertSentTo($target, NewFollower::class, fn (NewFollower $notification, array $channels) => $channels === ['database', 'broadcast']
        && $notification->pending() === false
        && $notification->locale === 'nb');
});

test('asking to follow someone who approves their followers notifies them of a request', function () {
    Notification::fake();
    $follower = User::factory()->create();
    $target = User::factory()->privateFollow()->create();

    $this->actingAs($follower)->post('/follows', ['user_id' => $target->id])->assertRedirect();

    Notification::assertSentTo($target, NewFollower::class, fn (NewFollower $notification) => $notification->pending() === true);
});

test('a follow request stays a request in the notification even if approved before it is sent', function () {
    $follow = Follow::factory()->pending()->create();
    $notification = new NewFollower($follow);

    $follow->update(['status' => FollowStatus::Accepted]);
    $follow->followed->notify($notification);

    expect($follow->followed->notifications()->sole()->data['pending'])->toBeTrue();
});

test('approving a follow request tells the follower, in their language', function () {
    Notification::fake();
    $follower = User::factory()->create(['locale' => 'nb']);
    $target = User::factory()->privateFollow()->create();
    $follow = Follow::factory()->pending()->create(['follower_id' => $follower->id, 'followed_id' => $target->id]);

    $this->actingAs($target)->patch("/follows/{$follow->id}")->assertRedirect();

    Notification::assertSentTo($follower, FollowRequestApproved::class, fn (FollowRequestApproved $notification) => $notification->follow->is($follow)
        && $notification->locale === 'nb');
    Notification::assertNotSentTo($target, FollowRequestApproved::class);
});

test('rejecting a follow request is never announced', function () {
    Notification::fake();
    $follower = User::factory()->create();
    $target = User::factory()->privateFollow()->create();
    $follow = Follow::factory()->pending()->create(['follower_id' => $follower->id, 'followed_id' => $target->id]);

    $this->actingAs($target)->delete("/follows/{$follow->id}")->assertRedirect();

    $this->assertModelMissing($follow);
    Notification::assertNothingSent();
});

test('following, approving and unfollowing update both sides badges', function (string $action) {
    $follower = User::factory()->create();
    $target = User::factory()->privateFollow()->create();
    $follow = $action === 'follow' ? null : Follow::factory()->pending()->create(['follower_id' => $follower->id, 'followed_id' => $target->id]);
    Event::fake([BadgesUpdated::class]);

    $response = match ($action) {
        'follow' => $this->actingAs($follower)->post('/follows', ['user_id' => $target->id]),
        'approve' => $this->actingAs($target)->patch("/follows/{$follow->id}"),
        'reject' => $this->actingAs($target)->delete("/follows/{$follow->id}"),
        'withdraw' => $this->actingAs($follower)->delete("/follows/{$follow->id}"),
    };

    $response->assertRedirect();
    Event::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $follower->id);
    Event::assertDispatched(BadgesUpdated::class, fn (BadgesUpdated $event) => $event->userId === $target->id);
})->with(['follow', 'approve', 'reject', 'withdraw']);

test('a withdrawn or turned-down follow request takes its notification with it', function (string $by) {
    $follower = User::factory()->create();
    $target = User::factory()->privateFollow()->create();
    $follow = Follow::factory()->pending()->create(['follower_id' => $follower->id, 'followed_id' => $target->id]);
    $otherFollow = Follow::factory()->create(['followed_id' => $target->id]);
    $target->notify(new NewFollower($follow));
    $target->notify(new NewFollower($otherFollow));

    $this->actingAs($by === 'follower' ? $follower : $target)->delete("/follows/{$follow->id}")->assertRedirect();

    expect($target->notifications()->sole()->data['follow_id'])->toBe($otherFollow->id);
})->with(['follower', 'followed user']);

test('unfollowing keeps the notification about the follow', function () {
    $follower = User::factory()->create();
    $target = User::factory()->create();
    $follow = Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $target->id]);
    $target->notify(new NewFollower($follow));

    $this->actingAs($follower)->delete("/follows/{$follow->id}")->assertRedirect();

    expect($target->notifications()->count())->toBe(1);
});

test('approving a follow request marks its notification read', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create(['allow_public_follow' => false]);
    $follow = Follow::factory()->pending()->create(['follower_id' => $follower->id, 'followed_id' => $followed->id]);
    $followed->notify(new NewFollower($follow));

    $this->actingAs($followed)->patch("/follows/{$follow->id}")->assertRedirect();

    expect($followed->unreadNotifications()->count())->toBe(0);
});
