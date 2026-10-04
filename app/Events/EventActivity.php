<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells everyone with an event's page open that part of it changed, so
 * they reload that part. It carries only what changed, never content: what
 * each viewer may see is worked out when their page reloads.
 *
 * Kinds: `comment`, `attendance`, `invitation`, `updated` and `deleted`.
 *
 * Rescued: an unreachable WebSocket server is logged instead of failing
 * the request that changed the event.
 */
class EventActivity implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $eventId,
        public string $kind,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("events.{$this->eventId}")];
    }

    public function broadcastAs(): string
    {
        return 'event.activity';
    }

    /**
     * @return array{event_id: int, kind: string}
     */
    public function broadcastWith(): array
    {
        return ['event_id' => $this->eventId, 'kind' => $this->kind];
    }
}
