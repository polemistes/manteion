<?php

use App\Enums\UserRole;
use App\Mail\AdminNotice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('an administrator can delete a users account with their own password', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->delete("/admin/users/{$target->id}", ['password' => 'password']);

    $response->assertRedirect();
    expect(User::find($target->id))->toBeNull();
    $this->assertAuthenticatedAs($admin);
});

test('deleting a users account requires the administrators own correct password', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->delete("/admin/users/{$target->id}", ['password' => 'wrong-password']);

    $response->assertSessionHasErrors(['password']);
    expect(User::find($target->id))->not->toBeNull();
});

test('an administrator cannot delete their own account from the admin panel', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);

    $response = $this->actingAs($admin)->delete("/admin/users/{$admin->id}", ['password' => 'password']);

    $response->assertStatus(422);
    expect(User::find($admin->id))->not->toBeNull();
});

test('a moderator cannot delete user accounts', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $target = User::factory()->create();

    $this->actingAs($moderator)->delete("/admin/users/{$target->id}", ['password' => 'password'])->assertForbidden();
});

test('an administrator can email a user', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $target = User::factory()->create(['email' => 'target@example.com']);

    $response = $this->actingAs($admin)->post("/admin/users/{$target->id}/email", [
        'subject' => 'Regarding your account',
        'body' => 'Please review our community guidelines.',
    ]);

    $response->assertRedirect();
    Mail::assertSent(AdminNotice::class, fn (AdminNotice $mail) => $mail->hasTo('target@example.com')
        && $mail->subjectLine === 'Regarding your account');
});

test('a moderator cannot email a user', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $target = User::factory()->create();

    $this->actingAs($moderator)->post("/admin/users/{$target->id}/email", [
        'subject' => 'Hi',
        'body' => 'Hello',
    ])->assertForbidden();
});
