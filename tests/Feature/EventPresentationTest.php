<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVisibility;
use App\Enums\AttendeeSource;
use App\Events\EventActivity;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\EventImage;
use App\Models\Friendship;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function presentationEventPayload(array $overrides = []): array
{
    return [
        'name' => 'Open Mic Night',
        'place' => 'Kafé Hærverk',
        'description' => 'Ten minutes each.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'ends_at' => null,
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'tag_ids' => [],
        ...$overrides,
    ];
}

test('a landscape upload is stored with its size and counts as a photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/events', presentationEventPayload([
        'image' => UploadedFile::fake()->image('photo.jpg', 1500, 1000),
    ]));

    $image = Event::query()->where('name', 'Open Mic Night')->firstOrFail()->images->first();

    expect($image->width)->toBe(1500)
        ->and($image->height)->toBe(1000)
        ->and($image->fit)->toBeNull()
        ->and($image->shape())->toBe('photo');
});

test('a portrait upload is a poster, and "show it whole" makes any image a poster', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/events', presentationEventPayload([
        'name' => 'Poster Event',
        'image' => UploadedFile::fake()->image('poster.jpg', 1000, 1500),
    ]));
    $this->actingAs($user)->post('/events', presentationEventPayload([
        'name' => 'Whole Photo',
        'image' => UploadedFile::fake()->image('wide.jpg', 1600, 900),
        'image_fit' => 'contain',
    ]));

    expect(Event::query()->where('name', 'Poster Event')->firstOrFail()->images->first()->shape())->toBe('poster')
        ->and(Event::query()->where('name', 'Whole Photo')->firstOrFail()->images->first()->shape())->toBe('poster');
});

test('an unknown fit is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/events', presentationEventPayload(['image_fit' => 'stretch']))
        ->assertSessionHasErrors('image_fit');
});

test('editing changes the fit without a new image, and keeps it when the form sends none', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $image = EventImage::factory()->create(['event_id' => $event->id, 'width' => 1600, 'height' => 900, 'fit' => null]);
    $payload = presentationEventPayload(['name' => $event->name]);

    $this->actingAs($user)->put("/events/{$event->id}", [...$payload, 'image_fit' => 'contain']);
    expect($image->fresh()->fit)->toBe('contain');

    $this->actingAs($user)->put("/events/{$event->id}", $payload);
    expect($image->fresh()->fit)->toBe('contain');

    $this->actingAs($user)->put("/events/{$event->id}", [...$payload, 'image_fit' => null]);
    expect($image->fresh()->fit)->toBeNull();
});

test('event cards carry the pending invitation and the faces the viewer may see', function () {
    $viewer = User::factory()->create();
    $host = User::factory()->create(['name' => 'Bjørn Haugen']);
    $friend = User::factory()->create(['name' => 'Camilla Iversen']);
    $stranger = User::factory()->create(['name' => 'Erik Solberg']);
    $hidden = User::factory()->create(['name' => 'Frida Berg']);
    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => $friend->id]);

    $event = Event::factory()->create(['user_id' => $host->id, 'starts_at' => now()->addDays(3)]);
    EventAttendee::factory()->create([
        'event_id' => $event->id,
        'user_id' => $viewer->id,
        'invited_by_user_id' => $host->id,
        'source' => AttendeeSource::Invited,
        'status' => AttendanceStatus::NoResponse,
    ]);
    EventAttendee::factory()->attending()->create(['event_id' => $event->id, 'user_id' => $stranger->id]);
    EventAttendee::factory()->attending()->visibility(AttendanceVisibility::Friends)->create(['event_id' => $event->id, 'user_id' => $friend->id]);
    EventAttendee::factory()->attending()->visibility(AttendanceVisibility::None)->create(['event_id' => $event->id, 'user_id' => $hidden->id]);

    $this->actingAs($viewer)->get('/events')->assertInertia(fn (Assert $page) => $page
        ->where('events.0.invitation.by.name', 'Bjørn Haugen')
        ->where('events.0.going', fn ($going) => collect($going)->pluck('name')->all() === ['Camilla Iversen', 'Erik Solberg'])
        ->where('events.0.is_host', false));
});

test('the feed lists pending invitations, what is coming up, and this month\'s event days', function () {
    $viewer = User::factory()->create();
    $host = User::factory()->create();

    $invited = Event::factory()->create(['user_id' => $host->id, 'name' => 'Cabin Weekend', 'starts_at' => now()->addDays(2)]);
    EventAttendee::factory()->create(['event_id' => $invited->id, 'user_id' => $viewer->id, 'source' => AttendeeSource::Invited, 'status' => AttendanceStatus::NoResponse]);

    $going = Event::factory()->create(['user_id' => $host->id, 'name' => 'Book Club', 'starts_at' => now()->addDays(4)]);
    EventAttendee::factory()->attending()->create(['event_id' => $going->id, 'user_id' => $viewer->id]);

    $hosting = Event::factory()->create(['user_id' => $viewer->id, 'name' => 'Board Game Night', 'starts_at' => now()->addHours(5)]);

    $this->actingAs($viewer)->get('/events')->assertInertia(fn (Assert $page) => $page
        ->has('invitations', 1)
        ->where('invitations.0.name', 'Cabin Weekend')
        ->where('comingUp', fn ($events) => collect($events)->pluck('name')->all() === ['Board Game Night', 'Book Club'])
        ->where('calendarDays.month', now(config('app.display_timezone'))->format('Y-m'))
        ->has('calendarDays.days'));

    expect($hosting)->not->toBeNull();
});

test('the mini month marks every day an event runs, within the month', function () {
    Carbon::setTestNow('2026-09-18 12:00:00');
    $viewer = User::factory()->create();

    Event::factory()->create(['user_id' => $viewer->id, 'starts_at' => '2026-09-25 16:00:00', 'ends_at' => '2026-09-27 12:00:00']);
    Event::factory()->create(['user_id' => $viewer->id, 'starts_at' => '2026-09-30 08:00:00', 'ends_at' => '2026-10-02 12:00:00']);
    Event::factory()->create(['user_id' => $viewer->id, 'starts_at' => '2026-10-05 08:00:00']);

    $this->actingAs($viewer)->get('/events')->assertInertia(fn (Assert $page) => $page
        ->where('calendarDays.month', '2026-09')
        ->where('calendarDays.days', ['2026-09-25', '2026-09-26', '2026-09-27', '2026-09-30']));
});

test('people who can see an event can like its comments, and the event page hears about it', function () {
    EventFacade::fake([EventActivity::class]);
    $host = User::factory()->create();
    $guest = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $guest->id]);
    $comment = EventComment::factory()->create(['event_id' => $event->id, 'user_id' => $host->id]);

    $this->actingAs($guest)
        ->postJson('/reactions', ['reactable_type' => 'comment', 'reactable_id' => $comment->id, 'emoji' => '❤️'])
        ->assertOk()
        ->assertJsonPath('reactions.0.emoji', '❤️')
        ->assertJsonPath('reactions.0.reacted', true);

    expect(Reaction::query()->where('reactable_type', 'comment')->where('reactable_id', $comment->id)->count())->toBe(1);
    EventFacade::assertDispatched(EventActivity::class, fn (EventActivity $activity) => $activity->eventId === $event->id && $activity->kind === 'comment');

    $this->actingAs($host)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
        ->where('comments.0.reactions.0.count', 1)
        ->where('comments.0.reactions.0.reacted', false));
});

test('a comment cannot be liked by someone who cannot see the event, nor once deleted', function () {
    $host = User::factory()->create();
    $outsider = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $comment = EventComment::factory()->create(['event_id' => $event->id, 'user_id' => $host->id]);
    $deleted = EventComment::factory()->create(['event_id' => $event->id, 'user_id' => $host->id, 'is_deleted' => true]);

    $this->actingAs($outsider)
        ->postJson('/reactions', ['reactable_type' => 'comment', 'reactable_id' => $comment->id, 'emoji' => '❤️'])
        ->assertForbidden();

    $this->actingAs($host)
        ->postJson('/reactions', ['reactable_type' => 'comment', 'reactable_id' => $deleted->id, 'emoji' => '❤️'])
        ->assertForbidden();
});

test('the event page shows the cover\'s shape and the viewer\'s invitation', function () {
    $host = User::factory()->create(['name' => 'Ingrid Dahl']);
    $guest = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    EventImage::factory()->create(['event_id' => $event->id, 'width' => 900, 'height' => 1350]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $guest->id, 'invited_by_user_id' => $host->id, 'source' => AttendeeSource::Invited]);

    $this->actingAs($guest)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
        ->where('event.images.0.shape', 'poster')
        ->where('event.images.0.width', 900)
        ->where('invitation.by.name', 'Ingrid Dahl'));
});

test('the calendar file carries the event, escaped, in UTC', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $host->id,
        'name' => 'Dinner, drinks; dancing',
        'description' => "Line one\nLine two",
        'place' => 'Grünerløkka',
        'starts_at' => '2026-11-06 18:00:00',
        'ends_at' => '2026-11-06 23:30:00',
    ]);

    $response = $this->actingAs($host)->get("/events/{$event->id}/calendar.ics");

    $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $body = $response->getContent();

    expect($body)->toContain('BEGIN:VEVENT')
        ->toContain('DTSTART:20261106T180000Z')
        ->toContain('DTEND:20261106T233000Z')
        ->toContain('SUMMARY:Dinner\, drinks\; dancing')
        ->toContain('DESCRIPTION:Line one\nLine two')
        ->toContain('LOCATION:Grünerløkka');
});

test('the calendar file is only for people who can see the event', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get("/events/{$event->id}/calendar.ics")
        ->assertForbidden();
});

test('the feed narrows to invitations, events the viewer is going to, or ones they host', function () {
    $viewer = User::factory()->create();
    $host = User::factory()->create();

    $invited = Event::factory()->create(['user_id' => $host->id, 'name' => 'Invited', 'starts_at' => now()->addDay()]);
    EventAttendee::factory()->create(['event_id' => $invited->id, 'user_id' => $viewer->id, 'source' => AttendeeSource::Invited, 'status' => AttendanceStatus::NoResponse]);
    $going = Event::factory()->create(['user_id' => $host->id, 'name' => 'Going', 'starts_at' => now()->addDays(2)]);
    EventAttendee::factory()->attending()->create(['event_id' => $going->id, 'user_id' => $viewer->id]);
    Event::factory()->create(['user_id' => $viewer->id, 'name' => 'Hosting', 'starts_at' => now()->addDays(3)]);

    $names = fn (string $mine) => collect($this->actingAs($viewer)->get("/events?mine={$mine}")->inertiaProps('events'))->pluck('name')->all();

    expect($names('invited'))->toBe(['Invited'])
        ->and($names('going'))->toBe(['Going'])
        ->and($names('hosting'))->toBe(['Hosting'])
        ->and($names('nonsense'))->toBe(['Invited', 'Going', 'Hosting']);
});

test('the feed searches the viewer\'s own events by name and place', function () {
    $viewer = User::factory()->create();
    Event::factory()->create(['user_id' => $viewer->id, 'name' => 'Cabin Weekend', 'place' => 'Kvitfjell', 'starts_at' => now()->addDay()]);
    Event::factory()->create(['user_id' => $viewer->id, 'name' => 'Book Club', 'place' => 'Majorstuen', 'starts_at' => now()->addDays(2)]);

    $names = fn (string $q) => collect($this->actingAs($viewer)->get('/events?q='.urlencode($q))->inertiaProps('events'))->pluck('name')->all();

    expect($names('cabin'))->toBe(['Cabin Weekend'])
        ->and($names('majorstuen'))->toBe(['Book Club']);
});

test('the people to invite never give away a guest who hid their answer', function () {
    $host = User::factory()->create();
    $viewer = User::factory()->create();
    $hidden = User::factory()->create();
    $other = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => $hidden->id]);
    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => $other->id]);
    $event = Event::factory()->create(['user_id' => $host->id, 'allow_invitee_invites' => true]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $viewer->id]);
    EventAttendee::factory()->attending()->visibility(AttendanceVisibility::None)->create(['event_id' => $event->id, 'user_id' => $hidden->id]);

    $this->actingAs($viewer)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
        ->where('event.can_invite', true)
        ->where('attendees', fn ($attendees) => ! collect($attendees)->pluck('user.id')->contains($hidden->id))
        ->where('inviteCandidates', fn ($candidates) => collect($candidates)->pluck('id')->sort()->values()->all() === collect([$hidden->id, $other->id])->sort()->values()->all()));
});

test('someone who may not invite gets no one to invite', function () {
    $host = User::factory()->create();
    $viewer = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $viewer->id, 'addressee_id' => User::factory()->create()->id]);
    $event = Event::factory()->create(['user_id' => $host->id, 'allow_invitee_invites' => false]);
    EventAttendee::factory()->create(['event_id' => $event->id, 'user_id' => $viewer->id]);

    $this->actingAs($viewer)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
        ->where('event.can_invite', false)
        ->where('inviteCandidates', []));
});

test('an event without an end time runs until the end of its day', function () {
    // 14:00 in Oslo.
    Carbon::setTestNow('2026-09-18 12:00:00');
    $host = User::factory()->create();

    $startedToday = Event::factory()->create(['user_id' => $host->id, 'starts_at' => '2026-09-18 10:00:00', 'ends_at' => null]);
    $yesterday = Event::factory()->create(['user_id' => $host->id, 'starts_at' => '2026-09-17 18:00:00', 'ends_at' => null]);
    $endedEarlier = Event::factory()->create(['user_id' => $host->id, 'starts_at' => '2026-09-18 08:00:00', 'ends_at' => '2026-09-18 09:00:00']);

    expect($startedToday->isOver())->toBeFalse()
        ->and($yesterday->isOver())->toBeTrue()
        ->and($endedEarlier->isOver())->toBeTrue();

    $this->actingAs($host)->get('/events')->assertInertia(fn (Assert $page) => $page
        ->where('comingUp', fn ($events) => collect($events)->pluck('id')->contains($startedToday->id)
            && ! collect($events)->pluck('id')->contains($yesterday->id)));
});
