<?php

use App\Enums\SpokespersonRole;
use App\Jobs\NotifyFollowersOfEvent;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\FollowedUserPublishedEvent;
use App\Notifications\NewEventComment;
use App\Notifications\SpokespersonInvited;
use App\Services\BadgeCounts;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function organizerEventPayload(array $overrides = []): array
{
    return [
        'name' => 'Quiz night',
        'description' => 'Bring a team.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'public',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        ...$overrides,
    ];
}

test('creating an organizer makes you its main spokesperson, and the handle must be unique', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/organizers', ['name' => 'Kafé Hærverk', 'handle' => 'haerverk', 'description' => 'Bar and venue'])
        ->assertRedirect();

    $organizer = Organizer::query()->where('handle', 'haerverk')->firstOrFail();
    expect($organizer->created_by)->toBe($user->id)
        ->and($organizer->isMainSpokesperson($user))->toBeTrue()
        ->and($user->fresh()->organizers()->pluck('organizers.id')->all())->toBe([$organizer->id]);

    $this->actingAs(User::factory()->create())->post('/organizers', ['name' => 'Another', 'handle' => 'haerverk'])
        ->assertInvalid(['handle']);
});

test('only the main spokesperson edits or deletes the organizer; a spokesperson may act for it', function () {
    [$main, $spokesperson, $stranger] = User::factory()->count(3)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($spokesperson)->create();

    $this->actingAs($spokesperson)->put("/organizers/{$organizer->id}", ['name' => 'New name', 'handle' => $organizer->handle])->assertForbidden();
    $this->actingAs($stranger)->get("/organizers/{$organizer->id}/edit")->assertForbidden();
    $this->actingAs($main)->put("/organizers/{$organizer->id}", ['name' => 'New name', 'handle' => $organizer->handle])->assertRedirect("/organizers/{$organizer->id}");
    expect($organizer->fresh()->name)->toBe('New name');

    expect($spokesperson->can('act', $organizer))->toBeTrue()
        ->and($stranger->can('act', $organizer))->toBeFalse()
        ->and($stranger->can('view', $organizer))->toBeTrue();

    $this->actingAs($spokesperson)->delete("/organizers/{$organizer->id}")->assertForbidden();
    $this->actingAs($main)->delete("/organizers/{$organizer->id}")->assertRedirect('/settings/organizers');
    $this->assertModelMissing($organizer);
});

test('the main spokesperson invites a friend, who sees it in Activity and can accept', function () {
    Notification::fake();
    [$main, $friend] = User::factory()->count(2)->create()->all();
    Friendship::factory()->create(['requester_id' => $main->id, 'addressee_id' => $friend->id, 'status' => 'accepted']);
    $organizer = Organizer::factory()->runBy($main)->create();

    $this->actingAs($main)->post("/organizers/{$organizer->id}/spokespersons", ['user_id' => $friend->id])->assertRedirect();

    $membership = $organizer->membershipOf($friend);
    expect($membership)->not->toBeNull()
        ->and($membership->isPending())->toBeTrue()
        ->and($membership->invited_by_user_id)->toBe($main->id)
        ->and(BadgeCounts::for($friend)['requests'])->toBe(1)
        ->and($friend->fresh()->organizerIds()->all())->toBe([]);
    Notification::assertSentTo($friend, SpokespersonInvited::class, fn (SpokespersonInvited $notification) => $notification->subject()->is($organizer)
        && $notification->actor()->is($main)
        && $notification->toArray($friend)['spokesperson_id'] === $membership->id
        && $notification->toArray($friend)['subject_type'] === 'organizer');

    // Inviting again, or someone who is not a friend, is refused.
    $this->actingAs($main)->post("/organizers/{$organizer->id}/spokespersons", ['user_id' => $friend->id])->assertInvalid(['user_id']);
    $this->actingAs($main)->post("/organizers/{$organizer->id}/spokespersons", ['user_id' => User::factory()->create()->id])->assertInvalid(['user_id']);

    $this->actingAs($friend)->patch("/organizers/{$organizer->id}/spokespersons/{$friend->id}", ['action' => 'accept'])->assertRedirect();
    expect($organizer->membershipOf($friend)->isPending())->toBeFalse()
        ->and($organizer->hasSpokesperson($friend->fresh()))->toBeTrue()
        ->and(BadgeCounts::for($friend)['requests'])->toBe(0);
});

test('declining an invitation removes it quietly; only the invited person answers', function () {
    [$main, $friend, $other] = User::factory()->count(3)->create()->all();
    Friendship::factory()->create(['requester_id' => $main->id, 'addressee_id' => $friend->id, 'status' => 'accepted']);
    $organizer = Organizer::factory()->runBy($main)->create();
    $this->actingAs($main)->post("/organizers/{$organizer->id}/spokespersons", ['user_id' => $friend->id])->assertRedirect();
    expect($friend->notifications()->count())->toBe(1);

    $this->actingAs($other)->patch("/organizers/{$organizer->id}/spokespersons/{$friend->id}", ['action' => 'accept'])->assertForbidden();
    $this->actingAs($friend)->patch("/organizers/{$organizer->id}/spokespersons/{$friend->id}", ['action' => 'decline'])->assertRedirect();

    expect($organizer->membershipOf($friend))->toBeNull()
        ->and($friend->notifications()->count())->toBe(0)
        ->and(BadgeCounts::for($friend)['requests'])->toBe(0);
});

test('a spokesperson may leave or hide from the page; the main spokesperson hands over before leaving', function () {
    [$main, $spokesperson] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($spokesperson)->create();
    $event = Event::factory()->create(['user_id' => $spokesperson->id, 'organizer_id' => $organizer->id]);

    $this->actingAs($spokesperson)->patch("/organizers/{$organizer->id}/spokespersons/{$spokesperson->id}", ['action' => 'accept', 'listed' => false])->assertForbidden();
    $this->actingAs($main)->delete("/organizers/{$organizer->id}/spokespersons/{$main->id}")->assertInvalid(['role']);

    $this->actingAs($main)->patch("/organizers/{$organizer->id}/spokespersons/{$spokesperson->id}", ['action' => 'make_main'])->assertRedirect();
    expect($organizer->isMainSpokesperson($spokesperson->fresh()))->toBeTrue()
        ->and($organizer->isMainSpokesperson($main->fresh()))->toBeFalse();

    // Now a plain spokesperson, the old main may leave; the event they made
    // for the organizer stays with it.
    $this->actingAs($main)->delete("/organizers/{$organizer->id}/spokespersons/{$main->id}")->assertRedirect();
    expect($organizer->membershipOf($main))->toBeNull()
        ->and($event->fresh()->organizer_id)->toBe($organizer->id);
});

test('a spokesperson hosts an event for the organizer; a stranger cannot', function () {
    [$spokesperson, $stranger] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($spokesperson)->create();

    $this->actingAs($stranger)->post('/events', organizerEventPayload(['organizer_id' => $organizer->id]))->assertForbidden();
    $this->actingAs($spokesperson)->post('/events', organizerEventPayload(['organizer_id' => $organizer->id]))->assertRedirect();

    $event = Event::query()->where('name', 'Quiz night')->firstOrFail();
    expect($event->organizer_id)->toBe($organizer->id)
        ->and($event->user_id)->toBe($spokesperson->id)
        ->and($event->hostName())->toBe($organizer->name)
        ->and($event->isHostedBy($spokesperson))->toBeTrue()
        ->and($event->isHostedBy($stranger))->toBeFalse();

    $this->actingAs($spokesperson)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.organizer.id', $organizer->id)
            ->where('event.is_owner', true)
            ->where('event.can_report', false));
});

test('an organizers public event reaches its followers, not the followers of the person who made it', function () {
    [$spokesperson, $organizerFollower, $personFollower, $stranger] = User::factory()->count(4)->create()->all();
    $organizer = Organizer::factory()->runBy($spokesperson)->create();
    $organizer->followers()->attach($organizerFollower->id);
    Follow::factory()->create(['follower_id' => $personFollower->id, 'followed_id' => $spokesperson->id]);
    $event = Event::factory()->public()->create(['user_id' => $spokesperson->id, 'organizer_id' => $organizer->id, 'starts_at' => now()->addDay()]);

    $this->actingAs($organizerFollower)->get("/events/{$event->id}")->assertOk();
    $this->actingAs($personFollower)->get("/events/{$event->id}")->assertForbidden();
    $this->actingAs($stranger)->get("/events/{$event->id}")->assertForbidden();

    $this->actingAs($organizerFollower)->get('/events')
        ->assertInertia(fn (Assert $page) => $page->where('events.0.id', $event->id)->where('events.0.organizer.name', $organizer->name)->where('events.0.is_host', false));
    $this->actingAs($personFollower)->get('/events')
        ->assertInertia(fn (Assert $page) => $page->has('events', 0));
    // The person's own profile lists their own events, not the organizer's.
    $this->actingAs($organizerFollower)->get("/users/{$spokesperson->id}")
        ->assertInertia(fn (Assert $page) => $page->has('events', 0));
    $this->actingAs($organizerFollower)->get("/organizers/{$organizer->id}")
        ->assertInertia(fn (Assert $page) => $page->component('organizers/Show')->where('events.0.id', $event->id)->where('is_following', true));
});

test('every spokesperson hears of a comment on the organizers event, except the one who wrote it', function () {
    Notification::fake();
    [$main, $spokesperson, $guest] = User::factory()->count(3)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($spokesperson)->create();
    $event = Event::factory()->searchable()->create(['user_id' => $main->id, 'organizer_id' => $organizer->id]);

    $this->actingAs($guest)->post("/events/{$event->id}/comments", ['body' => 'Looking forward to it'])->assertRedirect();
    Notification::assertSentTo([$main, $spokesperson], NewEventComment::class);

    $this->actingAs($spokesperson)->post("/events/{$event->id}/comments", ['body' => 'See you there'])->assertRedirect();
    Notification::assertSentToTimes($spokesperson, NewEventComment::class, 1);
    Notification::assertSentToTimes($main, NewEventComment::class, 2);
});

test('the followers of an organizer are told when it publishes an event, under its name', function () {
    Notification::fake();
    [$spokesperson, $follower] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($spokesperson)->create(['name' => 'Kafé Hærverk']);
    $organizer->followers()->attach($follower->id);
    $event = Event::factory()->public()->create(['user_id' => $spokesperson->id, 'organizer_id' => $organizer->id]);

    (new NotifyFollowersOfEvent($event))->handle();

    Notification::assertSentTo($follower, FollowedUserPublishedEvent::class, function (FollowedUserPublishedEvent $notification) use ($follower) {
        $push = $notification->toWebPush($follower, $notification)->toArray();

        return str_contains($push['body'], 'Kafé Hærverk');
    });
    expect($event->fresh()->followers_notified_at)->not->toBeNull();
});

test('following an organizer needs no approval and can be undone', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->runBy(User::factory()->create())->create();

    $this->actingAs($user)->post("/organizers/{$organizer->id}/follow")->assertRedirect();
    expect($organizer->isFollowedBy($user))->toBeTrue()->and($user->fresh()->followedOrganizerIds()->all())->toBe([$organizer->id]);

    $this->actingAs($user)->delete("/organizers/{$organizer->id}/follow")->assertRedirect();
    expect($organizer->isFollowedBy($user))->toBeFalse();
});

test('the settings page lists the organizers you speak for and the invitations waiting', function () {
    [$main, $friend] = User::factory()->count(2)->create()->all();
    Friendship::factory()->create(['requester_id' => $main->id, 'addressee_id' => $friend->id, 'status' => 'accepted']);
    $organizer = Organizer::factory()->runBy($main)->create();
    $this->actingAs($main)->post("/organizers/{$organizer->id}/spokespersons", ['user_id' => $friend->id])->assertRedirect();

    $this->actingAs($main)->get('/settings/organizers')
        ->assertInertia(fn (Assert $page) => $page->component('settings/Organizers')
            ->where('organizers.0.id', $organizer->id)->where('organizers.0.role', SpokespersonRole::Main->value)->has('invitations', 0));
    $this->actingAs($friend)->get('/settings/organizers')
        ->assertInertia(fn (Assert $page) => $page->has('organizers', 0)->where('invitations.0.organizer.id', $organizer->id)->where('invitations.0.invited_by', $main->name));
});
