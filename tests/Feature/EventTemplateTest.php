<?php

use App\Enums\EventTimeZoneMode;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Organizer;
use App\Models\Tag;
use App\Models\User;
use App\Services\EventStartingPoints;
use App\Services\ImageProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function eventTemplatePayload(array $overrides = []): array
{
    return [
        'name' => 'Quiz night',
        'place' => 'The Crown',
        'description' => 'Bring a team.',
        'visibility' => 'public',
        'allow_invitee_invites' => true,
        'allow_invitee_comments' => true,
        ...$overrides,
    ];
}

/**
 * An event with a cover whose file is really on the (faked) private disk.
 */
function eventWithCover(User $host, array $attributes = []): Event
{
    $event = Event::factory()->create(['user_id' => $host->id, ...$attributes]);
    $path = "events/{$event->id}/cover.jpg";
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($path, 'cover-bytes');
    $event->images()->create(['path' => $path, 'position' => 0, 'width' => 800, 'height' => 1200, 'fit' => 'contain']);

    return $event;
}

test('a host makes a template of an event: everything but the day, with a cover of its own', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $host = User::factory()->create();
    $games = Tag::query()->where('slug', 'games')->firstOrFail();
    $event = eventWithCover($host, [
        'name' => 'Board games',
        'place' => 'My flat',
        'city' => 'Oslo',
        'country' => 'Norway',
        'country_code' => 'no',
        'latitude' => 59.91,
        'longitude' => 10.75,
        'description' => 'Bring snacks.',
        'timezone' => 'Europe/Oslo',
        // 19:00–23:30 in Oslo in winter.
        'starts_at' => '2026-12-03 18:00:00',
        'ends_at' => '2026-12-03 22:30:00',
        'visibility' => EventVisibility::Searchable,
        'searchable_paid_at' => now(),
        'allow_invitee_invites' => true,
        'allow_invitee_comments' => false,
    ]);
    $event->tags()->sync([$games->id, $games->parent_id]);

    $response = $this->actingAs($host)->post("/events/{$event->id}/template");

    $template = EventTemplate::query()->sole();
    $response->assertRedirect("/events/templates/{$template->id}/edit");

    expect($template->user_id)->toBe($host->id)
        ->and($template->organizer_id)->toBeNull()
        ->and($template->only(['name', 'place', 'city', 'country', 'country_code', 'description', 'timezone']))->toBe([
            'name' => 'Board games', 'place' => 'My flat', 'city' => 'Oslo', 'country' => 'Norway', 'country_code' => 'no',
            'description' => 'Bring snacks.', 'timezone' => 'Europe/Oslo',
        ])
        ->and($template->latitude)->toBe(59.91)
        ->and($template->starts_time)->toBe('19:00')
        ->and($template->ends_time)->toBe('23:30')
        ->and($template->ends_days_later)->toBe(0)
        // Being searchable is bought per event.
        ->and($template->visibility)->toBe(EventVisibility::Public)
        ->and($template->allow_invitee_invites)->toBeTrue()
        ->and($template->allow_invitee_comments)->toBeFalse()
        ->and($template->tags()->pluck('tags.id')->sort()->values()->all())->toBe(collect([$games->id, $games->parent_id])->sort()->values()->all())
        ->and($template->image_fit)->toBe('contain')
        ->and($template->image_width)->toBe(800);

    // The cover is a copy: the template keeps it when the event goes.
    expect($template->image_path)->not->toBe($event->images->first()->path);
    $this->actingAs($host)->delete("/events/{$event->id}")->assertRedirect();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($template->image_path);
    expect(Storage::disk(ImageProcessor::PRIVATE_DISK)->get($template->image_path))->toBe('cover-bytes');
});

test('an event that ends after midnight, or days later, keeps how many days later it ends', function (string $endsAt, string $endsTime, int $daysLater) {
    $event = Event::factory()->create(['timezone' => 'Europe/Oslo', 'starts_at' => '2026-12-04 19:00:00', 'ends_at' => $endsAt]);

    expect(app(EventStartingPoints::class)->clockTimesOf($event))->toBe([
        'starts_time' => '20:00', 'ends_time' => $endsTime, 'ends_days_later' => $daysLater,
    ]);
})->with([
    'the same evening' => ['2026-12-04 22:00:00', '23:00', 0],
    'after midnight' => ['2026-12-05 01:00:00', '02:00', 1],
    'two days later' => ['2026-12-06 13:00:00', '14:00', 2],
]);

test('the night the clocks go forward still counts as one day', function () {
    // Oslo: 28–29 March 2026, 23:00 to 08:00 the next morning.
    $event = Event::factory()->create(['timezone' => 'Europe/Oslo', 'starts_at' => '2026-03-28 22:00:00', 'ends_at' => '2026-03-29 06:00:00']);

    expect(app(EventStartingPoints::class)->clockTimesOf($event))->toBe([
        'starts_time' => '23:00', 'ends_time' => '08:00', 'ends_days_later' => 1,
    ]);
});

test('an event without an end gives a template without one', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id, 'timezone' => 'Europe/Oslo', 'starts_at' => '2026-12-04 19:00:00', 'ends_at' => null]);

    $this->actingAs($host)->post("/events/{$event->id}/template")->assertRedirect();

    $template = EventTemplate::query()->sole();
    expect($template->starts_time)->toBe('20:00')
        ->and($template->ends_time)->toBeNull()
        ->and($template->image_path)->toBeNull();
});

test('only a host makes a template of an event', function () {
    $event = Event::factory()->public()->create();

    $this->actingAs(User::factory()->create())->post("/events/{$event->id}/template")->assertForbidden();

    expect(EventTemplate::query()->count())->toBe(0);
});

test('a template can be made from scratch with only a name', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $user = User::factory()->create(['timezone' => 'Europe/Oslo']);
    $games = Tag::query()->where('slug', 'games')->firstOrFail();

    $this->actingAs($user)->post('/events/templates', ['name' => 'Dinner', 'visibility' => 'private', 'allow_invitee_invites' => false, 'allow_invitee_comments' => true])
        ->assertRedirect('/events/templates');

    $bare = EventTemplate::query()->where('name', 'Dinner')->sole();
    expect($bare->description)->toBe('')
        ->and($bare->starts_time)->toBeNull()
        ->and($bare->timezone)->toBe('Europe/Oslo')
        ->and($bare->timezone_mode)->toBe(EventTimeZoneMode::Automatic);

    $this->actingAs($user)->post('/events/templates', eventTemplatePayload([
        'starts_time' => '19:00',
        'ends_time' => '21:30',
        'ends_days_later' => 0,
        'tag_ids' => [$games->id],
        'image' => UploadedFile::fake()->image('poster.jpg', 600, 900),
        'image_fit' => 'contain',
    ]))->assertRedirect('/events/templates');

    $full = EventTemplate::query()->where('name', 'Quiz night')->sole();
    expect($full->starts_time)->toBe('19:00')
        ->and($full->ends_time)->toBe('21:30')
        ->and($full->ends_days_later)->toBe(0)
        ->and($full->visibility)->toBe(EventVisibility::Public)
        // The subcategory brings its parent along, as on an event.
        ->and($full->tags()->pluck('slug')->sort()->values()->all())->toBe(['games', 'social'])
        ->and($full->image_fit)->toBe('contain')
        ->and($full->image_width)->toBe(600);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($full->image_path);
});

test('a template needs a name, and an end time needs a start time', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/events/templates', eventTemplatePayload(['name' => '']))->assertInvalid(['name']);
    $this->actingAs($user)->post('/events/templates', eventTemplatePayload(['ends_time' => '21:00']))->assertInvalid(['starts_time']);
    $this->actingAs($user)->post('/events/templates', eventTemplatePayload(['starts_time' => '7 pm']))->assertInvalid(['starts_time']);
});

test('an end time that is not later on the clock belongs to the next day', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/events/templates', eventTemplatePayload(['starts_time' => '22:00', 'ends_time' => '02:00', 'ends_days_later' => 0]))
        ->assertRedirect();

    expect(EventTemplate::query()->sole()->ends_days_later)->toBe(1);
});

test('a template can be edited: its times cleared, its cover replaced or removed', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $user = User::factory()->create();
    $this->actingAs($user)->post('/events/templates', eventTemplatePayload([
        'starts_time' => '19:00', 'ends_time' => '21:00',
        'image' => UploadedFile::fake()->image('photo.jpg', 1200, 800),
    ]));
    $template = EventTemplate::query()->sole();
    $firstCover = $template->image_path;

    // Times that differ every time are simply left out.
    $this->actingAs($user)->put("/events/templates/{$template->id}", eventTemplatePayload([
        'name' => 'Quiz', 'starts_time' => '', 'ends_time' => '', 'image_fit' => 'contain',
    ]))->assertRedirect('/events/templates');

    $template->refresh();
    expect($template->name)->toBe('Quiz')
        ->and($template->starts_time)->toBeNull()
        ->and($template->ends_time)->toBeNull()
        ->and($template->image_path)->toBe($firstCover)
        ->and($template->image_fit)->toBe('contain');

    $this->actingAs($user)->put("/events/templates/{$template->id}", eventTemplatePayload([
        'image' => UploadedFile::fake()->image('new.jpg', 900, 600),
    ]))->assertRedirect();

    $template->refresh();
    expect($template->image_path)->not->toBe($firstCover);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($firstCover);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($template->image_path);
    $secondCover = $template->image_path;

    $this->actingAs($user)->put("/events/templates/{$template->id}", eventTemplatePayload(['remove_image' => '1']))->assertRedirect();

    expect($template->refresh()->image_path)->toBeNull();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($secondCover);
});

test('deleting a template deletes its cover', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $user = User::factory()->create();
    $this->actingAs($user)->post('/events/templates', eventTemplatePayload(['image' => UploadedFile::fake()->image('photo.jpg')]));
    $template = EventTemplate::query()->sole();

    $this->actingAs($user)->delete("/events/templates/{$template->id}")->assertRedirect('/events/templates');

    expect(EventTemplate::query()->count())->toBe(0);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($template->image_path);
});

test('a template is private to its maker: no page, no change, no cover for anyone else', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $owner = User::factory()->create();
    $this->actingAs($owner)->post('/events/templates', eventTemplatePayload(['image' => UploadedFile::fake()->image('photo.jpg')]));
    $template = EventTemplate::query()->sole();
    $coverUrl = "/events/templates/{$template->id}/cover/".basename($template->image_path);
    $stranger = User::factory()->create();

    $this->actingAs($owner)->get($coverUrl)->assertSuccessful();
    $this->actingAs($owner)->get("/events/templates/{$template->id}/cover/other.jpg")->assertNotFound();

    $this->actingAs($stranger)->get($coverUrl)->assertForbidden();
    $this->actingAs($stranger)->get("/events/templates/{$template->id}/edit")->assertForbidden();
    $this->actingAs($stranger)->put("/events/templates/{$template->id}", eventTemplatePayload())->assertForbidden();
    $this->actingAs($stranger)->delete("/events/templates/{$template->id}")->assertForbidden();
    $this->actingAs($stranger)->get("/events/create?template={$template->id}")->assertForbidden();
    $this->actingAs($stranger)->get('/events/templates')
        ->assertInertia(fn (Assert $page) => $page->component('events/Templates')->has('templates', 0));

    auth()->logout();
    $this->get($coverUrl)->assertRedirect('/login');
});

test("an organizer's template is shared by its spokespersons, and by no one else", function () {
    [$main, $spokesperson, $stranger] = User::factory()->count(3)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($spokesperson)->create();
    $other = Organizer::factory()->runBy($stranger)->create();

    $this->actingAs($spokesperson)->post('/events/templates', eventTemplatePayload(['organizer_id' => $organizer->id]))->assertRedirect();
    $template = EventTemplate::query()->sole();
    expect($template->organizer_id)->toBe($organizer->id)->and($template->user_id)->toBe($spokesperson->id);

    // Not for an organizer you do not speak for.
    $this->actingAs($spokesperson)->post('/events/templates', eventTemplatePayload(['organizer_id' => $other->id]))->assertForbidden();

    $this->actingAs($main)->get('/events/templates')
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1)
            ->where('templates.0.id', $template->id)
            ->where('templates.0.organizer.id', $organizer->id));
    $this->actingAs($main)->put("/events/templates/{$template->id}", eventTemplatePayload(['name' => 'Quiz at ours']))->assertRedirect();
    expect($template->refresh()->name)->toBe('Quiz at ours');

    $this->actingAs($stranger)->get("/events/templates/{$template->id}/edit")->assertForbidden();
});

test("a template of an organizer's event belongs to the organizer", function () {
    [$main, $spokesperson] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($spokesperson)->create();
    $event = Event::factory()->public()->create(['user_id' => $main->id, 'organizer_id' => $organizer->id]);

    $this->actingAs($spokesperson)->post("/events/{$event->id}/template")->assertRedirect();

    $template = EventTemplate::query()->sole();
    expect($template->organizer_id)->toBe($organizer->id)->and($template->user_id)->toBe($spokesperson->id);
});

test('a spokesperson who leaves hands the templates they made for the organizer to the main spokesperson', function () {
    [$main, $spokesperson] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($spokesperson)->create();
    $shared = EventTemplate::factory()->forOrganizer($organizer)->create(['user_id' => $spokesperson->id]);
    $own = EventTemplate::factory()->create(['user_id' => $spokesperson->id]);

    $this->actingAs($spokesperson)->delete("/organizers/{$organizer->id}/spokespersons/{$spokesperson->id}")->assertRedirect();

    expect($shared->refresh()->user_id)->toBe($main->id)
        ->and($shared->isUsableBy($spokesperson->fresh()))->toBeFalse()
        ->and($own->refresh()->user_id)->toBe($spokesperson->id);
});

test('the templates page lists the templates and the events one hosts, newest first', function () {
    $host = User::factory()->create();
    EventTemplate::factory()->withTimes('19:00', '21:00')->create(['user_id' => $host->id, 'name' => 'B quiz']);
    EventTemplate::factory()->create(['user_id' => $host->id, 'name' => 'A dinner']);
    EventTemplate::factory()->create();
    $old = Event::factory()->create(['user_id' => $host->id, 'starts_at' => now()->subMonth()]);
    $coming = Event::factory()->create(['user_id' => $host->id, 'starts_at' => now()->addWeek()]);
    Event::factory()->create(['user_id' => $host->id, 'is_deleted' => true]);
    Event::factory()->public()->create();

    $this->actingAs($host)->get('/events/templates')
        ->assertInertia(fn (Assert $page) => $page
            ->component('events/Templates')
            ->has('templates', 2)
            ->where('templates.0.name', 'A dinner')
            ->where('templates.1.name', 'B quiz')
            ->where('templates.1.starts_time', '19:00')
            ->where('templates.1.ends_time', '21:00')
            ->has('events', 2)
            ->where('events.0.id', $coming->id)
            ->where('events.1.id', $old->id)
            ->where('pagination.total', 2));
});

test('a new event starts from a template: everything filled in but the day', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $user = User::factory()->create();
    $games = Tag::query()->where('slug', 'games')->firstOrFail();
    $this->actingAs($user)->post('/events/templates', eventTemplatePayload([
        'latitude' => 59.91, 'longitude' => 10.75, 'city' => 'Oslo', 'country' => 'Norway', 'country_code' => 'NO',
        'timezone' => 'Europe/Oslo',
        'starts_time' => '22:00', 'ends_time' => '02:00',
        'tag_ids' => [$games->id],
        'image' => UploadedFile::fake()->image('photo.jpg', 1200, 800),
    ]));
    $template = EventTemplate::query()->sole();

    $this->actingAs($user)->get("/events/create?template={$template->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('events/Create')
            ->has('templates', 1)
            ->where('templates.0.id', $template->id)
            ->where('startFrom.source', ['type' => 'template', 'id' => $template->id, 'name' => 'Quiz night'])
            ->where('startFrom.cover_from', "template:{$template->id}")
            ->where('startFrom.starts_time', '22:00')
            ->where('startFrom.ends_time', '02:00')
            ->where('startFrom.ends_days_later', 1)
            ->where('startFrom.values.name', 'Quiz night')
            ->where('startFrom.values.place', 'The Crown')
            ->where('startFrom.values.city', 'Oslo')
            ->where('startFrom.values.timezone', 'Europe/Oslo')
            ->where('startFrom.values.visibility', 'public')
            ->where('startFrom.values.allow_invitee_invites', true)
            ->where('startFrom.values.tag_ids', $template->tags()->pluck('tags.id')->all())
            ->where('startFrom.values.image.url', url("/events/templates/{$template->id}/cover/".basename($template->image_path))));
});

test('without a starting point the new-event form is blank', function () {
    $this->actingAs(User::factory()->create())->get('/events/create')
        ->assertInertia(fn (Assert $page) => $page->where('startFrom', null)->has('templates', 0));
});

test('a template without a pin does not carry its zone: the times are on the clock of whoever uses it', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Oslo']);
    $template = EventTemplate::factory()->withTimes()->create(['user_id' => $user->id, 'timezone' => 'Europe/Oslo']);

    $this->actingAs($user)->get("/events/create?template={$template->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('startFrom.values.timezone', null)
            ->where('startFrom.values.timezone_mode', 'auto'));
});

test('a new event starts from an earlier one its host picks, and from no one else\'s', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $host = User::factory()->create();
    $event = eventWithCover($host, ['name' => 'Summer party', 'timezone' => 'Europe/Oslo', 'starts_at' => '2026-07-04 16:00:00', 'ends_at' => '2026-07-04 21:00:00']);

    $this->actingAs($host)->get("/events/create?from={$event->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('startFrom.source', ['type' => 'event', 'id' => $event->id, 'name' => 'Summer party'])
            ->where('startFrom.cover_from', "event:{$event->id}")
            ->where('startFrom.starts_time', '18:00')
            ->where('startFrom.ends_time', '23:00')
            ->where('startFrom.ends_days_later', 0)
            ->where('startFrom.values.name', 'Summer party')
            ->where('startFrom.values.image.id', $event->images->first()->id));

    $this->actingAs(User::factory()->create())->get("/events/create?from={$event->id}")->assertForbidden();
});

test('an event made from a template gets a copy of its cover', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $user = User::factory()->create();
    $this->actingAs($user)->post('/events/templates', eventTemplatePayload([
        'image' => UploadedFile::fake()->image('poster.jpg', 600, 900),
        'image_fit' => 'contain',
    ]));
    $template = EventTemplate::query()->sole();

    $this->actingAs($user)->post('/events', [
        'name' => 'Quiz night',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'cover_from' => "template:{$template->id}",
        'image_fit' => 'cover',
    ])->assertRedirect();

    $image = Event::query()->sole()->images()->sole();
    expect($image->path)->not->toBe($template->image_path)
        ->and($image->path)->toStartWith('events/')
        ->and($image->width)->toBe(600)
        ->and($image->height)->toBe(900)
        // The form's choice wins over the template's.
        ->and($image->fit)->toBe('cover');
    $disk = Storage::disk(ImageProcessor::PRIVATE_DISK);
    expect($disk->get($image->path))->toBe($disk->get($template->image_path));

    // Deleting the template leaves the event's cover alone.
    $template->delete();
    $disk->assertExists($image->path);
});

test('an event made from an earlier one gets a copy of its cover, unless another is uploaded', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $host = User::factory()->create();
    $earlier = eventWithCover($host);
    $payload = [
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'cover_from' => "event:{$earlier->id}",
    ];

    $this->actingAs($host)->post('/events', ['name' => 'Copied', ...$payload])->assertRedirect();

    $copied = Event::query()->where('name', 'Copied')->sole()->images()->sole();
    expect($copied->path)->not->toBe($earlier->images->first()->path)
        ->and($copied->fit)->toBe('contain')
        ->and(Storage::disk(ImageProcessor::PRIVATE_DISK)->get($copied->path))->toBe('cover-bytes');

    $this->actingAs($host)->post('/events', ['name' => 'Uploaded', ...$payload, 'image' => UploadedFile::fake()->image('new.jpg', 900, 600)])->assertRedirect();

    $uploaded = Event::query()->where('name', 'Uploaded')->sole()->images;
    expect($uploaded)->toHaveCount(1)
        ->and($uploaded->first()->width)->toBe(900);
});

test('nobody copies a cover they may not see', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $owner = User::factory()->create();
    $event = eventWithCover($owner, ['visibility' => EventVisibility::Public]);
    $this->actingAs($owner)->post('/events/templates', eventTemplatePayload(['image' => UploadedFile::fake()->image('photo.jpg')]));
    $template = EventTemplate::query()->sole();
    $stranger = User::factory()->create();
    $payload = [
        'name' => 'Mine now',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
    ];

    $this->actingAs($stranger)->post('/events', [...$payload, 'cover_from' => "template:{$template->id}"])->assertForbidden();
    $this->actingAs($stranger)->post('/events', [...$payload, 'cover_from' => "event:{$event->id}"])->assertForbidden();
    $this->actingAs($stranger)->post('/events', [...$payload, 'cover_from' => '../../etc/passwd'])->assertInvalid(['cover_from']);
});

test('a starting point that is gone leaves the new event without a cover', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/events', [
        'name' => 'No cover',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'cover_from' => 'template:999',
    ])->assertRedirect();

    expect(Event::query()->sole()->images)->toHaveCount(0);
});

test("deleting an account or an organizer deletes its templates' covers", function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $user = User::factory()->create();
    $organizer = Organizer::factory()->runBy($user)->create();
    $this->actingAs($user)->post('/events/templates', eventTemplatePayload(['name' => 'Own', 'image' => UploadedFile::fake()->image('a.jpg')]));
    $this->actingAs($user)->post('/events/templates', eventTemplatePayload(['name' => 'Shared', 'organizer_id' => $organizer->id, 'image' => UploadedFile::fake()->image('b.jpg')]));
    $own = EventTemplate::query()->where('name', 'Own')->sole();
    $shared = EventTemplate::query()->where('name', 'Shared')->sole();

    $organizer->deleteUploadedFiles();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($shared->image_path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($own->image_path);

    $user->deleteUploadedFiles();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($own->image_path);
});

test('the clock times of an event read back as the same instants on the same day', function () {
    // What the form does with a template's times, checked on the server's
    // side of the same arithmetic.
    $event = Event::factory()->create(['timezone' => 'America/New_York', 'starts_at' => '2026-07-04 23:30:00', 'ends_at' => '2026-07-05 05:00:00']);
    $times = app(EventStartingPoints::class)->clockTimesOf($event);

    $start = CarbonImmutable::parse("2026-07-04 {$times['starts_time']}", 'America/New_York');
    $end = CarbonImmutable::parse("2026-07-04 {$times['ends_time']}", 'America/New_York')->addDays($times['ends_days_later']);

    expect($start->utc()->toDateTimeString())->toBe('2026-07-04 23:30:00')
        ->and($end->utc()->toDateTimeString())->toBe('2026-07-05 05:00:00');
});
