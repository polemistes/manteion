<?php

use App\Enums\UserRole;
use App\Models\User;

test('an administrator can promote a member to moderator', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create(['role' => UserRole::Member]);

    $response = $this->actingAs($admin)->patch("/admin/users/{$member->id}/role", ['role' => 'moderator']);

    $response->assertRedirect();
    expect($member->fresh()->role)->toBe(UserRole::Moderator);
});

test('an administrator can demote a moderator back to member', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);

    $this->actingAs($admin)->patch("/admin/users/{$moderator->id}/role", ['role' => 'member'])->assertRedirect();

    expect($moderator->fresh()->role)->toBe(UserRole::Member);
});

test('an administrator cannot change their own role', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);

    $response = $this->actingAs($admin)->patch("/admin/users/{$admin->id}/role", ['role' => 'member']);

    $response->assertForbidden();
    expect($admin->fresh()->role)->toBe(UserRole::Administrator);
});

test('a moderator cannot change roles', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $member = User::factory()->create(['role' => UserRole::Member]);

    $response = $this->actingAs($moderator)->patch("/admin/users/{$member->id}/role", ['role' => 'administrator']);

    $response->assertForbidden();
    expect($member->fresh()->role)->toBe(UserRole::Member);
});

test('a regular member cannot change roles', function () {
    $member = User::factory()->create(['role' => UserRole::Member]);
    $other = User::factory()->create(['role' => UserRole::Member]);

    $response = $this->actingAs($member)->patch("/admin/users/{$other->id}/role", ['role' => 'administrator']);

    $response->assertForbidden();
});

test('the full user list including emails is only visible to administrators', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $target = User::factory()->create(['email' => 'target@example.com']);

    $response = $this->actingAs($admin)->get('/admin/users?q=target@example.com');

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.email', $target->email)
    );
});

test('moderators cannot access the administrator user list', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);

    $this->actingAs($moderator)->get('/admin/users')->assertForbidden();
});
