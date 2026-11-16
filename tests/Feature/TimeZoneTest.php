<?php

use App\Models\Event;
use App\Models\User;
use App\Services\TimeZones;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The fields the event form sends.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function eventFormFields(array $overrides = []): array
{
    return [
        'name' => 'Pub quiz',
        'description' => 'Bring a pen.',
        'starts_at' => '2027-03-13T19:00:00Z',
        'ends_at' => null,
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        ...$overrides,
    ];
}

test('the device reports its time zone, and pages are written in it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn (Assert $page) => $page->where('timezone', 'Europe/Oslo'));

    $this->actingAs($user)->putJson('/time-zone', ['timezone' => 'America/New_York'])->assertNoContent();

    expect($user->refresh()->timezone)->toBe('America/New_York');

    $this->actingAs($user)->get('/events')
        ->assertInertia(fn (Assert $page) => $page->where('timezone', 'America/New_York'));
});

test('a time zone under its older name is accepted, and nonsense is not', function () {
    $user = User::factory()->create();

    // What some browsers still call Asia/Kolkata.
    $this->actingAs($user)->putJson('/time-zone', ['timezone' => 'Asia/Calcutta'])->assertNoContent();
    $this->actingAs($user)->putJson('/time-zone', ['timezone' => 'Mars/Olympus_Mons'])->assertUnprocessable();
    $this->actingAs($user)->putJson('/time-zone', [])->assertUnprocessable();

    expect($user->refresh()->timezone)->toBe('Asia/Calcutta');
});

test('reporting a time zone needs a signed-in user', function () {
    $this->putJson('/time-zone', ['timezone' => 'Europe/London'])->assertUnauthorized();
    $this->getJson('/time-zone/lookup?latitude=51.5&longitude=-0.12')->assertUnauthorized();
});

test('the time zone at a pin', function (?float $latitude, ?float $longitude, ?string $countryCode, ?string $expected) {
    expect(TimeZones::forPlace($latitude, $longitude, $countryCode))->toBe($expected);
})->with([
    'a country with one zone' => [59.91, 10.75, 'NO', 'Europe/Oslo'],
    'the country alone, when it has one zone' => [null, null, 'se', 'Europe/Stockholm'],
    'the east of a country with several' => [40.71, -74.0, 'US', 'America/New_York'],
    'the west of a country with several' => [34.05, -118.24, 'US', 'America/Los_Angeles'],
    'islands on another clock than the mainland' => [28.1, -15.4, 'ES', 'Atlantic/Canary'],
    'a pin without a country' => [48.85, 2.35, null, 'Europe/Paris'],
    'a country with several zones and no pin' => [null, null, 'US', null],
    'nothing to go by' => [null, null, null, null],
    'a country code that is not one' => [null, null, 'Norway', null],
]);

test('the form can ask for the time zone at a pin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/time-zone/lookup?latitude=51.5&longitude=-0.12&country_code=GB')
        ->assertOk()
        ->assertExactJson(['timezone' => 'Europe/London']);

    $this->actingAs($user)->getJson('/time-zone/lookup?latitude=151&longitude=0')->assertUnprocessable();
});

test('a new event keeps the time zone the form worked in', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/events', eventFormFields(['timezone' => 'Europe/London']))->assertRedirect();

    expect(Event::query()->sole()->timezone)->toBe('Europe/London');
});

test('without one from the form, a new event takes the zone at its pin', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Oslo']);

    $this->actingAs($user)->post('/events', eventFormFields([
        'latitude' => 40.71, 'longitude' => -74.0, 'country_code' => 'US',
    ]))->assertRedirect();

    expect(Event::query()->sole()->timezone)->toBe('America/New_York');
});

test('an event without a pin is in its host\'s time zone', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

    $this->actingAs($user)->post('/events', eventFormFields())->assertRedirect();

    expect(Event::query()->sole()->timezone)->toBe('Asia/Tokyo');
});

test('an event with a time zone that is not one is refused', function () {
    $this->actingAs(User::factory()->create())
        ->post('/events', eventFormFields(['timezone' => 'Nowhere/Special']))
        ->assertSessionHasErrors('timezone');
});

test('editing keeps the event\'s time zone unless the form or the pin says otherwise', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
    $event = Event::factory()->create(['user_id' => $user->id, 'timezone' => 'Europe/London']);

    $this->actingAs($user)->put("/events/{$event->id}", eventFormFields())->assertRedirect();
    expect($event->refresh()->timezone)->toBe('Europe/London');

    $this->actingAs($user)->put("/events/{$event->id}", eventFormFields([
        'latitude' => 59.91, 'longitude' => 10.75, 'country_code' => 'NO',
    ]))->assertRedirect();
    expect($event->refresh()->timezone)->toBe('Europe/Oslo');

    $this->actingAs($user)->put("/events/{$event->id}", eventFormFields(['timezone' => 'America/Chicago']))->assertRedirect();
    expect($event->refresh()->timezone)->toBe('America/Chicago');
});

test('an event is sent with its own time zone, the app\'s for one from before', function () {
    $user = User::factory()->create();
    $abroad = Event::factory()->create(['user_id' => $user->id, 'timezone' => 'Europe/London']);
    $old = Event::factory()->create(['user_id' => $user->id, 'timezone' => null]);

    $this->actingAs($user)->get("/events/{$abroad->id}")
        ->assertInertia(fn (Assert $page) => $page->where('event.timezone', 'Europe/London'));
    $this->actingAs($user)->get("/events/{$old->id}")
        ->assertInertia(fn (Assert $page) => $page->where('event.timezone', 'Europe/Oslo'));
    $this->actingAs($user)->get("/events/{$abroad->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->where('event.timezone', 'Europe/London'));
});

test('today is the viewer\'s own day', function () {
    // 03:00 UTC on 2 June: already 2 June in Oslo, still 1 June in Los Angeles.
    Carbon::setTestNow('2026-06-02 03:00:00');

    $inOslo = User::factory()->create(['timezone' => 'Europe/Oslo']);
    $inLosAngeles = User::factory()->create(['timezone' => 'America/Los_Angeles']);

    // 12:00 UTC on 1 June: yesterday's in Oslo, earlier today in Los Angeles.
    foreach ([$inOslo, $inLosAngeles] as $host) {
        Event::factory()->create(['user_id' => $host->id, 'name' => 'Morning swim', 'starts_at' => '2026-06-01 12:00:00']);
    }

    $this->actingAs($inOslo)->get('/events')
        ->assertInertia(fn (Assert $page) => $page->has('events', 0));
    $this->actingAs($inLosAngeles)->get('/events')
        ->assertInertia(fn (Assert $page) => $page->has('events', 1)->where('events.0.name', 'Morning swim'));
});

test('an event without an end is over when its day ends where it happens', function () {
    // 20:00 in Tokyo on 1 June.
    $event = Event::factory()->create(['starts_at' => '2026-06-01 11:00:00', 'ends_at' => null, 'timezone' => 'Asia/Tokyo']);

    // 23:00 in Tokyo: still the same evening.
    Carbon::setTestNow('2026-06-01 14:00:00');
    expect($event->isOver())->toBeFalse();

    // 01:00 the next day in Tokyo, though only 18:00 on 1 June in Oslo.
    Carbon::setTestNow('2026-06-01 16:00:00');
    expect($event->isOver())->toBeTrue();
});
