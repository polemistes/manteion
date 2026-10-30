<?php

use App\Enums\AttendanceStatus;
use App\Enums\ConversationType;
use App\Enums\EventVisibility;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventImage;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\Message;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake(ImageProcessor::PRIVATE_DISK);
});

/**
 * An event with a picture on the private disk, as the event form leaves it.
 */
function eventWithPicture(User $host, EventVisibility $visibility = EventVisibility::Private): EventImage
{
    $event = Event::factory()->create(['user_id' => $host->id, 'visibility' => $visibility]);
    $path = "events/{$event->id}/".str_repeat('a', 40).'.jpg';
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($path, 'picture');

    return EventImage::factory()->for($event)->create(['path' => $path]);
}

/**
 * A leaflet with a picture on the private disk.
 */
function leafletWithPicture(User $author, string $visibility = 'friends'): Leaflet
{
    $leaflet = Leaflet::factory()->{$visibility}()->create(['user_id' => $author->id]);
    $path = "leaflets/{$leaflet->id}/".str_repeat('b', 40).'.jpg';
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($path, 'picture');
    $leaflet->update(['image_path' => $path]);

    return $leaflet;
}

test('an uploaded event picture is kept on the private disk and pages get its gated address', function () {
    $host = User::factory()->create();

    $this->actingAs($host)->post('/events', [
        'name' => 'Garden Party',
        'description' => 'Bring a chair.',
        'starts_at' => now()->addWeek()->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'image' => UploadedFile::fake()->image('garden.jpg', 1200, 800),
    ])->assertRedirect();

    $event = Event::query()->where('name', 'Garden Party')->firstOrFail();
    $image = $event->images->sole();
    $url = route('events.images.show', [$event, $image, basename($image->path)]);

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($image->path);
    Storage::disk('public')->assertMissing($image->path);
    expect($image->presentation()['url'])->toBe($url);

    $this->actingAs($host)->get("/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page->where('event.images.0.url', $url));

    $response = $this->actingAs($host)->get($url);
    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('immutable');
});

test('an event picture opens only for those who may see the event, and for staff', function () {
    $host = User::factory()->create();
    $guest = User::factory()->create();
    $stranger = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $image = eventWithPicture($host);
    EventAttendee::factory()->create(['event_id' => $image->event_id, 'user_id' => $guest->id, 'status' => AttendanceStatus::NoResponse]);
    $url = $image->presentation()['url'];

    $this->actingAs($host)->get($url)->assertOk();
    $this->actingAs($guest)->get($url)->assertOk();
    $this->actingAs($moderator)->get($url)->assertOk();
    $this->actingAs($stranger)->get($url)->assertForbidden();
});

test('an event picture follows the event when it is made private', function () {
    $host = User::factory()->create();
    $follower = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $host->id]);
    $image = eventWithPicture($host, EventVisibility::Public);
    $url = $image->presentation()['url'];

    $this->actingAs($follower)->get($url)->assertOk();

    $image->event->update(['visibility' => EventVisibility::Private]);

    $this->actingAs($follower)->get($url)->assertForbidden();
});

test('an event picture needs a login and its exact address', function () {
    $host = User::factory()->create();
    $image = eventWithPicture($host);
    $other = eventWithPicture($host);

    $this->get($image->presentation()['url'])->assertRedirect(route('login'));

    // Another file name, or another event's image under this event.
    $this->actingAs($host)->get(route('events.images.show', [$image->event_id, $image->id, str_repeat('c', 40).'.jpg']))->assertNotFound();
    $this->actingAs($host)->get(route('events.images.show', [$image->event_id, $other->id, basename($other->path)]))->assertNotFound();
});

test('older pictures stored in another format still open', function () {
    $host = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $host->id]);
    $image = EventImage::factory()->for($event)->create(['path' => "events/{$event->id}/Old_Upload-1.png"]);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($image->path, 'picture');
    $leaflet = Leaflet::factory()->create(['user_id' => $host->id, 'image_path' => 'leaflets/9/legacy.webp']);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($leaflet->image_path, 'picture');

    $this->actingAs($host)->get($image->presentation()['url'])->assertOk();
    $this->actingAs($host)->get($leaflet->imageUrl())->assertOk();
});

test('removing or replacing an event picture deletes its file', function () {
    $host = User::factory()->create();
    $image = eventWithPicture($host);
    $replaced = eventWithPicture($host);

    $this->actingAs($host)->delete(route('events.images.destroy', [$image->event_id, $image->id]))->assertRedirect();
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($image->path);

    $event = $replaced->event;
    $this->actingAs($host)->put("/events/{$event->id}", [
        'name' => $event->name,
        'place' => $event->place,
        'description' => $event->description,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'visibility' => 'private',
        'allow_invitee_invites' => false,
        'allow_invitee_comments' => true,
        'image' => UploadedFile::fake()->image('new.jpg'),
    ])->assertRedirect();

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($replaced->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($event->images()->sole()->path);
});

test('an uploaded leaflet picture is kept on the private disk and opens only for its readers', function () {
    $author = User::factory()->create();
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $author->id, 'addressee_id' => $friend->id]);

    $this->actingAs($author)->post('/leaflets', [
        'body' => 'Look what I found',
        'visibility' => 'friends',
        'image' => UploadedFile::fake()->image('find.jpg', 600, 400),
    ])->assertRedirect();

    $leaflet = Leaflet::query()->sole();
    $url = route('leaflets.image', [$leaflet, basename($leaflet->image_path)]);

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($leaflet->image_path);
    Storage::disk('public')->assertMissing($leaflet->image_path);
    expect($leaflet->imageUrl())->toBe($url);

    $this->actingAs($friend)->get("/leaflets/{$leaflet->id}")
        ->assertInertia(fn (Assert $page) => $page->where('leaflet.image_url', $url));

    $this->actingAs($friend)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($author)->get($url)->assertOk();
    $this->actingAs($stranger)->get($url)->assertForbidden();
});

test('a replaced leaflet picture gets a new address and the old one closes', function () {
    $author = User::factory()->create();
    $leaflet = leafletWithPicture($author);
    $oldUrl = $leaflet->imageUrl();
    $oldPath = $leaflet->image_path;

    $this->actingAs($author)->put("/leaflets/{$leaflet->id}", [
        'body' => $leaflet->body,
        'visibility' => 'friends',
        'image' => UploadedFile::fake()->image('new.jpg', 600, 400),
    ])->assertRedirect()->assertValid();

    $leaflet->refresh();
    expect($leaflet->imageUrl())->not->toBe($oldUrl);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($oldPath);

    $this->actingAs($author)->get($oldUrl)->assertNotFound();
    $this->actingAs($author)->get($leaflet->imageUrl())->assertOk();
});

test('deleting a leaflet deletes its picture', function () {
    $author = User::factory()->create();
    $leaflet = leafletWithPicture($author);
    $path = $leaflet->image_path;

    $this->actingAs($author)->delete("/leaflets/{$leaflet->id}")->assertRedirect();

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($path);
});

test('deleting an account removes the pictures on its events, leaflets and messages, and nobody elses', function (bool $byAdmin) {
    $user = User::factory()->create(['avatar_path' => 'avatars/mine.jpg']);
    Storage::disk('public')->put('avatars/mine.jpg', 'picture');
    $friend = User::factory()->create();
    $eventImage = eventWithPicture($user);
    $leaflet = leafletWithPicture($user);
    $keptLeaflet = leafletWithPicture($friend);

    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);
    $message = Message::factory()->withImage()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => '']);
    $keptMessage = Message::factory()->withImage()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id, 'body' => '']);
    $private = Storage::disk(ImageProcessor::PRIVATE_DISK);
    $private->put($message->image_path, 'picture');
    $private->put($keptMessage->image_path, 'picture');

    if ($byAdmin) {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $user), ['password' => 'password'])->assertRedirect();
    } else {
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
    }

    expect(User::find($user->id))->toBeNull();
    Storage::disk('public')->assertMissing('avatars/mine.jpg');
    $private->assertMissing($eventImage->path);
    $private->assertMissing($leaflet->image_path);
    $private->assertMissing($message->image_path);
    $private->assertExists($keptLeaflet->image_path);
    $private->assertExists($keptMessage->image_path);
})->with(['by themselves' => false, 'by an administrator' => true]);

test('deleting an organizer removes its picture and those on its events and leaflets', function () {
    $spokesperson = User::factory()->create();
    $organizer = Organizer::factory()->runBy($spokesperson)->create(['avatar_path' => 'organizers/logo.jpg']);
    Storage::disk('public')->put('organizers/logo.jpg', 'picture');
    $eventImage = eventWithPicture($spokesperson);
    $eventImage->event->update(['organizer_id' => $organizer->id]);
    $leaflet = leafletWithPicture($spokesperson, 'official');
    $leaflet->update(['organizer_id' => $organizer->id]);
    $ownLeaflet = leafletWithPicture($spokesperson);

    $this->actingAs($spokesperson)->delete(route('organizers.destroy', $organizer))->assertRedirect();

    Storage::disk('public')->assertMissing('organizers/logo.jpg');
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($eventImage->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($leaflet->image_path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($ownLeaflet->image_path);
});

test('the migration moves every event and leaflet picture to the private disk, and back', function () {
    $public = Storage::disk('public');
    $private = Storage::disk(ImageProcessor::PRIVATE_DISK);
    $public->put('events/1/cover.jpg', 'cover');
    $public->put('events/2/orphan.jpg', 'orphan');
    $public->put('leaflets/3/picture.jpg', 'picture');
    $public->put('avatars/face.jpg', 'face');
    $private->put('messages/4/chat.jpg', 'chat');

    $migration = require database_path('migrations/2026_10_30_220142_move_event_and_leaflet_images_to_the_private_disk.php');
    $migration->moveFiles($public, $private);

    expect($private->get('events/1/cover.jpg'))->toBe('cover')
        ->and($private->get('events/2/orphan.jpg'))->toBe('orphan')
        ->and($private->get('leaflets/3/picture.jpg'))->toBe('picture');
    expect($public->allFiles())->toBe(['avatars/face.jpg'])
        ->and($public->directories())->toBe(['avatars']);

    // Again: nothing left to move, nothing changes.
    $migration->moveFiles($public, $private);
    expect($private->allFiles('events'))->toHaveCount(2);

    $migration->moveFiles($private, $public);

    expect($public->get('events/1/cover.jpg'))->toBe('cover')
        ->and($public->get('leaflets/3/picture.jpg'))->toBe('picture')
        ->and($private->allFiles())->toBe(['messages/4/chat.jpg']);
});
