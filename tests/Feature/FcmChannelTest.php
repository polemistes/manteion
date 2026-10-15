<?php

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\InvitedToEvent;
use App\Notifications\Messages\FcmMessage;
use App\Notifications\NewMessageReceived;
use App\Notifications\PushTest;
use App\Services\Fcm;
use App\Services\FcmAccessToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A Firebase service-account key that names the project, and an access
 * token that never asks Google. Returns the fake token, which counts how
 * often it was dropped.
 */
function configureFcm(string $projectId = 'manteion-test'): object
{
    $key = tempnam(sys_get_temp_dir(), 'fcm-key-');
    FcmKeyFiles::$paths[] = $key;
    file_put_contents($key, json_encode(['type' => 'service_account', 'project_id' => $projectId]));
    config(['services.fcm.credentials' => $key, 'services.fcm.project_id' => null]);

    $token = new class extends FcmAccessToken
    {
        public int $forgotten = 0;

        public function get(): string
        {
            return "access-token-{$this->forgotten}";
        }

        public function forget(): void
        {
            $this->forgotten++;
        }
    };
    app()->instance(FcmAccessToken::class, $token);

    return $token;
}

function fcmSendUrl(string $projectId = 'manteion-test'): string
{
    return "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
}

/**
 * A direct message from Kari to the returned recipient.
 */
function fcmDirectMessage(User $recipient): Message
{
    $sender = User::factory()->create(['name' => 'Kari']);
    $conversation = Conversation::factory()->create(['type' => ConversationType::Direct, 'creator_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $recipient->id]);

    return Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $sender->id, 'body' => 'See you at eight?']);
}

/**
 * The payload of a sent FCM request, decoded.
 *
 * @return array<string, mixed>
 */
function fcmPayload(Request $request): array
{
    return json_decode($request['message']['data']['payload'], true);
}

/**
 * The key files configureFcm() wrote, removed after each test.
 */
final class FcmKeyFiles
{
    /** @var list<string> */
    public static array $paths = [];
}

beforeEach(function () {
    Http::preventStrayRequests();
});

afterEach(function () {
    array_map(fn (string $path) => @unlink($path), FcmKeyFiles::$paths);
    FcmKeyFiles::$paths = [];
});

test('a message reaches the app as a data-only FCM message carrying the web push payload', function () {
    configureFcm();
    Http::fake([fcmSendUrl() => Http::response(['name' => 'projects/manteion-test/messages/1'])]);
    $recipient = User::factory()->create();
    PushSubscription::factory()->for($recipient, 'subscribable')->create();
    $app = PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();
    $message = fcmDirectMessage($recipient);
    $notification = new NewMessageReceived($message);

    app(FcmChannel::class)->send($recipient, $notification);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($app, $message, $recipient, $notification) {
        $sent = $request['message'];

        expect($request->url())->toBe(fcmSendUrl());
        expect($request->header('Authorization'))->toBe(['Bearer access-token-0']);
        expect($sent['token'])->toBe($app->endpoint);
        expect($sent)->not->toHaveKey('notification');
        expect($sent['android'])->toBe(['priority' => 'high', 'ttl' => '86400s', 'collapse_key' => "c{$message->conversation_id}"]);
        expect($sent['data'])->each->toBeString();
        expect(fcmPayload($request))->toBe(json_decode(json_encode($notification->toWebPush($recipient, $notification)->toArray()), true));
        expect(fcmPayload($request)['data'])
            ->type->toBe('message')
            ->category->toBe('direct_messages')
            ->sender_name->toBe('Kari')
            ->text->toBe('See you at eight?');

        return true;
    });
});

test('an activity notification carries its category, for the apps channel', function () {
    configureFcm();
    Http::fake([fcmSendUrl() => Http::response([])]);
    $recipient = User::factory()->create();
    PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();

    app(FcmChannel::class)->send($recipient, new InvitedToEvent(Event::factory()->create(), User::factory()->create()));

    Http::assertSent(function (Request $request) {
        expect(fcmPayload($request)['data'])->type->toBe('InvitedToEvent')->category->toBe('event_invites');
        expect($request['message']['android'])->toBe(['priority' => 'high', 'ttl' => '86400s']);

        return true;
    });
});

test('every app install of the user gets it, and none of the browsers', function () {
    configureFcm();
    Http::fake([fcmSendUrl() => Http::response([])]);
    $recipient = User::factory()->create();
    PushSubscription::factory()->count(2)->for($recipient, 'subscribable')->create();
    $phones = PushSubscription::factory()->count(2)->app()->for($recipient, 'subscribable')->create();

    app(FcmChannel::class)->send($recipient, new NewMessageReceived(fcmDirectMessage($recipient)));

    Http::assertSentCount(2);
    expect(Http::recorded()->map(fn (array $pair) => $pair[0]['message']['token'])->sort()->values()->all())
        ->toBe($phones->pluck('endpoint')->sort()->values()->all());
});

test('a user without the app sends nothing to FCM', function () {
    configureFcm();
    Http::fake();
    $recipient = User::factory()->create();
    PushSubscription::factory()->for($recipient, 'subscribable')->create();

    app(FcmChannel::class)->send($recipient, new NewMessageReceived(fcmDirectMessage($recipient)));

    Http::assertNothingSent();
});

test('an app install FCM no longer knows is removed, and the others still get it', function (int $status, array $error) {
    configureFcm();
    $recipient = User::factory()->create();
    $gone = PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();
    $alive = PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();
    Http::fake(fn (Request $request) => $request['message']['token'] === $gone->endpoint
        ? Http::response(['error' => $error], $status)
        : Http::response([]));

    app(FcmChannel::class)->send($recipient, new NewMessageReceived(fcmDirectMessage($recipient)));

    Http::assertSentCount(2);
    expect($recipient->pushSubscriptions()->pluck('id')->all())->toBe([$alive->id]);
})->with([
    'unregistered' => [404, ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]],
    'another project' => [403, ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'SenderId mismatch', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'SENDER_ID_MISMATCH']]]],
    'not a token' => [400, ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'The registration token is not a valid FCM registration token', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'INVALID_ARGUMENT']]]],
]);

test('a push FCM refuses for any other reason keeps the device and is logged', function () {
    configureFcm();
    Log::spy();
    $recipient = User::factory()->create();
    $device = PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();
    Http::fake([fcmSendUrl() => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Invalid value at message.android.ttl']], 400)]);

    app(FcmChannel::class)->send($recipient, new NewMessageReceived(fcmDirectMessage($recipient)));

    expect($recipient->pushSubscriptions()->pluck('id')->all())->toBe([$device->id]);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'FCM refused a push.' && $context['status'] === 400)->once();
});

test('FCM being unreachable keeps the device and does not throw', function () {
    configureFcm();
    $recipient = User::factory()->create();
    $device = PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();
    Http::fake([fcmSendUrl() => Http::failedConnection()]);

    app(FcmChannel::class)->send($recipient, new NewMessageReceived(fcmDirectMessage($recipient)));

    expect($recipient->pushSubscriptions()->pluck('id')->all())->toBe([$device->id]);
});

test('a refused access token is dropped and the push tried once more with a fresh one', function () {
    $token = configureFcm();
    $recipient = User::factory()->create();
    PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();
    Http::fake([fcmSendUrl() => Http::sequence()->push(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED']], 401)->push([])]);

    app(FcmChannel::class)->send($recipient, new NewMessageReceived(fcmDirectMessage($recipient)));

    expect($token->forgotten)->toBe(1);
    expect(Http::recorded()->map(fn (array $pair) => $pair[0]->header('Authorization')[0])->all())
        ->toBe(['Bearer access-token-0', 'Bearer access-token-1']);
});

test('without a Firebase key nothing is sent and nothing fails', function (?string $credentials) {
    config(['services.fcm.credentials' => $credentials, 'services.fcm.project_id' => 'manteion-test']);
    Http::fake();
    $recipient = User::factory()->create();
    $device = PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();

    expect(Fcm::configured())->toBeFalse();

    app(FcmChannel::class)->send($recipient, new NewMessageReceived(fcmDirectMessage($recipient)));

    Http::assertNothingSent();
    expect($recipient->pushSubscriptions()->pluck('id')->all())->toBe([$device->id]);
})->with([
    'not set' => [null],
    'a file that is not there' => ['/nonexistent/firebase-service-account.json'],
]);

test('the project id comes from the key file unless it is set', function () {
    configureFcm('from-the-key');

    expect(Fcm::projectId())->toBe('from-the-key');

    config(['services.fcm.project_id' => 'set-explicitly']);

    expect(Fcm::projectId())->toBe('set-explicitly');
});

test('a message sent in the app reaches the recipients phone', function () {
    configureFcm();
    Http::fake([fcmSendUrl() => Http::response([])]);
    $recipient = User::factory()->create();
    PushSubscription::factory()->app()->for($recipient, 'subscribable')->create();
    $message = fcmDirectMessage($recipient);

    $this->actingAs($message->sender)
        ->postJson("/messages/{$message->conversation_id}/send", ['body' => 'Coffee first?'])
        ->assertCreated();

    Http::assertSent(fn (Request $request) => fcmPayload($request)['body'] === 'Coffee first?');
});

test('a test push reaches only the device that asked, browser or app', function () {
    $user = User::factory()->create();
    $browser = PushSubscription::factory()->for($user, 'subscribable')->create();
    $phone = PushSubscription::factory()->app()->for($user, 'subscribable')->create();
    PushSubscription::factory()->app()->for($user, 'subscribable')->create();

    expect($user->routeNotificationForFcm(new PushTest($phone->endpoint))->pluck('id')->all())->toBe([$phone->id]);
    expect($user->routeNotificationForWebPush(new PushTest($phone->endpoint)))->toBeEmpty();
    expect($user->routeNotificationForWebPush(new PushTest($browser->endpoint))->pluck('id')->all())->toBe([$browser->id]);
    expect($user->routeNotificationForFcm(new PushTest($browser->endpoint)))->toBeEmpty();
});

test('web push goes to browsers only and FCM to app installs only', function () {
    $user = User::factory()->create();
    $browser = PushSubscription::factory()->for($user, 'subscribable')->create();
    $phone = PushSubscription::factory()->app()->for($user, 'subscribable')->create();

    expect($user->routeNotificationForWebPush()->pluck('id')->all())->toBe([$browser->id]);
    expect($user->routeNotificationForFcm()->pluck('id')->all())->toBe([$phone->id]);
});

test('the settings test push works for an app device', function () {
    configureFcm();
    Http::fake([fcmSendUrl() => Http::response([])]);
    $user = User::factory()->create();
    $phone = PushSubscription::factory()->app()->for($user, 'subscribable')->create();

    $this->actingAs($user)
        ->postJson('/settings/notifications/test', ['endpoint' => $phone->endpoint])
        ->assertNoContent();

    Http::assertSent(function (Request $request) use ($phone) {
        expect($request['message']['token'])->toBe($phone->endpoint);
        expect(fcmPayload($request)['data'])->type->toBe('test')->category->toBe('direct_messages');

        return true;
    });
});

test('every FCM data value is a string, as FCM requires', function () {
    $message = (new FcmMessage(['title' => 'Kari', 'data' => ['badge_count' => 3, 'url' => '/messages/1']], ['TTL' => 300, 'urgency' => 'high', 'topic' => 'c1']))->toArray();

    expect($message['data'])->toBe(['payload' => '{"title":"Kari","data":{"badge_count":3,"url":"/messages/1"}}']);
    expect($message['android'])->toBe(['priority' => 'high', 'ttl' => '300s', 'collapse_key' => 'c1']);
});

test('every page says whether the server can push to the app', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('fcmConfigured', false));

    configureFcm();

    $this->get('/')->assertInertia(fn ($page) => $page->where('fcmConfigured', true));
});
