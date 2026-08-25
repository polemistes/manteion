<?php

use App\Enums\FollowStatus;
use App\Models\Follow;
use App\Models\User;

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
