<?php

use App\Models\Event;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\NewLeafletResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An organizer run by $main with $second speaking for it too, $follower
 * following it, and $stranger with no tie to it.
 *
 * @return array{main: User, second: User, follower: User, stranger: User, organizer: Organizer}
 */
function organizerCast(): array
{
    [$main, $second, $follower, $stranger] = User::factory()->count(4)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($second)->create();
    $organizer->followers()->attach($follower->id);

    return compact('main', 'second', 'follower', 'stranger', 'organizer');
}

test('a spokesperson can comment as the organizer, and the comment names them too', function () {
    ['second' => $second, 'follower' => $follower, 'organizer' => $organizer, 'main' => $main] = organizerCast();
    $event = Event::factory()->public()->create(['user_id' => $main->id, 'organizer_id' => $organizer->id, 'allow_invitee_comments' => true]);

    $this->actingAs($second)->post("/events/{$event->id}/comments", ['body' => 'Doors open at seven.', 'as_organizer' => 1])->assertRedirect();
    // Asking to speak as the organizer means nothing for anyone else.
    $this->actingAs($follower)->post("/events/{$event->id}/comments", ['body' => 'Great!', 'as_organizer' => 1])->assertRedirect();

    $this->assertDatabaseHas('event_comments', ['event_id' => $event->id, 'user_id' => $second->id, 'organizer_id' => $organizer->id]);
    $this->assertDatabaseHas('event_comments', ['event_id' => $event->id, 'user_id' => $follower->id, 'organizer_id' => null]);

    $this->actingAs($follower)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.can_comment_as_organizer', false)
            ->where('comments.0.organizer.id', $organizer->id)
            ->where('comments.0.user.id', $second->id)
            ->where('comments.0.is_host', true)
            ->where('comments.1.organizer', null)
            ->where('comments.1.is_host', false));
    $this->actingAs($second)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page->where('event.can_comment_as_organizer', true));
});

test('a spokesperson may invite the organizer\'s followers, not only their own friends', function () {
    ['main' => $main, 'follower' => $follower, 'organizer' => $organizer] = organizerCast();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $main->id, 'addressee_id' => $friend->id]);
    $event = Event::factory()->create(['user_id' => $main->id, 'organizer_id' => $organizer->id]);
    $personal = Event::factory()->create(['user_id' => $main->id]);

    $this->actingAs($main)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page->where(
            'inviteCandidates',
            fn ($candidates) => collect($candidates)->pluck('id')->sort()->values()->all() === collect([$friend->id, $follower->id])->sort()->values()->all()
        ));
    $this->actingAs($main)->get("/events/{$personal->id}")
        ->assertInertia(fn (Assert $page) => $page->has('inviteCandidates', 1)->where('inviteCandidates.0.id', $friend->id));
});

test('People finds organizers by name or handle and lists the ones you follow', function () {
    ['follower' => $follower, 'stranger' => $stranger, 'organizer' => $organizer] = organizerCast();
    $organizer->update(['name' => 'Kafé Hærverk', 'handle' => 'haerverk']);
    Organizer::factory()->create(['name' => 'Other place', 'handle' => 'other']);

    $this->actingAs($follower)->get('/contacts?find=haerv')
        ->assertInertia(fn (Assert $page) => $page->component('contacts/Index')
            ->has('findOrganizers', 1)
            ->where('findOrganizers.0.id', $organizer->id)
            ->where('findOrganizers.0.is_following', true)
            ->has('followedOrganizers', 1)
            ->where('followedOrganizers.0.id', $organizer->id));
    $this->actingAs($stranger)->get('/contacts?find=Kafé')
        ->assertInertia(fn (Assert $page) => $page
            ->where('findOrganizers.0.is_following', false)
            ->has('followedOrganizers', 0));
});

test('a profile lists the organizers the person speaks for, when they chose to be listed', function () {
    ['second' => $second, 'stranger' => $stranger, 'organizer' => $organizer] = organizerCast();
    $organizer->memberships()->where('user_id', $second->id)->update(['listed' => true]);

    $this->actingAs($stranger)->get("/users/{$second->id}")
        ->assertInertia(fn (Assert $page) => $page->has('organizers', 1)->where('organizers.0.id', $organizer->id));

    $organizer->memberships()->where('user_id', $second->id)->update(['listed' => false]);

    $this->actingAs($stranger)->get("/users/{$second->id}")
        ->assertInertia(fn (Assert $page) => $page->has('organizers', 0));
});

test('a spokesperson can post a leaflet from the organizer; it reaches its followers and every spokesperson', function () {
    Notification::fake();
    ['main' => $main, 'second' => $second, 'follower' => $follower, 'stranger' => $stranger, 'organizer' => $organizer] = organizerCast();
    $friendOfWriter = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $second->id, 'addressee_id' => $friendOfWriter->id]);

    $this->actingAs($second)->get('/leaflets')
        ->assertInertia(fn (Assert $page) => $page->has('organizers', 1)->where('organizers.0.id', $organizer->id));

    $this->actingAs($second)->post('/leaflets', ['body' => 'Quiz on Thursday', 'visibility' => 'friends_and_followers', 'organizer_id' => $organizer->id])
        ->assertRedirect('/leaflets');
    $leaflet = Leaflet::query()->where('organizer_id', $organizer->id)->firstOrFail();
    expect($leaflet->user_id)->toBe($second->id);

    // An organizer has no friends, and no outsider may post as it.
    $this->actingAs($second)->post('/leaflets', ['body' => 'x', 'visibility' => 'friends', 'organizer_id' => $organizer->id])->assertInvalid(['visibility']);
    $this->actingAs($stranger)->post('/leaflets', ['body' => 'x', 'visibility' => 'official', 'organizer_id' => $organizer->id])->assertForbidden();

    // Its followers and spokespersons read it; the writer's own friends do not.
    foreach ([$main, $follower] as $reader) {
        $this->actingAs($reader)->get('/leaflets')->assertInertia(fn (Assert $page) => $page
            ->where('leaflets.0.id', $leaflet->id)
            ->where('leaflets.0.organizer.id', $organizer->id)
            ->where('leaflets.0.owner.id', $second->id));
    }
    foreach ([$friendOfWriter, $stranger] as $reader) {
        $this->actingAs($reader)->get('/leaflets')->assertInertia(fn (Assert $page) => $page->has('leaflets', 0));
        $this->actingAs($reader)->get("/leaflets/{$leaflet->id}")->assertForbidden();
    }

    // Every spokesperson may edit and delete it, and hears about responses.
    $this->actingAs($main)->get("/leaflets/{$leaflet->id}/edit")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('leaflet.organizer.id', $organizer->id));
    $this->actingAs($follower)->post("/leaflets/{$leaflet->id}/responses", ['body' => 'We will be there'])->assertRedirect();
    Notification::assertSentTo([$main, $second], NewLeafletResponse::class);
    Notification::assertNotSentTo($follower, NewLeafletResponse::class);
    $this->actingAs($main)->delete("/leaflets/{$leaflet->id}")->assertRedirect();
    $this->assertDatabaseMissing('leaflets', ['id' => $leaflet->id]);
});
