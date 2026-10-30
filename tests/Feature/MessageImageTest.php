<?php

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\IncomingMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
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
 * Sends a picture (with the given words, if any) as the user, the way the
 * chat does: a multipart request that wants JSON back.
 *
 * @return array{response: TestResponse, message: Message}
 */
function sendPicture(User $user, Conversation $conversation, UploadedFile $picture, ?string $body = null): array
{
    $response = test()->actingAs($user)->post(
        "/messages/{$conversation->id}/send",
        array_filter(['image' => $picture, 'body' => $body]),
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

    $response->assertCreated()
        ->assertJsonPath('message.body', '')
        ->assertJsonPath('message.image.url', route('messages.messages.image', [$conversation, $message]))
        ->assertJsonPath('message.image.width', 800)
        ->assertJsonPath('message.image.height', 600);

    expect($message->body)->toBe('')
        ->and($message->image_path)->toStartWith("messages/{$conversation->id}/")
        ->and($message->image_width)->toBe(800)
        ->and($message->image_height)->toBe(600);

    // On the private disk, where nothing has an address of its own.
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($message->image_path);
    Storage::disk('public')->assertMissing($message->image_path);
});

test('a picture can carry a caption', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['response' => $response, 'message' => $message] = sendPicture($user, $conversation, UploadedFile::fake()->image('photo.jpg'), 'Look at this');

    $response->assertCreated()->assertJsonPath('message.body', 'Look at this');
    expect($message->body)->toBe('Look at this')
        ->and($message->image_path)->not->toBeNull();
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
        ['image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
        ['Accept' => 'application/json'],
    )->assertUnprocessable()->assertJsonValidationErrors(['image']);

    $this->assertDatabaseCount('messages', 0);
});

test('a big picture is scaled down to fit', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['message' => $message] = sendPicture($user, $conversation, UploadedFile::fake()->image('wide.jpg', 3200, 1600));

    expect($message->image_width)->toBe(1600)
        ->and($message->image_height)->toBe(800);
});

test('the picture is shown to the people in the conversation and to staff, and to no one else', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $outsider = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $conversation = pictureChat($user, $other);
    $elsewhere = pictureChat($outsider, $moderator);

    ['message' => $message] = sendPicture($user, $conversation, UploadedFile::fake()->image('photo.jpg'));
    $url = route('messages.messages.image', [$conversation, $message]);

    $response = $this->actingAs($other)->get($url);
    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=31536000');

    $this->actingAs($moderator)->get($url)->assertOk();
    $this->actingAs($outsider)->get($url)->assertForbidden();

    // Not under another conversation's address either.
    $this->actingAs($outsider)->get(route('messages.messages.image', [$elsewhere, $message]))->assertNotFound();
});

test('a picture needs a login', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);
    $message = Message::factory()->withImage()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => '']);

    $this->get(route('messages.messages.image', [$conversation, $message]))->assertRedirect(route('login'));
});

test('deleting a message removes its picture for good', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);

    ['message' => $message] = sendPicture($user, $conversation, UploadedFile::fake()->image('photo.jpg'), 'Oops');
    $path = $message->image_path;

    $this->actingAs($user)->delete("/messages/{$conversation->id}/messages/{$message->id}")->assertRedirect();

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($path);
    $message->refresh();
    expect($message->is_deleted)->toBeTrue()
        ->and($message->image_path)->toBeNull()
        ->and($message->image_width)->toBeNull()
        ->and(MessageSummary::for($message, $other)['image'])->toBeNull()
        ->and(MessageSummary::broadcast($message)['image'])->toBeNull();

    $this->actingAs($other)->get(route('messages.messages.image', [$conversation, $message]))->assertNotFound();
});

test('a message without a picture says so in both shapes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    expect(MessageSummary::for($message, $other)['image'])->toBeNull()
        ->and(MessageSummary::broadcast($message)['image'])->toBeNull();

    $this->actingAs($other)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->where('messages.0.image', null));
});

test('the thread, the broadcast and the page carry the picture with its size', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = pictureChat($user, $other);
    $message = Message::factory()->withImage(1600, 1200)->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => '']);
    $image = ['url' => route('messages.messages.image', [$conversation, $message]), 'width' => 1600, 'height' => 1200];

    expect(MessageSummary::for($message, $other)['image'])->toBe($image)
        ->and(MessageSummary::broadcast($message)['image'])->toBe($image);

    $this->actingAs($other)->get("/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->where('messages.0.image', $image)->where('messages.0.body', ''));
});

test('lists, toasts and pushes describe a picture, in the readers language', function () {
    $user = User::factory()->create(['locale' => 'nb']);
    $other = User::factory()->create(['locale' => 'en']);
    $conversation = pictureChat($user, $other);
    $photo = Message::factory()->withImage()->create(['conversation_id' => $conversation->id, 'user_id' => $other->id, 'body' => '']);

    expect(MessageSummary::preview($photo))->toBe('📷 Photo')
        ->and(MessageSummary::preview($photo, locale: 'nb'))->toBe('📷 Bilde');

    $captioned = Message::factory()->withImage()->make(['body' => "Look  at\nthis"]);
    expect(MessageSummary::preview($captioned))->toBe('📷 Look at this');

    // The push is built in the language the notification was given (the
    // app's, here); the toast's label follows the recipient.
    $notification = new NewMessageReceived($photo);
    expect($notification->toWebPush($user, $notification)->toArray()['body'])->toBe('📷 Photo');
    expect((new IncomingMessage($user->id, $photo))->broadcastWith()['preview'])->toBe('📷 Bilde');

    $this->actingAs($user)->get('/messages')
        ->assertInertia(fn (Assert $page) => $page->where('conversations.0.last_message.body', '📷 Bilde'));
});

test('a reported picture is shown to the moderators', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $conversation = pictureChat($user, $other);
    $message = Message::factory()->withImage()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => '']);
    Report::factory()->create([
        'reporter_id' => $other->id,
        'reportable_type' => $message->getMorphClass(),
        'reportable_id' => $message->id,
    ]);

    $this->actingAs($moderator)->get(route('moderation.reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.reportable.image_url', route('messages.messages.image', [$conversation, $message]))
            ->where('reports.0.reportable.excerpt', ''));
});
