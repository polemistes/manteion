<?php

use App\Enums\ContactGroupType;
use App\Models\ContactGroup;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;

test('a user can create a friend group and add a friend to it', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);

    $this->actingAs($user)->post('/contact-groups', ['name' => 'Close friends', 'type' => 'friend'])->assertRedirect();

    $group = ContactGroup::query()->where('user_id', $user->id)->firstOrFail();
    expect($group->type)->toBe(ContactGroupType::Friend);

    $this->actingAs($user)
        ->post("/contact-groups/{$group->id}/members", ['member_user_id' => $friend->id])
        ->assertRedirect();

    expect($group->members()->pluck('users.id'))->toContain($friend->id);
});

test('a user cannot add someone who is not their friend to a friend group', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $group = ContactGroup::factory()->create(['user_id' => $user->id, 'type' => ContactGroupType::Friend]);

    $response = $this->actingAs($user)->post("/contact-groups/{$group->id}/members", ['member_user_id' => $stranger->id]);

    $response->assertInvalid(['member_user_id']);
});

test('a user cannot add someone they do not follow to a following group', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $group = ContactGroup::factory()->following()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/contact-groups/{$group->id}/members", ['member_user_id' => $stranger->id]);

    $response->assertInvalid(['member_user_id']);
});

test('a user can add someone they follow to a following group', function () {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    Follow::factory()->create(['follower_id' => $user->id, 'followed_id' => $followed->id]);
    $group = ContactGroup::factory()->following()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post("/contact-groups/{$group->id}/members", ['member_user_id' => $followed->id])
        ->assertRedirect();

    expect($group->members()->pluck('users.id'))->toContain($followed->id);
});

test('a user cannot manage another users contact group', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $group = ContactGroup::factory()->create(['user_id' => $owner->id, 'type' => ContactGroupType::Friend]);

    $this->actingAs($outsider)->put("/contact-groups/{$group->id}", ['name' => 'Hijacked'])->assertForbidden();
    $this->actingAs($outsider)->delete("/contact-groups/{$group->id}")->assertForbidden();
});
