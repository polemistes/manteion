<?php

use App\Enums\AdminAlert;
use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Mail\AdminAlertMail;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * An administrator who asked to be emailed about everything.
 */
function alertedAdmin(string $email = 'admin@example.com'): User
{
    return User::factory()->create([
        'role' => UserRole::Administrator,
        'email' => $email,
        'notification_settings' => ['admin_email' => array_fill_keys(
            array_map(fn (AdminAlert $kind) => $kind->value, AdminAlert::cases()),
            true,
        )],
    ]);
}

function alertMailTo(string $email): ?AdminAlertMail
{
    $found = null;
    Mail::assertQueued(AdminAlertMail::class, function (AdminAlertMail $mail) use ($email, &$found) {
        if ($mail->hasTo($email)) {
            $found = $mail;

            return true;
        }

        return false;
    });

    return $found;
}

test('an administrator can turn the email alerts on and off; others cannot', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $member = User::factory()->create();

    $this->actingAs($admin)
        ->put('/settings/notifications', ['admin_email' => ['new_user' => true, 'message_sent' => true]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->actingAs($member)
        ->put('/settings/notifications', ['admin_email' => ['new_user' => true]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($admin->fresh()->notificationSettings()['admin_email'])->toMatchArray(['new_user' => true, 'message_sent' => true, 'event_created' => false])
        ->and($admin->fresh()->wantsAdminEmail(AdminAlert::NewUser))->toBeTrue()
        ->and($member->fresh()->notificationSettings()['admin_email']['new_user'])->toBeFalse()
        ->and($member->fresh()->wantsAdminEmail(AdminAlert::NewUser))->toBeFalse();
});

test('a registration emails the administrators who asked, and nobody else', function () {
    Mail::fake();
    alertedAdmin();
    User::factory()->create(['role' => UserRole::Administrator, 'email' => 'quiet-admin@example.com']);
    User::factory()->create(['role' => UserRole::Administrator, 'email' => 'suspended-admin@example.com', 'suspended_at' => now(),
        'notification_settings' => ['admin_email' => ['new_user' => true]]]);
    User::factory()->create(['email' => 'member@example.com', 'notification_settings' => ['admin_email' => ['new_user' => true]]]);

    $this->post('/register', [
        'name' => 'Nora Nyberg',
        'username' => 'nora_nyberg',
        'email' => 'nora@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect();

    $mail = alertMailTo('admin@example.com');
    expect($mail->subjectLine)->toBe('[Manteion] New member: Nora Nyberg')
        ->and($mail->lines)->toBe(['Nora Nyberg (@nora_nyberg) just registered with nora@example.com.'])
        ->and($mail->url)->toBe(route('admin.users.index'));
    Mail::assertNotQueued(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->hasTo('quiet-admin@example.com'));
    Mail::assertNotQueued(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->hasTo('suspended-admin@example.com'));
    Mail::assertNotQueued(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->hasTo('member@example.com'));
});

test('the administrator is not told about what they do themselves', function () {
    Mail::fake();
    $admin = alertedAdmin();
    alertedAdmin('other-admin@example.com');
    $member = User::factory()->create(['name' => 'Per Hansen', 'username' => 'per_hansen']);

    $this->actingAs($admin)->post("/admin/users/{$member->id}/suspension", ['reason' => 'Spam'])->assertRedirect();

    Mail::assertNotQueued(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->hasTo($admin->email));
    $mail = alertMailTo('other-admin@example.com');
    expect($mail->subjectLine)->toBe('[Manteion] Account suspended: Per Hansen')
        ->and($mail->lines)->toBe(['Account suspended for Per Hansen (@per_hansen), by '.$admin->name.'.', 'Reason: Spam']);
});

test('a message, an event, a leaflet and a report each email the administrator, without the message text', function () {
    Mail::fake();
    alertedAdmin();
    [$sender, $friend] = User::factory()->count(2)->create(['name' => 'Kari Nordmann'])->all();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);

    $this->actingAs($sender)->postJson("/messages/{$conversation->id}/send", ['body' => 'Top secret plans'])->assertCreated();
    $this->actingAs($sender)->post('/events', [
        'name' => 'Quiz night', 'description' => 'Bring a team.', 'starts_at' => '2026-11-10T18:00:00+01:00',
        'visibility' => 'public', 'allow_invitee_invites' => false, 'allow_invitee_comments' => true,
    ])->assertRedirect();
    $this->actingAs($sender)->post('/leaflets', ['body' => 'Lost cat', 'visibility' => 'friends'])->assertRedirect();
    $comment = EventComment::factory()->create(['event_id' => Event::factory()->create(['user_id' => $sender->id])->id, 'user_id' => $friend->id]);
    $this->actingAs($sender)->post('/reports', ['reportable_type' => 'comment', 'reportable_id' => $comment->id, 'reason' => 'Rude'])->assertRedirect();

    $subjects = [];
    Mail::assertQueued(AdminAlertMail::class, function (AdminAlertMail $mail) use (&$subjects) {
        $subjects[] = $mail->subjectLine;
        foreach ($mail->lines as $line) {
            expect($line)->not->toContain('Top secret plans');
        }

        return true;
    });
    expect($subjects)->toContain(
        '[Manteion] Message from Kari Nordmann in Kari Nordmann',
        '[Manteion] New event: Quiz night',
        '[Manteion] New leaflet from Kari Nordmann',
        '[Manteion] New report from Kari Nordmann',
    );
});

test('a deleted account emails the administrator, whoever deleted it', function () {
    Mail::fake();
    $admin = alertedAdmin();
    alertedAdmin('other-admin@example.com');
    $leaver = User::factory()->create(['name' => 'Lise Leaver', 'username' => 'lise']);
    $removed = User::factory()->create(['name' => 'Rolf Removed', 'username' => 'rolf']);

    $this->actingAs($leaver)->delete('/profile', ['password' => 'password'])->assertRedirect();
    $this->actingAs($admin)->delete("/admin/users/{$removed->id}", ['password' => 'password'])->assertRedirect();

    $subjects = [];
    Mail::assertQueued(AdminAlertMail::class, function (AdminAlertMail $mail) use (&$subjects) {
        if ($mail->hasTo('other-admin@example.com')) {
            $subjects[$mail->subjectLine] = $mail->lines;
        }

        return true;
    });
    expect($subjects)->toHaveKey('[Manteion] Account deleted: Lise Leaver')
        ->and($subjects['[Manteion] Account deleted: Lise Leaver'])->toBe(['Lise Leaver (@lise) is gone. They deleted it themselves.'])
        ->and($subjects['[Manteion] Account deleted: Rolf Removed'])->toBe(['Rolf Removed (@rolf) is gone. Deleted by '.$admin->name.'.']);
});
