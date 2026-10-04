<?php

use App\Models\Event;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-18 12:00:00');
});

test('the month view returns every event on the grid of whole weeks, counting days in Oslo', function () {
    $user = User::factory()->create();
    // September 2026 starts on a Tuesday; the grid runs Mon 31 Aug – Sun 4 Oct, and Oslo is UTC+2.
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Too early', 'starts_at' => '2026-08-30 21:30:00']);
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Grid start', 'starts_at' => '2026-08-30 22:30:00']);
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Mid month', 'starts_at' => '2026-09-15 10:00:00']);
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Grid end', 'starts_at' => '2026-10-04 21:30:00']);
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Too late', 'starts_at' => '2026-10-04 22:30:00']);

    $this->actingAs($user)->get('/events/calendar')
        ->assertInertia(fn ($page) => $page
            ->component('events/Calendar')
            ->where('calendar.view', 'month')
            ->where('calendar.date', '2026-09-18')
            ->where('calendar.today', '2026-09-18')
            ->has('events', 3)
            ->where('events.0.name', 'Grid start')
            ->where('events.2.name', 'Grid end'));
});

test('an event shows on every day it runs', function () {
    $user = User::factory()->create();
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Cabin weekend', 'starts_at' => '2026-09-11 16:00:00', 'ends_at' => '2026-09-13 12:00:00']);

    $this->actingAs($user)->get('/events/calendar?view=day&date=2026-09-12')
        ->assertInertia(fn ($page) => $page->has('events', 1)->where('events.0.name', 'Cabin weekend'));

    $this->actingAs($user)->get('/events/calendar?view=day&date=2026-09-14')
        ->assertInertia(fn ($page) => $page->has('events', 0));
});

test('today is the date in the display time zone', function () {
    // 00:30 on Saturday 19 September in Oslo.
    Carbon::setTestNow('2026-09-18 22:30:00');

    $this->actingAs(User::factory()->create())->get('/events/calendar')
        ->assertInertia(fn ($page) => $page
            ->where('calendar.today', '2026-09-19')
            ->where('calendar.date', '2026-09-19'));
});

test('the week and day views narrow the range accordingly', function () {
    $user = User::factory()->create();
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Monday', 'starts_at' => '2026-09-14 09:00:00']);
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Friday', 'starts_at' => '2026-09-18 18:00:00']);
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Next week', 'starts_at' => '2026-09-23 18:00:00']);

    $this->actingAs($user)->get('/events/calendar?view=week&date=2026-09-16')
        ->assertInertia(fn ($page) => $page
            ->where('calendar.view', 'week')
            ->has('events', 2)
            ->where('events.0.name', 'Monday')
            ->where('events.1.name', 'Friday'));

    $this->actingAs($user)->get('/events/calendar?view=day&date=2026-09-18')
        ->assertInertia(fn ($page) => $page
            ->where('calendar.view', 'day')
            ->has('events', 1)
            ->where('events.0.name', 'Friday'));
});

test('an unknown view or a malformed date falls back to this month', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/events/calendar?view=year&date=soon')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('calendar.view', 'month')
            ->where('calendar.date', '2026-09-18'));
});

test('an impossible date falls back to today rather than erroring', function () {
    $this->actingAs(User::factory()->create())->get('/events/calendar?date=2026-13-01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('calendar.date', '2026-09-18'));
});

test('the calendar honours the same category and organizer filters as the list', function () {
    $user = User::factory()->create();
    $music = Tag::factory()->create(['name' => 'music']);
    $concert = Event::factory()->create(['user_id' => $user->id, 'name' => 'Concert', 'starts_at' => '2026-09-20 20:00:00']);
    $concert->tags()->attach($music);
    Event::factory()->create(['user_id' => $user->id, 'name' => 'Untagged', 'starts_at' => '2026-09-21 20:00:00']);

    $this->actingAs($user)->get('/events/calendar?tags[]=music')
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Concert')
            ->where('filters.tags', ['music']));
});

test('the calendar only shows events the viewer may see', function () {
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    Event::factory()->public()->create(['user_id' => $stranger->id, 'starts_at' => '2026-09-20 20:00:00']);

    $this->actingAs($viewer)->get('/events/calendar')
        ->assertInertia(fn ($page) => $page->has('events', 0));
});
