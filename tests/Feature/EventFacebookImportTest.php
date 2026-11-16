<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A Facebook event page as a crawler gets it: the event spread over
 * several pieces of embedded JSON, its end time in a piece without an id,
 * and another (suggested) event beside it.
 *
 * @param  array<string, mixed>  $overrides
 */
function facebookEventPage(string $id = '1652056599320293', array $overrides = []): string
{
    $event = [
        'name' => 'Cyberia Reunion + Rotersand',
        'start_timestamp' => 1787940000,
        'end_timestamp' => 1787968800,
        'description' => "Een hele nacht lang dansen.\r\n\r\nMet Rotersand.",
        'place' => [
            '__typename' => 'Page',
            'name' => 'De Helling',
            'contextual_name' => 'De Helling',
            'location' => ['latitude' => 52.07683, 'longitude' => 5.12176],
            'city' => ['contextual_name' => 'Utrecht, Nederland', 'id' => '169137873096468'],
        ],
        'cover' => 'https://scontent-arn2-1.xx.fbcdn.net/v/t39.30808-6/cover_n.jpg?stp=dst-jpg',
        ...$overrides,
    ];

    $blocks = [
        // The details: description and the place with its pin.
        ['require' => [['__bbox' => ['result' => ['data' => ['event' => [
            'id' => $id,
            'event_description' => ['text' => $event['description']],
            'event_place' => $event['place'],
            'is_online' => false,
        ]]]]]]],
        // The times, in a piece without the event's id.
        ['require' => [['__bbox' => ['result' => ['data' => [
            'tz_display_name' => 'UTC+02',
            'start_timestamp' => $event['start_timestamp'],
            'end_timestamp' => $event['end_timestamp'],
        ]]]]]],
        // The header: name, start, cover picture.
        ['require' => [['__bbox' => ['result' => ['data' => ['event' => [
            '__typename' => 'Event',
            'id' => $id,
            'name' => $event['name'],
            'start_timestamp' => $event['start_timestamp'],
            'event_place' => ['__typename' => 'Page', 'name' => $event['place']['name'] ?? null],
            'cover_media_renderer' => $event['cover'] === null ? null : [
                'cover_photo' => ['photo' => ['full_image' => ['uri' => $event['cover'], 'width' => 960, 'height' => 565]]],
            ],
        ]]]]]]],
        // Another event the page suggests.
        ['result' => ['data' => ['event_suggested_events' => ['edges' => [['node' => [
            '__typename' => 'Event',
            'id' => '1779119833343281',
            'name' => 'Dance In The UNDERDARK',
            'start_timestamp' => 1797710400,
            'end_timestamp' => 1797739200,
            'event_place' => ['name' => 'K-Sjot', 'location' => ['latitude' => 1.0, 'longitude' => 2.0]],
        ]]]]]]],
    ];

    $scripts = collect($blocks)
        ->map(fn (array $block) => '<script type="application/json" data-content-len="1" data-sjs>'.json_encode($block).'</script>')
        ->implode("\n");

    return '<!DOCTYPE html><html><head><meta property="og:url" content="https://www.facebook.com/events/de-helling/cyberia/'.$id.'/" /></head><body>'.$scripts.'</body></html>';
}

/**
 * A real, tiny JPEG.
 */
function facebookCoverBytes(): string
{
    $image = imagecreatetruecolor(12, 8);

    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('a public Facebook event is read into the fields of the event form', function () {
    Http::fake([
        'www.facebook.com/events/1652056599320293/' => Http::response(facebookEventPage()),
        'scontent-arn2-1.xx.fbcdn.net/*' => Http::response(facebookCoverBytes(), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => 'https://www.facebook.com/events/1652056599320293/?ref=share']);

    $response->assertOk()->assertJson([
        'name' => 'Cyberia Reunion + Rotersand',
        'description' => "Een hele nacht lang dansen.\n\nMet Rotersand.",
        'starts_at' => '2026-08-28T18:00:00Z',
        'ends_at' => '2026-08-29T02:00:00Z',
        'place' => 'De Helling',
        'latitude' => 52.07683,
        'longitude' => 5.12176,
        'city' => 'Utrecht',
    ]);

    expect($response->json('image'))->toStartWith('data:image/jpeg;base64,');
    expect(base64_decode(substr($response->json('image'), strlen('data:image/jpeg;base64,'))))->toBe(facebookCoverBytes());

    // Facebook is asked as an honest crawler, and never left to redirect on its own.
    Http::assertSent(fn (Request $request) => str_contains($request->header('User-Agent')[0], 'ManteionBot'));
});

test('every way of writing the event link leads to the same page', function (string $link) {
    Http::fake([
        'www.facebook.com/events/1652056599320293/' => Http::response(facebookEventPage(overrides: ['cover' => null])),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => $link])
        ->assertOk()
        ->assertJsonPath('name', 'Cyberia Reunion + Rotersand')
        ->assertJsonPath('image', null);

    Http::assertSentCount(1);
})->with([
    'plain' => 'https://www.facebook.com/events/1652056599320293',
    'without www' => 'http://facebook.com/events/1652056599320293/',
    'mobile' => 'https://m.facebook.com/events/1652056599320293/?acontext=%7B%22ref%22%3A%2252%22%7D',
    'with the place and name' => 'https://www.facebook.com/events/de-helling/cyberia-reunion-rotersand/1652056599320293/',
    'the old address' => 'https://www.facebook.com/event.php?eid=1652056599320293',
    'one occurrence of a repeating event' => 'https://www.facebook.com/events/999999999999/?event_time_id=1652056599320293',
]);

test('a short share link is followed to the event, inside Facebook only', function () {
    Http::fake([
        'fb.me/e/7qAqwdZPZ' => Http::response('', 302, ['Location' => 'https://www.facebook.com/event_invite/7qAqwdZPZ/']),
        'www.facebook.com/event_invite/7qAqwdZPZ/' => Http::response('', 302, ['Location' => 'https://www.facebook.com/events/1652056599320293/?acontext=x']),
        'www.facebook.com/events/1652056599320293/' => Http::response(facebookEventPage(overrides: ['cover' => null])),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => 'https://fb.me/e/7qAqwdZPZ'])
        ->assertOk()
        ->assertJsonPath('name', 'Cyberia Reunion + Rotersand');
});

test('a share link that leaves Facebook is not followed', function () {
    Http::fake([
        'fb.me/e/abc' => Http::response('', 302, ['Location' => 'http://192.168.1.1/events/1652056599320293/']),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => 'https://fb.me/e/abc'])
        ->assertNotFound();

    Http::assertSentCount(1);
});

test('an event without an end, a pin or a picture still imports', function () {
    Http::fake([
        'www.facebook.com/events/1652056599320293/' => Http::response(facebookEventPage(overrides: [
            'end_timestamp' => 0,
            'description' => '',
            'place' => ['__typename' => 'FreeformPlace', 'name' => 'StudioGonz', 'contextual_name' => 'StudioGonz'],
            'cover' => null,
        ])),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => 'https://www.facebook.com/events/1652056599320293/'])
        ->assertOk()
        ->assertExactJson([
            'name' => 'Cyberia Reunion + Rotersand',
            'description' => '',
            'starts_at' => '2026-08-28T18:00:00Z',
            'ends_at' => null,
            'place' => 'StudioGonz',
            'latitude' => null,
            'longitude' => null,
            'city' => null,
            'image' => null,
        ]);
});

test('a private or removed event, answered with a sign-in page, is not found', function () {
    Http::fake([
        'www.facebook.com/events/123456789012345/' => Http::response('<html><head><meta property="og:title" content="Log in or sign up to view" /></head><body><script type="application/json">{"viewer":{"id":"0"}}</script></body></html>'),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => 'https://www.facebook.com/events/123456789012345/'])
        ->assertNotFound();
});

test('an event sent to the sign-in page is not found', function () {
    Http::fake([
        'www.facebook.com/events/123456789012345/' => Http::response('', 302, ['Location' => 'https://www.facebook.com/login/?next=https%3A%2F%2Fwww.facebook.com%2Fevents%2F123456789012345%2F']),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => 'https://www.facebook.com/events/123456789012345/'])
        ->assertNotFound();

    Http::assertSentCount(1);
});

test('a cover picture is only taken from Facebook, and only if it is a picture', function (string $coverUrl, string $body) {
    Http::fake([
        'www.facebook.com/events/1652056599320293/' => Http::response(facebookEventPage(overrides: ['cover' => $coverUrl])),
        'scontent-arn2-1.xx.fbcdn.net/*' => Http::response($body, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => 'https://www.facebook.com/events/1652056599320293/'])
        ->assertOk()
        ->assertJsonPath('name', 'Cyberia Reunion + Rotersand')
        ->assertJsonPath('image', null);
})->with([
    'another host' => ['https://example.com/cover.jpg', 'unused'],
    'a look-alike host' => ['https://evilfbcdn.net/cover.jpg', 'unused'],
    'not https' => ['http://scontent-arn2-1.xx.fbcdn.net/cover.jpg', 'unused'],
    'not a picture' => ['https://scontent-arn2-1.xx.fbcdn.net/cover.jpg', '<html>nope</html>'],
]);

test('only links to Facebook events are accepted', function (string $link) {
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->postJson('/events/import/facebook', ['url' => $link])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    Http::assertNothingSent();
})->with([
    'another site' => 'https://example.com/events/1652056599320293/',
    'a look-alike host' => 'https://facebook.com.example.com/events/1652056599320293/',
    'a Facebook page that is not an event' => 'https://www.facebook.com/blaaoslo',
    'this server' => 'http://localhost/events/1652056599320293/',
    'not a link' => 'the pub quiz on Friday',
    'another scheme' => 'file:///events/1652056599320293/',
]);

test('importing needs a signed-in user and is rate limited', function () {
    Http::fake();

    $this->postJson('/events/import/facebook', ['url' => 'https://www.facebook.com/events/1652056599320293/'])
        ->assertUnauthorized();

    $user = User::factory()->create();

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($user)->postJson('/events/import/facebook', ['url' => 'nope'])->assertUnprocessable();
    }

    $this->actingAs($user)->postJson('/events/import/facebook', ['url' => 'nope'])->assertTooManyRequests();
});
