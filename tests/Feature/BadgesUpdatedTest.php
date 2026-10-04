<?php

use App\Events\BadgesUpdated;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Exceptions;

/**
 * Route broadcasts through the given broadcaster instead of the test
 * environment's "null" driver.
 */
function broadcastBadgesThrough(Broadcaster $broadcaster): void
{
    Broadcast::extend('badges-test', fn () => $broadcaster);
    config([
        'broadcasting.default' => 'badges-test',
        'broadcasting.connections.badges-test' => ['driver' => 'badges-test'],
    ]);
    Broadcast::forgetDrivers();
}

test('it goes out right away on the users private channel as badges.updated', function () {
    $event = new BadgesUpdated(42);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    expect(array_map('strval', $event->broadcastOn()))->toBe(['private-App.Models.User.42']);
    expect($event->broadcastAs())->toBe('badges.updated');
});

test('the payload is the counts at the moment it is sent, not when it was created', function () {
    $user = User::factory()->create();
    $event = new BadgesUpdated($user->id);

    Friendship::factory()->create(['requester_id' => User::factory()->create()->id, 'addressee_id' => $user->id]);

    expect($event->broadcastWith())->toBe(['messages' => 0, 'activity' => 0, 'requests' => 1]);
});

test('a user who no longer exists gets zero badges', function () {
    expect((new BadgesUpdated(999999))->broadcastWith())->toBe(['messages' => 0, 'activity' => 0, 'requests' => 0]);
});

test('dispatching it broadcasts the counts without waiting for a queue', function () {
    $broadcaster = new class implements Broadcaster
    {
        /** @var list<array{channels: list<string>, event: string, payload: array<string, mixed>}> */
        public array $sent = [];

        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = [])
        {
            $this->sent[] = ['channels' => array_map('strval', $channels), 'event' => $event, 'payload' => $payload];
        }
    };
    broadcastBadgesThrough($broadcaster);
    config(['queue.default' => 'database']);
    $user = User::factory()->create();
    Friendship::factory()->create(['requester_id' => User::factory()->create()->id, 'addressee_id' => $user->id]);

    BadgesUpdated::dispatch($user->id);

    expect($broadcaster->sent)->toHaveCount(1);
    expect($broadcaster->sent[0]['channels'])->toBe(["private-App.Models.User.{$user->id}"]);
    expect($broadcaster->sent[0]['event'])->toBe('badges.updated');
    expect($broadcaster->sent[0]['payload'])->toMatchArray(['messages' => 0, 'activity' => 0, 'requests' => 1]);
});

test('an unreachable WebSocket server is reported instead of failing the request', function () {
    Exceptions::fake();
    broadcastBadgesThrough(new class implements Broadcaster
    {
        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = [])
        {
            throw new BroadcastException('Connection refused');
        }
    });
    $user = User::factory()->create();

    BadgesUpdated::dispatch($user->id);

    Exceptions::assertReported(BroadcastException::class);
});
