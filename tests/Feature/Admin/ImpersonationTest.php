<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\User;

test('an administrator can start and see the app as the impersonated user', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($admin)->post("/admin/users/{$member->id}/impersonate")->assertRedirect('/events');

    $this->assertAuthenticatedAs($member);
    expect(session('impersonator_id'))->toBe($admin->id);
});

test('exiting impersonation restores the administrator session', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($admin)->post("/admin/users/{$member->id}/impersonate");
    $this->post('/admin/impersonate/exit')->assertRedirect('/admin/users');

    $this->assertAuthenticatedAs($admin);
    expect(session('impersonator_id'))->toBeNull();
});

test('a non-administrator cannot start impersonation', function () {
    $member = User::factory()->create(['role' => UserRole::Member]);
    $other = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($member)->post("/admin/users/{$other->id}/impersonate")->assertForbidden();
});

test('while impersonating, posting content is blocked', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create(['role' => UserRole::Member]);
    $event = Event::factory()->create();

    $this->actingAs($admin)->post("/admin/users/{$member->id}/impersonate");

    $this->post("/events/{$event->id}/comments", ['body' => 'Trying to post as this user'])
        ->assertForbidden();
});

test('while impersonating, deleting the users content is still allowed', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create(['role' => UserRole::Member]);
    $event = Event::factory()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $member->id]);

    $this->actingAs($admin)->post("/admin/users/{$member->id}/impersonate");

    $this->delete("/events/{$event->id}/comments/{$comment->id}")->assertRedirect();

    expect(EventComment::find($comment->id))->toBeNull();
});

test('while impersonating, subscribing to live updates is still allowed', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($admin)->post("/admin/users/{$member->id}/impersonate");

    $response = $this->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-App.Models.User.{$member->id}",
    ]);

    expect($response->status())->not->toBe(403);
});
