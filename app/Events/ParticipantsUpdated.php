<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone joined or left the conversation. It carries nothing: clients
 * reload the participants, which also settles what they may now do.
 *
 * Rescued: an unreachable WebSocket server is logged instead of failing
 * the request that changed the participants.
 */
class ParticipantsUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $conversationId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversations.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'participants.updated';
    }

    /**
     * @return array{}
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
