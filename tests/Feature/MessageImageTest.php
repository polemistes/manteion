<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\IncomingMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Picture;
use App\Models\Report;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use App\Services\ImageProcessor;
use App\Services\MessageSummary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A direct conversation between the two users.
 */
function pictureChat(User $user, User $other): Conversation
{
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);

    return $conversation;
}

/**
 * Sends pictures (with the given words, if any) as the user, the way the
 * chat does: a multipart request that wants JSON back.
 *
 * @param  UploadedFile|array<int, UploadedFile>  $pictures
 * @return array{response: TestResponse, message: Message}
 */
function sendPicture(User $user, Conversation $conversation, UploadedFile|array $pictures, ?string $body = null): array
{
    $response = test()->actingAs($user)->post(
        "/messages/{$conversation->id}/send",
        array_filter(['pictures' => is_array($pictures) ? $pictures : [$pictures], 'body' => $body]),
        ['Accept' => 'application/json'],
    );

    return ['response' => $response, 'message' => Message::query()->latest('id')->firstOrFail()];
}

beforeEach(function () {
    Storage::fake(ImageProcessor::PRIVATE_DISK);
    Storage::fake('public');
});

test('a participant can send a picture without words', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['response' => $response, 'message' => $message] = sendPicture($user, $conversation, UploadedFile::fake()->image('photo.jpg', 800, 600));
    $picture = $message->pictures()->sole();

    $response->assertCreated()
        ->assertJsonPath('message.body', '')
        ->assertJsonCount(1, 'message.pictures')
        ->assertJsonPath('message.pictures.0.url', route('pictures.show', [$picture, basename($picture->path)]))
        ->assertJsonPath('message.pictures.0.thumb_url', route('pictures.show', [$picture, basename($picture->thumb_path)]))
        ->assertJsonPath('message.pictures.0.width', 800)
        ->assertJsonPath('message.pictures.0.height', 600);

    expect($message->body)->toBe('')
        ->and($picture->path)->toStartWith("messages/{$conversation->id}/")
        ->and($picture->width)->toBe(800)
        ->and($picture->height)->toBe(600);

    // On the private disk, where nothing has an address of its own.
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($picture->path);
    Storage::disk('public')->assertMissing($picture->path);
});

test('a message can carry several pictures, kept in the order they were sent', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['response' => $response, 'message' => $message] = sendPicture($user, $conversation, [
        UploadedFile::fake()->image('first.jpg', 900, 600),
        UploadedFile::fake()->image('second.jpg', 600, 900),
        UploadedFile::fake()->image('third.jpg', 300, 300),
    ], 'From the hike');

    $response->assertCreated()
        ->assertJsonPath('message.body', 'From the hike')
        ->assertJsonCount(3, 'message.pictures')
        ->assertJsonPath('message.pictures.0.width', 900)
        ->assertJsonPath('message.pictures.1.width', 600)
        ->assertJsonPath('message.pictures.2.width', 300);

    $pictures = $message->pictures()->get();

    expect($pictures->pluck('position')->all())->toBe([0, 1, 2]);

    foreach ($pictures as $picture) {
        Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($picture->path);
    }
});

test('a picture gets a smaller copy for grids, unless it is small itself', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['message' => $message] = sendPicture($user, $conversation, [
        UploadedFile::fake()->image('large.jpg', 1600, 1200),
        UploadedFile::fake()->image('small.jpg', 400, 300),
    ]);
    [$large, $small] = $message->pictures()->get()->all();
    $disk = Storage::disk(ImageProcessor::PRIVATE_DISK);

    expect($large->thumb_path)->not->toBeNull()
        ->and(array_slice(getimagesizefromstring($disk->get($large->thumb_path)), 0, 2))->toBe([640, 480])
        ->and($small->thumb_path)->toBeNull()
        ->and($small->presentation()['thumb_url'])->toBe($small->presentation()['url']);

    // The smaller copy opens for the same people, under its own address.
    $this->actingAs($other)->get($large->presentation()['thumb_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});

test('a picture can carry a caption', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['response' => $response, 'message' => $message] = sendPicture($user, $conversation, UploadedFile::fake()->image('photo.jpg'), 'Look at this');

    $response->assertCreated()->assertJsonPath('message.body', 'Look at this');
    expect($message->body)->toBe('Look at this')
        ->and($message->pictures()->count())->toBe(1);
});

test('a page loaded before pictures came in several can still send its one', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    $this->actingAs($user)->post(
        "/messages/{$conversation->id}/send",
        ['image' => UploadedFile::fake()->image('photo.jpg', 800, 600)],
        ['Accept' => 'application/json'],
    )->assertCreated()->assertJsonCount(1, 'message.pictures');
});

test('a message needs words or a picture', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    $this->actingAs($user)->postJson("/messages/{$conversation->id}/send", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);

    $this->assertDatabaseCount('messages', 0);
});

test('only a picture may be attached', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    $this->actingAs($user)->post(
        "/messages/{$conversation->id}/send",
        ['pictures' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')]],
        ['Accept' => 'application/json'],
    )->assertUnprocessable()->assertJsonValidationErrors(['pictures.0']);

    $this->assertDatabaseCount('messages', 0);
});

test('a message carries no more pictures than a post may', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);
    $tooMany = array_map(fn (int $index) => UploadedFile::fake()->image("photo-{$index}.jpg", 40, 40), range(0, Picture::MAX_PER_POST));

    $this->actingAs($user)->post("/messages/{$conversation->id}/send", ['pictures' => $tooMany], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pictures' => 'You can add up to 10 pictures at a time.']);

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('pictures', 0);
});

test('a big picture is scaled down to fit', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['message' => $message] = sendPicture($user, $conversation, UploadedFile::fake()->image('wide.jpg', 3200, 1600));
    $picture = $message->pictures()->sole();

    expect($picture->width)->toBe(1600)
        ->and($picture->height)->toBe(800);
});

test('the picture is shown to the people in the conversation and to staff, and to no one else', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $outsider = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $conversation = pictureChat($user, $other);

    ['message' => $message] = sendPicture($user, $conversation, UploadedFile::fake()->image('photo.jpg'));
    $picture = $message->pictures()->sole();
    $url = $picture->presentation()['url'];

    $response = $this->actingAs($other)->get($url);
    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=31536000');

    $this->actingAs($moderator)->get($url)->assertOk();
    $this->actingAs($outsider)->get($url)->assertForbidden();

    // Not under a made-up file name either.
    $this->actingAs($other)->get(route('pictures.show', [$picture, 'something-else.jpg']))->assertNotFound();
});

test('a picture needs a login', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);
    $message = Message::factory()->withPictures()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => '']);

    $this->get($message->pictures()->sole()->presentation()['url'])->assertRedirect(route('login'));
});

test('deleting a message removes its pictures for good', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['message' => $message] = sendPicture($user, $conversation, [
        UploadedFile::fake()->image('photo.jpg', 1200, 900),
        UploadedFile::fake()->image('another.jpg', 1200, 900),
    ], 'Oops');
    $pictures = $message->pictures()->get();
    $url = $pictures[0]->presentation()['url'];

    $this->actingAs($user)->delete("/messages/{$conversation->id}/messages/{$message->id}")->assertRedirect();

    foreach ($pictures as $picture) {
        Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($picture->path);
        Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($picture->thumb_path);
    }

    $message->refresh();
    expect($message->is_deleted)->toBeTrue()
        ->and($message->pictures()->count())->toBe(0)
        ->and(MessageSummary::for($message, $other)['pictures'])->toBe([])
        ->and(MessageSummary::broadcast($message)['pictures'])->toBe([]);

    $this->actingAs($other)->get($url)->assertNotFound();
});

test('a message without a picture says so in both shapes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    expect(MessageSummary::for($message, $other)['pictures'])->toBe([])
        ->and(MessageSummary::broadcast($message)['pictures'])->toBe([]);

    $this->actingAs($other)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->where('messages.0.pictures', []));
});

test('the thread, the broadcast and the page carry the pictures with their sizes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);
    $message = Message::factory()->withPictures(2, 1600, 1200)->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => '']);
    $pictures = $message->pictures()->get()->map(fn (Picture $picture) => [
        'id' => $picture->id,
        'url' => route('pictures.show', [$picture, basename($picture->path)]),
        'thumb_url' => route('pictures.show', [$picture, basename($picture->thumb_path)]),
        'width' => 1600,
        'height' => 1200,
    ])->all();

    expect($pictures)->toHaveCount(2)
        ->and(MessageSummary::for($message, $other)['pictures'])->toBe($pictures)
        ->and(MessageSummary::broadcast($message)['pictures'])->toBe($pictures);

    $this->actingAs($other)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->where('messages.0.pictures', $pictures)->where('messages.0.body', ''));
});

test('lists, toasts and pushes describe pictures, in the readers language', function () {
    $user = User::factory()->create(['locale' => 'nb']);
    $other = User::factory()->create(['locale' => 'en']);
    $conversation = pictureChat($user, $other);
    $photo = Message::factory()->withPictures()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => '']);

    expect(MessageSummary::preview($photo))->toBe('📷 Photo')
        ->and(MessageSummary::preview($photo, locale: 'nb'))->toBe('📷 Bilde');

    $several = Message::factory()->withPictures(3)->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => '']);
    expect(MessageSummary::preview($several))->toBe('📷 3 photos')
        ->and(MessageSummary::preview($several, locale: 'nb'))->toBe('📷 3 bilder');

    $captioned = Message::factory()->withPictures()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => "Look  at\nthis"]);
    expect(MessageSummary::preview($captioned))->toBe('📷 Look at this');

    // The push is built in the language the notification was given (the
    // app's, here); the toast's label follows the recipient.
    $notification = new NewMessageReceived($photo);
    expect($notification->toWebPush($user, $notification)->toArray()['body'])->toBe('📷 Photo');
    expect((new IncomingMessage($user->id, $several))->broadcastWith()['preview'])->toBe('📷 3 bilder');

    $captioned->delete();

    $this->actingAs($user)->get('/messages')
        ->assertInertia(fn (Assert $page) => $page->where('conversations.0.last_message.body', '📷 3 bilder'));
});

test('reported pictures are shown to the moderators', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $conversation = pictureChat($user, $other);
    $message = Message::factory()->withPictures(2)->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => '']);
    Report::factory()->create([
        'reporter_id' => $other->id,
        'reportable_type' => $message->getMorphClass(),
        'reportable_id' => $message->id,
    ]);

    $this->actingAs($moderator)->get(route('moderation.reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.reportable.pictures', $message->picturesPresentation())
            ->where('reports.0.reportable.excerpt', ''));
});
