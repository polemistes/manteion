<?php

use App\Enums\AttendanceStatus;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\User;
use App\Services\BadgeCounts;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ShowcaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Lorem Picsum, faked: every picture comes back as a tiny JPEG of the shape
 * asked for (a hundredth of the size), so no test reaches the network.
 */
function fakeShowcasePictures(): void
{
    Http::fake(['picsum.photos/*' => function (Request $request) {
        [$width, $height] = array_map('intval', array_slice(explode('/', (string) parse_url($request->url(), PHP_URL_PATH)), -2));

        return Http::response(
            UploadedFile::fake()->image('picture.jpg', intdiv($width, 100), intdiv($height, 100))->getContent(),
            200,
            ['Content-Type' => 'image/jpeg'],
        );
    }]);
}

/**
 * @return array<string, int>
 */
function showcaseRowCounts(): array
{
    return collect(['users', 'events', 'event_images', 'event_attendees', 'event_comments', 'event_tag', 'conversations', 'conversation_participants', 'messages', 'reactions', 'leaflets', 'leaflet_responses', 'notifications', 'friendships', 'follows'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
        ->all();
}

beforeEach(function () {
    // A Wednesday morning in Oslo, so "this evening", "tomorrow" and the rest are exact.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 09:30', 'Europe/Oslo'));

    Storage::fake('public');
    Queue::fake();
    Http::preventStrayRequests();

    $this->owner = User::factory()->create(['name' => 'Robert Emil Berge', 'username' => 'Robert', 'email' => 'post@robertemilberge.no']);
});

test('it seeds a current showcase around alice and the owner', function () {
    fakeShowcasePictures();

    $this->seed(ShowcaseSeeder::class);

    $alice = User::query()->where('username', 'alice_nilsen')->firstOrFail();
    $quiz = Event::query()->where('name', 'Pub quiz at Schouskjelleren')->firstOrFail();

    expect(Event::query()->where('starts_at', '>', now())->count())->toBe(9)
        ->and(Event::query()->where('starts_at', '<', now())->count())->toBe(1)
        ->and($quiz->starts_at->setTimezone('Europe/Oslo')->format('Y-m-d H:i'))->toBe('2026-10-07 19:00');

    expect($alice->eventAttendances()->where('status', AttendanceStatus::NoResponse)->count())->toBe(2)
        ->and(BadgeCounts::for($alice))->toBe(['messages' => 2, 'activity' => 9, 'requests' => 2])
        ->and(BadgeCounts::for($this->owner)['messages'])->toBe(1)
        ->and($this->owner->notifications()->count())->toBe(9)
        ->and($this->owner->unreadNotifications()->count())->toBe(7)
        ->and($alice->notifications()->pluck('type')->map(fn (string $type) => class_basename($type))->unique()->sort()->values()->all())
        ->toBe(['FollowedUserPublishedEvent', 'FriendRequestAccepted', 'FriendRequestReceived', 'InvitedToEvent', 'NewEventComment', 'NewFollower', 'NewLeafletResponse']);

    Conversation::query()->each(function (Conversation $conversation) {
        $times = $conversation->messages()->orderBy('id')->pluck('created_at');

        expect($times->sliding(2)->every(fn ($pair) => $pair->last()->gt($pair->first())))->toBeTrue();
    });

    $shapes = EventImage::query()->get()->map(function (EventImage $image) {
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($image->path));

        return $width > $height ? 'photo' : 'poster';
    });

    expect($shapes->countBy()->sortKeys()->all())->toBe(['photo' => 3, 'poster' => 2]);

    // The rows remember their size, so pages frame posters whole.
    expect(EventImage::query()->get()->map(fn (EventImage $image) => $image->shape())->countBy()->sortKeys()->all())
        ->toBe(['photo' => 3, 'poster' => 2]);
    Storage::disk('public')->assertExists(Leaflet::query()->whereNotNull('image_path')->sole()->image_path);
    Http::assertSentCount(6);
    Queue::assertNothingPushed();
});

test('running it again later refreshes the showcase instead of duplicating it', function () {
    fakeShowcasePictures();
    $this->seed(ShowcaseSeeder::class);
    $counts = showcaseRowCounts();

    $alice = User::query()->where('username', 'alice_nilsen')->firstOrFail();
    $alice->unreadNotifications()->update(['read_at' => now()]);
    $alice->conversationParticipations()->update(['last_read_at' => now()]);

    $this->travel(10)->days();
    $this->seed(ShowcaseSeeder::class);

    expect(showcaseRowCounts())->toBe($counts)
        ->and(Event::query()->where('starts_at', '>', now())->count())->toBe(9)
        ->and($alice->unreadNotifications()->count())->toBe(9)
        ->and(BadgeCounts::for($alice)['messages'])->toBe(2);

    Http::assertSentCount(6);
});

test('it builds on the demo seeder without duplicating its people, friendships or chats', function () {
    fakeShowcasePictures();

    $this->seed(DemoSeeder::class);
    $this->seed(ShowcaseSeeder::class);
    $this->seed(ShowcaseSeeder::class);

    $alice = User::query()->where('username', 'alice_nilsen')->firstOrFail();
    $pair = [$this->owner->id, $alice->id];

    expect(User::query()->count())->toBe(11)
        ->and(Friendship::query()->whereIn('requester_id', $pair)->whereIn('addressee_id', $pair)->count())->toBe(1)
        ->and(Conversation::query()
            ->where('type', ConversationType::Direct)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $this->owner->id))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $alice->id))
            ->count())->toBe(1)
        ->and(BadgeCounts::for($alice)['messages'])->toBe(2)
        ->and(BadgeCounts::for($this->owner)['messages'])->toBe(1);
});

test('it draws stand-in pictures when Lorem Picsum cannot be reached', function () {
    Http::fake(['picsum.photos/*' => Http::failedConnection()]);

    $this->seed(ShowcaseSeeder::class);

    $sizes = EventImage::query()->get()->map(fn (EventImage $image) => array_slice(getimagesizefromstring(Storage::disk('public')->get($image->path)), 0, 2));

    expect($sizes->filter(fn (array $size) => $size === [1600, 1000]))->toHaveCount(3)
        ->and($sizes->filter(fn (array $size) => $size === [1000, 1500]))->toHaveCount(2);
});
