<?php

use App\Enums\LeafletVisibility;
use App\Enums\UserRole;
use App\Jobs\FetchLeafletLinkPreview;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\Report;
use App\Models\User;
use App\Services\LinkPreview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{author: User, friend: User, follower: User, stranger: User}
 */
function leafletAudience(): array
{
    $author = User::factory()->create();
    $friend = User::factory()->create();
    $follower = User::factory()->create();
    $stranger = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $friend->id, 'addressee_id' => $author->id]);
    Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $author->id]);

    return compact('author', 'friend', 'follower', 'stranger');
}

test('a user can post a leaflet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leaflets', [
        'body' => 'Hello everyone',
        'visibility' => 'official',
    ])->assertRedirect('/leaflets');

    $this->assertDatabaseHas('leaflets', ['user_id' => $user->id, 'body' => 'Hello everyone', 'visibility' => 'official']);
});

test('the feed shows each leaflet only to the audience its visibility names', function () {
    ['author' => $author, 'friend' => $friend, 'follower' => $follower, 'stranger' => $stranger] = leafletAudience();

    Leaflet::factory()->official()->create(['user_id' => $author->id, 'body' => 'official one']);
    Leaflet::factory()->friendsAndFollowers()->create(['user_id' => $author->id, 'body' => 'friends and followers one']);
    Leaflet::factory()->friends()->create(['user_id' => $author->id, 'body' => 'friends one']);
    Leaflet::factory()->create(['user_id' => $author->id, 'body' => 'private one']);

    $bodiesFor = fn (User $viewer) => collect($this->actingAs($viewer)->get('/leaflets')->viewData('page')['props']['leaflets'])->pluck('body')->all();

    expect($bodiesFor($author))->toBe(['private one', 'friends one', 'friends and followers one', 'official one']);
    expect($bodiesFor($friend))->toBe(['friends one', 'friends and followers one', 'official one']);
    expect($bodiesFor($follower))->toBe(['friends and followers one', 'official one']);
    expect($bodiesFor($stranger))->toBe(['official one']);
});

test('the feed is newest first and can be narrowed to one visibility', function () {
    $user = User::factory()->create();
    Leaflet::factory()->official()->create(['user_id' => $user->id, 'body' => 'older official']);
    Leaflet::factory()->create(['user_id' => $user->id, 'body' => 'private note']);
    Leaflet::factory()->official()->create(['user_id' => $user->id, 'body' => 'newer official']);

    $this->actingAs($user)->get('/leaflets')
        ->assertInertia(fn ($page) => $page
            ->component('leaflets/Index')
            ->has('leaflets', 3)
            ->where('leaflets.0.body', 'newer official')
            ->where('scope', null));

    $this->actingAs($user)->get('/leaflets?scope=official')
        ->assertInertia(fn ($page) => $page
            ->has('leaflets', 2)
            ->where('leaflets.0.body', 'newer official')
            ->where('leaflets.1.body', 'older official')
            ->where('scope', 'official'));

    $this->actingAs($user)->get('/leaflets?scope=nonsense')
        ->assertInertia(fn ($page) => $page->has('leaflets', 3)->where('scope', null));
});

test('a leaflet with a link gets its preview fetched in the background', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leaflets', [
        'body' => 'Read this',
        'visibility' => 'friends',
        'link_url' => 'https://example.com/article',
    ])->assertRedirect();

    $leaflet = Leaflet::query()->firstOrFail();
    expect($leaflet->link_url)->toBe('https://example.com/article');
    Queue::assertPushed(FetchLeafletLinkPreview::class, fn (FetchLeafletLinkPreview $job) => $job->leaflet->is($leaflet));
});

test('the link preview job stores the pages open graph data', function () {
    Http::fake([
        'example.com/*' => Http::response(<<<'HTML'
            <html><head>
            <title>Fallback title</title>
            <meta property="og:title" content="A fine article">
            <meta property="og:description" content="Worth your time.">
            <meta property="og:image" content="/images/cover.jpg">
            </head><body></body></html>
            HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
    ]);
    $leaflet = Leaflet::factory()->withLink('https://example.com/article')->create();

    (new FetchLeafletLinkPreview($leaflet))->handle(app(LinkPreview::class));

    $leaflet->refresh();
    expect($leaflet->link_title)->toBe('A fine article')
        ->and($leaflet->link_description)->toBe('Worth your time.')
        ->and($leaflet->link_image_url)->toBe('https://example.com/images/cover.jpg')
        ->and($leaflet->link_fetched_at)->not->toBeNull();

    $this->actingAs($leaflet->owner)->get('/leaflets')
        ->assertInertia(fn ($page) => $page
            ->where('leaflets.0.link.title', 'A fine article')
            ->where('leaflets.0.link.host', 'example.com'));
});

test('a failed or non-html fetch leaves a plain link and still marks the link as fetched', function () {
    Http::fake(['example.com/*' => Http::response('nope', 500)]);
    $leaflet = Leaflet::factory()->withLink('https://example.com/broken')->create();

    (new FetchLeafletLinkPreview($leaflet))->handle(app(LinkPreview::class));

    $leaflet->refresh();
    expect($leaflet->link_title)->toBeNull()->and($leaflet->link_fetched_at)->not->toBeNull();
});

test('link previews are never fetched from private or local addresses', function () {
    Http::fake();
    $preview = app(LinkPreview::class);

    expect($preview->fetch('http://127.0.0.1/admin'))->toBeNull()
        ->and($preview->fetch('http://10.0.0.5/'))->toBeNull()
        ->and($preview->fetch('http://localhost:8000/'))->toBeNull()
        ->and($preview->fetch('ftp://example.com/'))->toBeNull();

    Http::assertNothingSent();
});

test('a leaflet may carry an image or a link but not both', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leaflets', [
        'body' => 'Both',
        'visibility' => 'private',
        'image' => UploadedFile::fake()->image('photo.jpg', 600, 400),
        'link_url' => 'https://example.com',
    ])->assertInvalid(['image']);

    $this->actingAs($user)->post('/leaflets', [
        'body' => 'Just a picture',
        'visibility' => 'private',
        'image' => UploadedFile::fake()->image('photo.jpg', 600, 400),
    ])->assertRedirect()->assertValid();

    $leaflet = Leaflet::query()->firstOrFail();
    expect($leaflet->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($leaflet->image_path);
});

test('the author can edit everything about a leaflet, including swapping the image for a link', function () {
    Storage::fake('public');
    Queue::fake();
    $user = User::factory()->create();
    Storage::disk('public')->put('leaflets/1/old.jpg', 'x');
    $leaflet = Leaflet::factory()->create(['user_id' => $user->id, 'image_path' => 'leaflets/1/old.jpg']);

    $this->actingAs($user)->put("/leaflets/{$leaflet->id}", [
        'body' => 'Changed text',
        'visibility' => 'official',
        'remove_image' => '1',
        'link_url' => 'https://example.com/new',
    ])->assertRedirect()->assertValid();

    $leaflet->refresh();
    expect($leaflet->body)->toBe('Changed text')
        ->and($leaflet->visibility)->toBe(LeafletVisibility::Official)
        ->and($leaflet->image_path)->toBeNull()
        ->and($leaflet->link_url)->toBe('https://example.com/new');
    Storage::disk('public')->assertMissing('leaflets/1/old.jpg');
    Queue::assertPushed(FetchLeafletLinkPreview::class);
});

test('a link cannot be added while an image is kept', function () {
    $user = User::factory()->create();
    $leaflet = Leaflet::factory()->create(['user_id' => $user->id, 'image_path' => 'leaflets/1/old.jpg']);

    $this->actingAs($user)->put("/leaflets/{$leaflet->id}", [
        'body' => $leaflet->body,
        'visibility' => 'private',
        'link_url' => 'https://example.com/new',
    ])->assertInvalid(['link_url']);
});

test('only the author can edit, and only the author or staff can delete', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $leaflet = Leaflet::factory()->official()->create(['user_id' => $author->id]);

    $this->actingAs($other)->get("/leaflets/{$leaflet->id}/edit")->assertForbidden();
    $this->actingAs($other)->put("/leaflets/{$leaflet->id}", ['body' => 'x', 'visibility' => 'official'])->assertForbidden();
    $this->actingAs($other)->delete("/leaflets/{$leaflet->id}")->assertForbidden();

    $this->actingAs($moderator)->get("/leaflets/{$leaflet->id}/edit")->assertForbidden();
    $this->actingAs($moderator)->delete("/leaflets/{$leaflet->id}")->assertRedirect();
    $this->assertModelMissing($leaflet);
});

test('a private leaflet can be seen on its own page only by its author and staff', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $leaflet = Leaflet::factory()->create(['user_id' => $author->id]);

    $this->actingAs($author)->get("/leaflets/{$leaflet->id}")->assertOk();
    $this->actingAs($other)->get("/leaflets/{$leaflet->id}")->assertForbidden();
    $this->actingAs($moderator)->get("/leaflets/{$leaflet->id}")->assertOk();
});

test('a leaflet can be reported and deleting it resolves the report', function () {
    $author = User::factory()->create();
    $reporter = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $leaflet = Leaflet::factory()->official()->create(['user_id' => $author->id, 'body' => 'Spammy']);

    $this->actingAs($reporter)->post('/reports', ['reportable_type' => 'leaflet', 'reportable_id' => $leaflet->id, 'reason' => 'Spam'])->assertRedirect();
    $report = Report::query()->firstOrFail();

    $this->actingAs($moderator)->get('/moderation/reports')
        ->assertInertia(fn ($page) => $page
            ->where('reports.0.reportable.type', 'leaflet')
            ->where('reports.0.reportable.excerpt', 'Spammy'));

    $this->actingAs($moderator)->delete("/leaflets/{$leaflet->id}")->assertRedirect();
    expect($report->fresh()->status->value)->toBe('resolved');
});

test('the feed is paginated twenty leaflets a page', function () {
    $user = User::factory()->create();
    Leaflet::factory()->count(21)->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/leaflets')
        ->assertInertia(fn ($page) => $page->has('leaflets', 20)->where('pagination.last_page', 2));
});
