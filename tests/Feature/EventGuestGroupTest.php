<?php

use App\Enums\AttendanceStatus;
use App\Enums\ContactGroupType;
use App\Models\ContactGroup;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Friendship;
use App\Models\Organizer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeFriendsOf(User $user, User ...$friends): void
{
    foreach ($friends as $friend) {
        Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);
    }
}

test('a host keeps the guests who are their friends as a group, whatever they answered', function () {
    $host = User::factory()->create();
    [$going, $declined, $unanswered, $notInvited, $friendOfGuest] = User::factory()->count(5)->create()->all();
    makeFriendsOf($host, $going, $declined, $unanswered, $notInvited);
    $event = Event::factory()->create(['user_id' => $host->id]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $going->id, 'status' => AttendanceStatus::Attending]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $declined->id, 'status' => AttendanceStatus::NotAttending]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $unanswered->id]);
    // Invited by a guest, and no friend of the host's: cannot be in a group of friends.
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $friendOfGuest->id]);
    // The host's own answer is on the list too.
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $host->id, 'status' => AttendanceStatus::Attending]);

    $this->actingAs($host)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page->where('event.friend_guest_count', 3));

    $this->actingAs($host)->post("/events/{$event->id}/guest-group", ['name' => 'Quiz gang'])->assertRedirect();

    $group = ContactGroup::query()->where('user_id', $host->id)->sole();
    expect($group->name)->toBe('Quiz gang')
        ->and($group->type)->toBe(ContactGroupType::Friend)
        ->and($group->members()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$going->id, $declined->id, $unanswered->id])->sort()->values()->all());
});

test('a guest list with no friends of the host on it makes no group', function () {
    $host = User::factory()->create();
    $event = Event::factory()->public()->create(['user_id' => $host->id]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => User::factory()->create()->id]);

    $this->actingAs($host)->post("/events/{$event->id}/guest-group", ['name' => 'Nobody'])->assertInvalid(['name']);
    $this->actingAs($host)->post("/events/{$event->id}/guest-group", ['name' => ''])->assertInvalid(['name']);

    expect(ContactGroup::query()->count())->toBe(0);
});

test('only a host keeps the guest list as a group', function () {
    $host = User::factory()->create();
    $guest = User::factory()->create();
    makeFriendsOf($host, $guest);
    $event = Event::factory()->create(['user_id' => $host->id]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $guest->id]);

    $this->actingAs($guest)->post("/events/{$event->id}/guest-group", ['name' => 'Mine'])->assertForbidden();
    $this->actingAs($guest)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page->where('event.friend_guest_count', 0));

    expect(ContactGroup::query()->count())->toBe(0);
});

test('the invite dialog is given the groups of friends, and only to someone who may invite', function () {
    $host = User::factory()->create();
    [$a, $b, $guest] = User::factory()->count(3)->create()->all();
    makeFriendsOf($host, $a, $b, $guest);
    $event = Event::factory()->create(['user_id' => $host->id, 'allow_invitee_invites' => false]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $guest->id]);
    $gang = ContactGroup::factory()->create(['user_id' => $host->id, 'type' => ContactGroupType::Friend, 'name' => 'B gang']);
    $gang->members()->attach([$a->id, $b->id]);
    ContactGroup::factory()->create(['user_id' => $host->id, 'type' => ContactGroupType::Friend, 'name' => 'A empty']);
    ContactGroup::factory()->following()->create(['user_id' => $host->id]);
    ContactGroup::factory()->create(['user_id' => $guest->id, 'type' => ContactGroupType::Friend]);

    $this->actingAs($host)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('inviteGroups', 2)
            ->where('inviteGroups.0.name', 'A empty')
            ->where('inviteGroups.0.member_ids', [])
            ->where('inviteGroups.1.id', $gang->id)
            ->where('inviteGroups.1.member_ids', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$a->id, $b->id])->sort()->values()->all()));

    // A guest who may not invite gets no groups, their own included.
    $this->actingAs($guest)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page->has('inviteGroups', 0));
});

test('a whole group is invited in one go', function () {
    $host = User::factory()->create();
    [$a, $b] = User::factory()->count(2)->create()->all();
    makeFriendsOf($host, $a, $b);
    $event = Event::factory()->create(['user_id' => $host->id]);

    $this->actingAs($host)->post("/events/{$event->id}/invitations", ['user_ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();

    expect($event->attendees()->pluck('user_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

test("a spokesperson invites the organizer's followers; nobody else invites people who are not their friends", function () {
    [$main, $follower, $stranger, $guest] = User::factory()->count(4)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->create();
    $organizer->followers()->attach($follower->id);
    $event = Event::factory()->public()->create(['user_id' => $main->id, 'organizer_id' => $organizer->id, 'allow_invitee_invites' => true]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $guest->id]);

    $this->actingAs($main)->post("/events/{$event->id}/invitations", ['user_ids' => [$follower->id]])->assertSessionHasNoErrors();
    expect($event->attendees()->where('user_id', $follower->id)->exists())->toBeTrue();

    // Someone following nothing is not the organizer's to invite.
    $this->actingAs($main)->post("/events/{$event->id}/invitations", ['user_ids' => [$stranger->id]])->assertInvalid(['user_ids']);

    // A guest who may invite invites their own friends, not the followers.
    $otherFollower = User::factory()->create();
    $organizer->followers()->attach($otherFollower->id);
    $this->actingAs($guest)->post("/events/{$event->id}/invitations", ['user_ids' => [$otherFollower->id]])->assertInvalid(['user_ids']);

    // On their own events a spokesperson invites friends only.
    $personal = Event::factory()->create(['user_id' => $main->id]);
    $this->actingAs($main)->post("/events/{$personal->id}/invitations", ['user_ids' => [$otherFollower->id]])->assertInvalid(['user_ids']);
});
