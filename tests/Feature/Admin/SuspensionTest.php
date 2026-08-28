<?php

use App\Enums\UserRole;
use App\Models\User;

test('a moderator can suspend a member', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $member = User::factory()->create(['role' => UserRole::Member]);

    $response = $this->actingAs($moderator)->post("/admin/users/{$member->id}/suspension", ['reason' => 'Repeated harassment']);

    $response->assertRedirect();
    $member->refresh();
    expect($member->isSuspended())->toBeTrue();
    expect($member->suspended_reason)->toBe('Repeated harassment');
    expect($member->suspended_by)->toBe($moderator->id);
});

test('an administrator can lift a suspension', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create(['role' => UserRole::Member, 'suspended_at' => now()]);

    $this->actingAs($admin)->delete("/admin/users/{$member->id}/suspension")->assertRedirect();

    expect($member->fresh()->isSuspended())->toBeFalse();
});

test('a suspended user cannot log in', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    $response = $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $response->assertSessionHasErrors(['username']);
    $this->assertGuest();
});

test('a suspended user is logged out mid session on their next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/events')->assertSuccessful();

    $user->update(['suspended_at' => now()]);

    $this->get('/events')->assertRedirect('/login');
    $this->assertGuest();
});

test('a regular member cannot suspend another user', function () {
    $member = User::factory()->create(['role' => UserRole::Member]);
    $other = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($member)->post("/admin/users/{$other->id}/suspension")->assertForbidden();
});

test('staff cannot suspend themselves', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);

    $response = $this->actingAs($moderator)->post("/admin/users/{$moderator->id}/suspension");

    $response->assertStatus(422);
    expect($moderator->fresh()->isSuspended())->toBeFalse();
});
