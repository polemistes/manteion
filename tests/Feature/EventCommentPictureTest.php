<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\Follow;
use App\Models\Picture;
use App\Models\User;
use App\Notifications\NewEventComment;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    Storage::fake('public');
});

/**
 * A public event of the host's that the follower may see and comment on.
 *
 * @return array{0: User, 1: User, 2: Event}
 */
function eventWithFollower(): array
{
    $host = User::factory()->create(['locale' => 'en']);
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $host->id]);

    return [$host, $follower, Event::factory()->public()->create(['user_id' => $host->id, 'allow_invitee_comments' => true])];
}

test('a comment can carry pictures, with or without words', function () {
    [$host, $follower, $event] = eventWithFollower();

    $this->actingAs($follower)->post("/events/{$event->id}/comments", [
        'body' => 'From last year',
        'pictures' => [UploadedFile::fake()->image('one.jpg', 1200, 800), UploadedFile::fake()->image('two.jpg', 600, 900)],
    ])->assertRedirect()->assertValid();

    $this->actingAs($follower)->post("/events/{$event->id}/comments", [
        'pictures' => [UploadedFile::fake()->image('alone.jpg', 300, 300)],
    ])->assertRedirect()->assertValid();

    [$withWords, $alone] = EventComment::query()->orderBy('id')->get()->all();

    expect($withWords->body)->toBe('From last year')
        ->and($withWords->pictures()->count())->toBe(2)
        ->and($withWords->pictures()->first()->path)->toStartWith("comments/{$event->id}/")
        ->and($alone->body)->toBe('')
        ->and($alone->pictures()->count())->toBe(1);

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($withWords->pictures()->first()->path);
    Storage::disk('public')->assertMissing($withWords->pictures()->first()->path);

    $this->actingAs($host)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.0.pictures', $withWords->picturesPresentation())
            ->where('comments.1.pictures', $alone->picturesPresentation())
            ->where('comments.1.body', ''));
});

test('a comment needs words or a picture, and no more pictures than a post may carry', function () {
    [, $follower, $event] = eventWithFollower();

    $this->actingAs($follower)->post("/events/{$event->id}/comments", [])->assertInvalid(['body']);

    $this->actingAs($follower)->post("/events/{$event->id}/comments", [
        'pictures' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
    ])->assertInvalid(['pictures.0']);

    $this->actingAs($follower)->post("/events/{$event->id}/comments", [
        'pictures' => array_map(fn (int $index) => UploadedFile::fake()->image("photo-{$index}.jpg", 40, 40), range(0, Picture::MAX_PER_POST)),
    ])->assertInvalid(['pictures']);

    $this->assertDatabaseCount('event_comments', 0);
    $this->assertDatabaseCount('pictures', 0);
});

test('a comment picture opens for those who may see the event, and for staff, and for no one else', function () {
    [$host, $follower, $event] = eventWithFollower();
    $stranger = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);

    $this->actingAs($follower)->post("/events/{$event->id}/comments", ['pictures' => [UploadedFile::fake()->image('one.jpg', 1200, 800)]]);
    $picture = Picture::query()->sole();

    $this->actingAs($host)->get($picture->presentation()['url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($follower)->get($picture->presentation()['thumb_url'])->assertOk();
    $this->actingAs($moderator)->get($picture->presentation()['url'])->assertOk();
    $this->actingAs($stranger)->get($picture->presentation()['url'])->assertForbidden();

    // It follows the event when that is made private.
    $event->update(['visibility' => 'private']);
    $this->actingAs($follower)->get($picture->presentation()['url'])->assertForbidden();
});

test('a comment without words tells the host of its pictures', function () {
    [$host, $follower, $event] = eventWithFollower();

    $this->actingAs($follower)->post("/events/{$event->id}/comments", [
        'pictures' => [UploadedFile::fake()->image('one.jpg', 40, 40), UploadedFile::fake()->image('two.jpg', 40, 40)],
    ]);

    expect((new NewEventComment(EventComment::query()->sole()))->excerpt())->toBe('📷 2 photos')
        ->and($host->notifications()->sole()->data['excerpt'])->toBe('📷 2 photos');
});

test('deleting a comment deletes its pictures, whoever does it and however', function (string $by) {
    [$host, $follower, $event] = eventWithFollower();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);

    $this->actingAs($follower)->post("/events/{$event->id}/comments", [
        'body' => 'Look',
        'pictures' => [UploadedFile::fake()->image('one.jpg', 1200, 800)],
    ]);
    $comment = EventComment::query()->sole();
    $picture = $comment->pictures()->sole();

    match ($by) {
        'author' => $this->actingAs($follower)->delete("/events/{$event->id}/comments/{$comment->id}")->assertRedirect(),
        'host' => $this->actingAs($host)->delete("/events/{$event->id}/comments/{$comment->id}")->assertRedirect(),
        'moderator, leaving a placeholder' => $this->actingAs($moderator)->delete("/events/{$event->id}/comments/{$comment->id}", ['leave_placeholder' => true])->assertRedirect(),
    };

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($picture->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($picture->thumb_path);
    $this->assertDatabaseCount('pictures', 0);
    $this->actingAs($host)->get($picture->presentation()['url'])->assertNotFound();
})->with(['author', 'host', 'moderator, leaving a placeholder']);

test('deleting an event deletes the pictures on its comments', function () {
    [$host, $follower, $event] = eventWithFollower();
    $otherEvent = Event::factory()->public()->create(['user_id' => $host->id]);

    $this->actingAs($follower)->post("/events/{$event->id}/comments", ['pictures' => [UploadedFile::fake()->image('one.jpg', 1200, 800)]]);
    $this->actingAs($follower)->post("/events/{$otherEvent->id}/comments", ['pictures' => [UploadedFile::fake()->image('kept.jpg', 1200, 800)]]);
    [$gone, $kept] = Picture::query()->orderBy('id')->get()->all();

    $this->actingAs($host)->delete("/events/{$event->id}")->assertRedirect();

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($gone->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($gone->thumb_path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($kept->path);
    expect(Picture::query()->pluck('id')->all())->toBe([$kept->id]);
});

test('the pictures a message and a leaflet already had move into the pictures table, and back', function () {
    $migration = require database_path('migrations/'.collect(scandir(database_path('migrations')))->first(fn (string $file) => str_ends_with($file, '_create_pictures_table.php')));
    $user = User::factory()->create();
    Storage::disk('local')->put('leaflets/1/old.jpg', UploadedFile::fake()->image('old.jpg', 90, 60)->getContent());

    $migration->down();

    $conversationId = DB::table('conversations')->insertGetId(['type' => 'direct', 'created_at' => now(), 'updated_at' => now()]);
    $messageId = DB::table('messages')->insertGetId(['conversation_id' => $conversationId, 'user_id' => $user->id, 'body' => 'Hi', 'image_path' => 'messages/1/chat.jpg', 'image_width' => 800, 'image_height' => 600, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('messages')->insert(['conversation_id' => $conversationId, 'user_id' => $user->id, 'body' => 'No picture', 'created_at' => now(), 'updated_at' => now()]);
    $leafletId = DB::table('leaflets')->insertGetId(['user_id' => $user->id, 'body' => 'Look', 'visibility' => 'private', 'image_path' => 'leaflets/1/old.jpg', 'created_at' => now(), 'updated_at' => now()]);

    $migration->up();

    expect(Picture::query()->orderBy('id')->get()->map->only(['message_id', 'leaflet_id', 'path', 'thumb_path', 'width', 'height'])->all())->toBe([
        ['message_id' => $messageId, 'leaflet_id' => null, 'path' => 'messages/1/chat.jpg', 'thumb_path' => null, 'width' => 800, 'height' => 600],
        // A leaflet's picture had no size on record: read from the file.
        ['message_id' => null, 'leaflet_id' => $leafletId, 'path' => 'leaflets/1/old.jpg', 'thumb_path' => null, 'width' => 90, 'height' => 60],
    ]);

    $migration->down();

    expect(DB::table('messages')->where('id', $messageId)->value('image_path'))->toBe('messages/1/chat.jpg')
        ->and(DB::table('leaflets')->where('id', $leafletId)->value('image_path'))->toBe('leaflets/1/old.jpg');

    $migration->up();
});
