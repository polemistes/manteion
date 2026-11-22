<?php

use App\Enums\AttendanceStatus;
use App\Enums\ConversationType;
use App\Enums\EventVisibility;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\EventImage;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\Message;
use App\Models\Organizer;
use App\Models\Picture;
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
 * A picture on a message, leaflet or comment, with its two files on the
 * private disk.
 */
function storedPicture(Message|Leaflet|EventComment $owner): Picture
{
    $picture = $owner->pictures()->create([
        'path' => 'pictures/'.fake()->uuid().'.jpg',
        'thumb_path' => 'pictures/'.fake()->uuid().'.jpg',
        'width' => 1200,
        'height' => 900,
        'position' => $owner->pictures()->count(),
    ]);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($picture->path, 'picture');
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($picture->thumb_path, 'small picture');

    return $picture;
}

/**
 * A leaflet with a picture on the private disk.
 */
function leafletWithPicture(User $author, string $visibility = 'friends'): Leaflet
{
    $leaflet = Leaflet::factory()->{$visibility}()->create(['user_id' => $author->id]);
    storedPicture($leaflet);

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
    // A leaflet's picture from before pictures had smaller copies.
    $leaflet = Leaflet::factory()->create(['user_id' => $host->id]);
    $picture = $leaflet->pictures()->create(['path' => 'leaflets/9/legacy.webp']);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->put($picture->path, 'picture');

    $this->actingAs($host)->get($image->presentation()['url'])->assertOk();
    $this->actingAs($host)->get($picture->presentation()['url'])->assertOk();
    expect($picture->presentation()['thumb_url'])->toBe($picture->presentation()['url']);
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

test('uploaded leaflet pictures are kept on the private disk and open only for its readers', function () {
    $author = User::factory()->create();
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $author->id, 'addressee_id' => $friend->id]);

    $this->actingAs($author)->post('/leaflets', [
        'body' => 'Look what I found',
        'visibility' => 'friends',
        'pictures' => [UploadedFile::fake()->image('find.jpg', 600, 400), UploadedFile::fake()->image('close-up.jpg', 1200, 800)],
    ])->assertRedirect()->assertValid();

    $leaflet = Leaflet::query()->sole();
    $pictures = $leaflet->pictures()->get();

    expect($pictures)->toHaveCount(2)
        ->and($pictures[0]->path)->toStartWith("leaflets/{$leaflet->id}/")
        ->and([$pictures[0]->width, $pictures[0]->height])->toBe([600, 400]);

    foreach ($pictures as $picture) {
        Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($picture->path);
        Storage::disk('public')->assertMissing($picture->path);
    }

    $this->actingAs($friend)->get("/leaflets/{$leaflet->id}")
        ->assertInertia(fn (Assert $page) => $page->where('leaflet.pictures', $leaflet->picturesPresentation()));
    $this->actingAs($friend)->get('/leaflets')
        ->assertInertia(fn (Assert $page) => $page->where('leaflets.0.pictures', $leaflet->picturesPresentation()));

    $url = $pictures[1]->presentation()['url'];
    $thumbUrl = $pictures[1]->presentation()['thumb_url'];

    expect($thumbUrl)->not->toBe($url);
    $this->actingAs($friend)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($friend)->get($thumbUrl)->assertOk();
    $this->actingAs($author)->get($url)->assertOk();
    $this->actingAs($stranger)->get($url)->assertForbidden();
    $this->actingAs($stranger)->get($thumbUrl)->assertForbidden();
});

test('a leaflet picture taken off is deleted, the others stay, and new ones follow them', function () {
    $author = User::factory()->create();
    $leaflet = Leaflet::factory()->friends()->create(['user_id' => $author->id]);
    $gone = storedPicture($leaflet);
    $kept = storedPicture($leaflet);
    $oldUrl = $gone->presentation()['url'];

    $this->actingAs($author)->put("/leaflets/{$leaflet->id}", [
        'body' => $leaflet->body,
        'visibility' => 'friends',
        'remove_pictures' => [$gone->id],
        'pictures' => [UploadedFile::fake()->image('new.jpg', 600, 400)],
    ])->assertRedirect()->assertValid();

    $pictures = $leaflet->pictures()->get();

    expect($pictures)->toHaveCount(2)
        ->and($pictures[0]->id)->toBe($kept->id)
        ->and($pictures[1]->position)->toBeGreaterThan($pictures[0]->position);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($gone->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($gone->thumb_path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($kept->path);

    $this->actingAs($author)->get($oldUrl)->assertNotFound();
    $this->actingAs($author)->get($pictures[1]->presentation()['url'])->assertOk();
});

test('a picture of another leaflet cannot be taken off through this one', function () {
    $author = User::factory()->create();
    $leaflet = leafletWithPicture($author);
    $other = leafletWithPicture(User::factory()->create());
    $theirs = $other->pictures()->sole();

    $this->actingAs($author)->put("/leaflets/{$leaflet->id}", [
        'body' => $leaflet->body,
        'visibility' => 'friends',
        'remove_pictures' => [$theirs->id],
    ])->assertRedirect()->assertValid();

    expect($other->pictures()->count())->toBe(1)
        ->and($leaflet->pictures()->count())->toBe(1);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($theirs->path);
});

test('a leaflet never carries more pictures than a post may, kept and new together', function () {
    $author = User::factory()->create();
    $leaflet = Leaflet::factory()->friends()->create(['user_id' => $author->id]);
    Picture::factory()->count(Picture::MAX_PER_POST - 1)->onLeaflet($leaflet)->create();

    $this->actingAs($author)->put("/leaflets/{$leaflet->id}", [
        'body' => $leaflet->body,
        'visibility' => 'friends',
        'pictures' => [UploadedFile::fake()->image('a.jpg', 40, 40), UploadedFile::fake()->image('b.jpg', 40, 40)],
    ])->assertSessionHasErrors(['pictures' => 'You can add up to 10 pictures at a time.']);

    expect($leaflet->pictures()->count())->toBe(Picture::MAX_PER_POST - 1);
});

test('deleting a leaflet deletes its pictures', function () {
    $author = User::factory()->create();
    $leaflet = leafletWithPicture($author);
    $second = storedPicture($leaflet);
    $first = $leaflet->pictures()->first();

    $this->actingAs($author)->delete("/leaflets/{$leaflet->id}")->assertRedirect();

    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($first->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($first->thumb_path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($second->path);
    $this->assertDatabaseCount('pictures', 0);
});

test('deleting an account removes the pictures on its events, leaflets, messages and comments, and nobody elses', function (bool $byAdmin) {
    $user = User::factory()->create(['avatar_path' => 'avatars/mine.jpg']);
    Storage::disk('public')->put('avatars/mine.jpg', 'picture');
    $friend = User::factory()->create();
    $eventImage = eventWithPicture($user);
    $leafletPicture = leafletWithPicture($user)->pictures()->sole();
    $keptLeafletPicture = leafletWithPicture($friend)->pictures()->sole();

    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);
    $messagePicture = storedPicture(Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => '']));
    $keptMessagePicture = storedPicture(Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $friend->id, 'body' => '']));

    // Their comment on a friend's event goes with them; so does a
    // friend's comment on their event, which is deleted with the event.
    $friendsEvent = Event::factory()->create(['user_id' => $friend->id]);
    $ownComment = storedPicture(EventComment::factory()->create(['event_id' => $friendsEvent->id, 'user_id' => $user->id]));
    $commentOnTheirEvent = storedPicture(EventComment::factory()->create(['event_id' => $eventImage->event_id, 'user_id' => $friend->id]));
    $keptComment = storedPicture(EventComment::factory()->create(['event_id' => $friendsEvent->id, 'user_id' => $friend->id]));
    $private = Storage::disk(ImageProcessor::PRIVATE_DISK);

    if ($byAdmin) {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $user), ['password' => 'password'])->assertRedirect();
    } else {
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
    }

    expect(User::find($user->id))->toBeNull();
    Storage::disk('public')->assertMissing('avatars/mine.jpg');
    $private->assertMissing($eventImage->path);

    foreach ([$leafletPicture, $messagePicture, $ownComment, $commentOnTheirEvent] as $gone) {
        $private->assertMissing($gone->path);
        $private->assertMissing($gone->thumb_path);
        expect(Picture::find($gone->id))->toBeNull();
    }

    foreach ([$keptLeafletPicture, $keptMessagePicture, $keptComment] as $kept) {
        $private->assertExists($kept->path);
        $private->assertExists($kept->thumb_path);
    }
})->with(['by themselves' => false, 'by an administrator' => true]);

test('deleting an organizer removes its picture and those on its events, their comments and its leaflets', function () {
    $spokesperson = User::factory()->create();
    $organizer = Organizer::factory()->runBy($spokesperson)->create(['avatar_path' => 'organizers/logo.jpg']);
    Storage::disk('public')->put('organizers/logo.jpg', 'picture');
    $eventImage = eventWithPicture($spokesperson);
    $eventImage->event->update(['organizer_id' => $organizer->id]);
    $comment = storedPicture(EventComment::factory()->create(['event_id' => $eventImage->event_id]));
    $leaflet = leafletWithPicture($spokesperson, 'official');
    $leaflet->update(['organizer_id' => $organizer->id]);
    $leafletPicture = $leaflet->pictures()->sole();
    $ownLeafletPicture = leafletWithPicture($spokesperson)->pictures()->sole();

    $this->actingAs($spokesperson)->delete(route('organizers.destroy', $organizer))->assertRedirect();

    Storage::disk('public')->assertMissing('organizers/logo.jpg');
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($eventImage->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($comment->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($leafletPicture->path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertMissing($leafletPicture->thumb_path);
    Storage::disk(ImageProcessor::PRIVATE_DISK)->assertExists($ownLeafletPicture->path);
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
