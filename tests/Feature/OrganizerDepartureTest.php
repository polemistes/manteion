<?php

use App\Enums\SpokespersonRole;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Leaflet;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\OrganizerHandedOver;
use App\Services\ActivityFeed;
use App\Services\ImageProcessor;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * What one person made for an organizer: an event with a cover on the
 * (faked) private disk, a leaflet and a template.
 *
 * @return array{event: Event, leaflet: Leaflet, template: EventTemplate, cover: string}
 */
function workFor(Organizer $organizer, User $maker): array
{
    $event = Event::factory()->public()->create(['user_id' => $maker->id, 'organizer_id' => $organizer->id]);
    $cover = "events/{$event->id}/cover.jpg";
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($cover, 'cover-bytes');
    $event->images()->create(['path' => $cover, 'position' => 0]);

    return [
        'event' => $event,
        'leaflet' => Leaflet::factory()->official()->create(['user_id' => $maker->id, 'organizer_id' => $organizer->id]),
        'template' => EventTemplate::factory()->forOrganizer($organizer)->create(['user_id' => $maker->id]),
        'cover' => $cover,
    ];
}

function spokespersonUrl(Organizer $organizer, User $user): string
{
    return "/organizers/{$organizer->id}/spokespersons/{$user->id}";
}

test('a spokesperson who steps down hands what they made to the main spokesperson, who is told', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    Notification::fake();
    [$main, $leaver] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($leaver)->create();
    $work = workFor($organizer, $leaver);
    $own = Event::factory()->create(['user_id' => $leaver->id]);

    $this->actingAs($leaver)->delete(spokespersonUrl($organizer, $leaver))->assertRedirect();

    expect($organizer->membershipOf($leaver))->toBeNull()
        ->and($work['event']->fresh()->user_id)->toBe($main->id)
        ->and($work['leaflet']->fresh()->user_id)->toBe($main->id)
        ->and($work['template']->fresh()->user_id)->toBe($main->id)
        // Their own events are no part of it.
        ->and($own->fresh()->user_id)->toBe($leaver->id);

    Notification::assertSentTo($main, OrganizerHandedOver::class, fn (OrganizerHandedOver $notice) => $notice->leaver->is($leaver)
        && $notice->madeMain === false
        && $notice->handed === ['events' => 1, 'leaflets' => 1, 'templates' => 1]);
});

test('a spokesperson who steps down may instead delete what they made for the organizer', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    Notification::fake();
    [$main, $leaver] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($leaver)->create();
    $work = workFor($organizer, $leaver);
    $mains = workFor($organizer, $main);
    $own = Event::factory()->create(['user_id' => $leaver->id]);

    $this->actingAs($leaver)->delete(spokespersonUrl($organizer, $leaver), ['hand_over' => false])->assertRedirect();

    expect($organizer->membershipOf($leaver))->toBeNull()
        ->and(Event::query()->find($work['event']->id))->toBeNull()
        ->and(Leaflet::query()->find($work['leaflet']->id))->toBeNull()
        ->and(EventTemplate::query()->find($work['template']->id))->toBeNull()
        // What others made for it, and what the leaver made for themself, stays.
        ->and($mains['event']->fresh())->not->toBeNull()
        ->and($mains['leaflet']->fresh())->not->toBeNull()
        ->and($own->fresh())->not->toBeNull();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($work['cover']);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($mains['cover']);

    Notification::assertNothingSent();
});

test('someone who made nothing steps down without a notice', function () {
    Notification::fake();
    [$main, $leaver] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($leaver)->create();

    $this->actingAs($leaver)->delete(spokespersonUrl($organizer, $leaver))->assertRedirect();

    expect($organizer->membershipOf($leaver))->toBeNull();
    Notification::assertNothingSent();
});

test('a main spokesperson steps down by naming who takes over, and that person receives everything', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    Notification::fake();
    [$main, $first, $second] = User::factory()->count(3)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($first)->withSpokesperson($second)->create();
    $work = workFor($organizer, $main);

    $this->actingAs($main)->delete(spokespersonUrl($organizer, $main), ['successor_id' => $second->id])->assertRedirect();

    expect($organizer->membershipOf($main))->toBeNull()
        ->and($organizer->isMainSpokesperson($second->fresh()))->toBeTrue()
        ->and($organizer->isMainSpokesperson($first->fresh()))->toBeFalse()
        ->and($work['event']->fresh()->user_id)->toBe($second->id)
        ->and($work['template']->fresh()->user_id)->toBe($second->id);

    Notification::assertSentTo($second, OrganizerHandedOver::class, fn (OrganizerHandedOver $notice) => $notice->madeMain
        && $notice->handed === ['events' => 1, 'leaflets' => 1, 'templates' => 1]);
    Notification::assertNotSentTo($first, OrganizerHandedOver::class);
});

test('a main spokesperson cannot step down without someone who can take over', function () {
    [$main, $member, $invited, $stranger] = User::factory()->count(4)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($member)->create();
    $organizer->memberships()->create(['user_id' => $invited->id, 'role' => SpokespersonRole::Spokesperson]);

    foreach ([[], ['successor_id' => $stranger->id], ['successor_id' => $invited->id], ['successor_id' => $main->id]] as $payload) {
        $this->actingAs($main)->delete(spokespersonUrl($organizer, $main), $payload)->assertInvalid(['role']);
    }

    expect($organizer->isMainSpokesperson($main->fresh()))->toBeTrue();

    // Alone, there is no one to name: the organizer is deleted instead.
    $alone = Organizer::factory()->runBy($main)->create();
    $this->actingAs($main)->delete(spokespersonUrl($alone, $main), ['successor_id' => $member->id])->assertInvalid(['role']);
    expect($alone->fresh())->not->toBeNull();
});

test('what a removed spokesperson made is handed to the main spokesperson, who is not told what they did themself', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    Notification::fake();
    [$main, $removed] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($removed)->create();
    $work = workFor($organizer, $removed);

    // The choice to delete is the leaver's alone.
    $this->actingAs($main)->delete(spokespersonUrl($organizer, $removed), ['hand_over' => false])->assertRedirect();

    expect($organizer->membershipOf($removed))->toBeNull()
        ->and($work['event']->fresh()->user_id)->toBe($main->id);
    Notification::assertNothingSent();
});

test('an organizer left without a main spokesperson gets its longest-serving one when someone steps down', function () {
    [$leaver, $older, $newer] = User::factory()->count(3)->create()->all();
    $organizer = Organizer::factory()->create();
    $organizer->memberships()->create(['user_id' => $newer->id, 'role' => SpokespersonRole::Spokesperson, 'accepted_at' => now()->subDay()]);
    $organizer->memberships()->create(['user_id' => $older->id, 'role' => SpokespersonRole::Spokesperson, 'accepted_at' => now()->subYear()]);
    $organizer->memberships()->create(['user_id' => $leaver->id, 'role' => SpokespersonRole::Spokesperson, 'accepted_at' => now()->subMonth()]);
    $event = Event::factory()->create(['user_id' => $leaver->id, 'organizer_id' => $organizer->id]);

    $this->actingAs($leaver)->delete(spokespersonUrl($organizer, $leaver))->assertRedirect();

    expect($organizer->isMainSpokesperson($older->fresh()))->toBeTrue()
        ->and($event->fresh()->user_id)->toBe($older->id);
});

test('the organizer page tells a spokesperson what stepping down would need decided', function () {
    [$main, $member, $follower] = User::factory()->count(3)->create(['name' => 'Someone'])->all();
    $main->update(['name' => 'Main Person']);
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($member)->create();
    Event::factory()->count(2)->create(['user_id' => $member->id, 'organizer_id' => $organizer->id]);
    EventTemplate::factory()->forOrganizer($organizer)->create(['user_id' => $member->id]);

    $this->actingAs($member)->get("/organizers/{$organizer->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('departure.is_main', false)
            ->where('departure.receiver', ['id' => $main->id, 'name' => 'Main Person'])
            ->where('departure.made', ['events' => 2, 'leaflets' => 0, 'templates' => 1])
            ->has('departure.others', 1));
    $this->actingAs($main)->get("/organizers/{$organizer->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('departure.is_main', true)
            ->where('departure.receiver', null)
            ->where('departure.others.0.id', $member->id));
    $this->actingAs($follower)->get("/organizers/{$organizer->id}")
        ->assertInertia(fn (Assert $page) => $page->where('departure', null));
});

test('deleting an account hands what was made for an organizer to its main spokesperson, in a notice that names no one', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    [$main, $leaver] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($leaver)->create();
    $work = workFor($organizer, $leaver);
    $own = Event::factory()->create(['user_id' => $leaver->id]);

    $this->actingAs($leaver)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    expect(User::query()->find($leaver->id))->toBeNull()
        ->and($work['event']->fresh()?->user_id)->toBe($main->id)
        ->and($work['leaflet']->fresh()?->user_id)->toBe($main->id)
        ->and($work['template']->fresh()?->user_id)->toBe($main->id)
        ->and(Event::query()->find($own->id))->toBeNull();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($work['cover']);

    // The notice outlives the account, since it does not point at it.
    $items = collect(ActivityFeed::for($main->fresh()));
    $notice = $items->firstWhere('type', 'OrganizerHandedOver');
    expect($notice)->not->toBeNull()
        ->and($notice['actor'])->toBeNull()
        ->and($notice['subject_id'])->toBe($organizer->id)
        ->and($notice['handover'])->toBe(['made_main' => false, 'events' => 1, 'leaflets' => 1, 'templates' => 1]);
});

test('deleting an account may delete what was made for one organizer and hand over the rest', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    [$main, $leaver] = User::factory()->count(2)->create()->all();
    $kept = Organizer::factory()->runBy($main)->withSpokesperson($leaver)->create();
    $dropped = Organizer::factory()->runBy($main)->withSpokesperson($leaver)->create();
    $keptWork = workFor($kept, $leaver);
    $droppedWork = workFor($dropped, $leaver);

    $this->actingAs($leaver)->delete('/profile', [
        'password' => 'password',
        'organizers' => [
            $kept->id => ['hand_over' => true],
            $dropped->id => ['hand_over' => false],
        ],
    ])->assertRedirect('/');

    expect($keptWork['event']->fresh()?->user_id)->toBe($main->id)
        ->and(Event::query()->find($droppedWork['event']->id))->toBeNull()
        ->and(Leaflet::query()->find($droppedWork['leaflet']->id))->toBeNull()
        ->and(EventTemplate::query()->find($droppedWork['template']->id))->toBeNull()
        ->and($dropped->fresh())->not->toBeNull();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($keptWork['cover']);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($droppedWork['cover']);
});

test('a main spokesperson deleting their account names who takes over; unnamed, the longest-serving does', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    [$main, $older, $newer, $stranger] = User::factory()->count(4)->create()->all();
    $named = Organizer::factory()->runBy($main)->create();
    $unnamed = Organizer::factory()->runBy($main)->create();

    foreach ([$named, $unnamed] as $organizer) {
        $organizer->memberships()->create(['user_id' => $newer->id, 'role' => SpokespersonRole::Spokesperson, 'accepted_at' => now()->subDay()]);
        $organizer->memberships()->create(['user_id' => $older->id, 'role' => SpokespersonRole::Spokesperson, 'accepted_at' => now()->subYear()]);
    }

    $work = workFor($named, $main);

    // Someone who does not speak for it cannot take over, and nothing happens.
    $this->actingAs($main)->delete('/profile', [
        'password' => 'password',
        'organizers' => [$named->id => ['successor_id' => $stranger->id]],
    ])->assertInvalid(['organizers']);
    expect(User::query()->find($main->id))->not->toBeNull()
        ->and($named->isMainSpokesperson($main->fresh()))->toBeTrue();

    $this->actingAs($main)->delete('/profile', [
        'password' => 'password',
        'organizers' => [$named->id => ['successor_id' => $newer->id]],
    ])->assertRedirect('/');

    expect(User::query()->find($main->id))->toBeNull()
        ->and($named->isMainSpokesperson($newer->fresh()))->toBeTrue()
        ->and($work['event']->fresh()?->user_id)->toBe($newer->id)
        ->and($unnamed->isMainSpokesperson($older->fresh()))->toBeTrue();

    $notice = collect(ActivityFeed::for($newer->fresh()))->firstWhere('type', 'OrganizerHandedOver');
    expect($notice['handover']['made_main'])->toBeTrue();
});

test('an organizer goes with the account of its only spokesperson', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    [$only, $invited, $follower] = User::factory()->count(3)->create()->all();
    $organizer = Organizer::factory()->runBy($only)->create();
    // An invitation not yet answered is no one to hand over to.
    $organizer->memberships()->create(['user_id' => $invited->id, 'role' => SpokespersonRole::Spokesperson]);
    $organizer->followers()->attach($follower->id);
    $work = workFor($organizer, $only);

    $this->actingAs($only)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    expect(Organizer::query()->find($organizer->id))->toBeNull()
        ->and(Event::query()->find($work['event']->id))->toBeNull()
        ->and(EventTemplate::query()->find($work['template']->id))->toBeNull();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($work['cover']);
});

test('an invitation to speak for an organizer changes nothing when the invited account is deleted', function () {
    [$main, $invited] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->create();
    $organizer->memberships()->create(['user_id' => $invited->id, 'role' => SpokespersonRole::Spokesperson]);

    $this->actingAs($invited)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    expect($organizer->fresh())->not->toBeNull()
        ->and($organizer->isMainSpokesperson($main->fresh()))->toBeTrue()
        ->and($organizer->memberships()->count())->toBe(1);
});

test('an administrator deleting an account hands over what it made for an organizer', function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    [$main, $member] = User::factory()->count(2)->create()->all();
    $organizer = Organizer::factory()->runBy($main)->withSpokesperson($member)->create();
    $work = workFor($organizer, $main);

    $this->actingAs($admin)->delete("/admin/users/{$main->id}", ['password' => 'password'])->assertRedirect();

    expect(User::query()->find($main->id))->toBeNull()
        ->and($organizer->isMainSpokesperson($member->fresh()))->toBeTrue()
        ->and($work['event']->fresh()?->user_id)->toBe($member->id)
        ->and($work['leaflet']->fresh()?->user_id)->toBe($member->id);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($work['cover']);
});

test('the account page lists the organizers a person speaks for, with what leaving each would need decided', function () {
    [$user, $other] = User::factory()->count(2)->create()->all();
    $shared = Organizer::factory()->runBy($other)->withSpokesperson($user)->create(['name' => 'B shared']);
    Organizer::factory()->runBy($user)->create(['name' => 'A alone']);
    Event::factory()->create(['user_id' => $user->id, 'organizer_id' => $shared->id]);

    $this->actingAs($user)->get('/settings/account')
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Account')
            ->has('organizers', 2)
            ->where('organizers.0.name', 'A alone')
            ->where('organizers.0.is_main', true)
            ->where('organizers.0.others', [])
            ->where('organizers.1.id', $shared->id)
            ->where('organizers.1.is_main', false)
            ->where('organizers.1.receiver.id', $other->id)
            ->where('organizers.1.made', ['events' => 1, 'leaflets' => 0, 'templates' => 0]));

    $this->actingAs($other)->get('/settings/account')
        ->assertInertia(fn (Assert $page) => $page->has('organizers', 1)->where('organizers.0.others.0.id', $user->id));
});
