<?php

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\Leaflet;
use App\Models\Picture;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A picture that passes validation can still fail to be stored (it does
 * not decode after all, the disk is full). Whatever it was sent with must
 * then not be saved either, and the pictures stored before it must go.
 */
beforeEach(function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);

    // Stores the first picture as usual and fails on the second.
    $this->app->bind(ImageProcessor::class, fn () => new class extends ImageProcessor
    {
        private int $stored = 0;

        public function storePicture(UploadedFile $file, string $directory): array
        {
            if (++$this->stored > 1) {
                throw new RuntimeException('Could not read the uploaded image.');
            }

            return parent::storePicture($file, $directory);
        }
    });
});

function twoPictures(): array
{
    return [UploadedFile::fake()->image('one.jpg', 1200, 800), UploadedFile::fake()->image('two.jpg', 1200, 800)];
}

test('a message is not saved when one of its pictures cannot be stored', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => User::factory()->create()->id]);

    $this->actingAs($user)->post("/messages/{$conversation->id}/send", ['pictures' => twoPictures()], ['Accept' => 'application/json'])
        ->assertServerError();

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('pictures', 0);
    expect(Storage::disk(ImageProcessor::PRIVATE_DISK)->allFiles())->toBe([]);
});

test('a comment is not saved when one of its pictures cannot be stored', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);

    $this->actingAs($host)->post("/events/{$event->id}/comments", ['body' => 'Look', 'pictures' => twoPictures()])
        ->assertServerError();

    $this->assertDatabaseCount('event_comments', 0);
    $this->assertDatabaseCount('pictures', 0);
    expect(Storage::disk(ImageProcessor::PRIVATE_DISK)->allFiles())->toBe([]);
});

test('a leaflet is not saved, and an edited one keeps what it had, when a picture cannot be stored', function () {
    $author = User::factory()->create();

    $this->actingAs($author)->post('/leaflets', ['body' => 'Look', 'visibility' => 'private', 'pictures' => twoPictures()])
        ->assertServerError();

    $this->assertDatabaseCount('leaflets', 0);
    expect(Storage::disk(ImageProcessor::PRIVATE_DISK)->allFiles())->toBe([]);

    $leaflet = Leaflet::factory()->create(['user_id' => $author->id, 'body' => 'As it was']);
    $kept = $leaflet->pictures()->create(['path' => 'leaflets/kept.jpg']);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put('leaflets/kept.jpg', 'picture');

    // A fresh processor for this request: it fails on its second picture.
    $this->actingAs($author)->put("/leaflets/{$leaflet->id}", [
        'body' => 'Changed',
        'visibility' => 'private',
        'remove_pictures' => [$kept->id],
        'pictures' => twoPictures(),
    ])->assertServerError();

    expect($leaflet->refresh()->body)->toBe('As it was')
        ->and(Picture::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(Storage::disk(ImageProcessor::PRIVATE_DISK)->allFiles())->toBe(['leaflets/kept.jpg']);
});
